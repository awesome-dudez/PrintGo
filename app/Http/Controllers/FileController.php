<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreFileRequest;
use App\Http\Resources\PreviewResource;
use App\Http\Resources\UploadedFileResource;
use App\Jobs\ProcessDocumentJob;
use App\Models\PrintingSession;
use App\Models\UploadedFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Symfony\Component\HttpFoundation\Response;

class FileController extends Controller
{
    /**
     * Upload a new document file and initialize session.
     *
     * @param StoreFileRequest $request
     * @return JsonResponse
     */
    public function store(StoreFileRequest $request): JsonResponse
    {
        $uploadedFile = $request->file('file');
        $sessionToken = $request->input('session_id');

        // Resolve or create PrintingSession
        $session = null;
        if ($sessionToken) {
            $session = PrintingSession::query()
                ->where('id', $sessionToken)
                ->first();
        }

        if (!$session) {
            $session = PrintingSession::query()->create([
                'status' => 'active',
                'last_activity_at' => now(),
                'expires_at' => now()->addMinutes(config('printing.session_ttl_minutes', 30)),
            ]);
        }

        $originalName = $uploadedFile->getClientOriginalName();
        $extension = strtolower($uploadedFile->getClientOriginalExtension());
        $mimeType = $uploadedFile->getMimeType() ?: 'application/octet-stream';
        $fileSize = $uploadedFile->getSize();

        // Store file in uploads directory
        $storedPath = $uploadedFile->store('uploads', 'local');

        // Initial page count detection
        $pageCount = 1;
        if ($extension === 'pdf') {
            try {
                $fullPath = Storage::disk('local')->path($storedPath);
                $parser = new Parser();
                $pdf = $parser->parseFile($fullPath);
                $pageCount = max(1, count($pdf->getPages()));
            } catch (\Throwable) {
                $pageCount = 1;
            }
        }

        // Generate initial preview paths
        $previewPaths = [
            "previews/preview_placeholder.png",
        ];

        /** @var UploadedFile $fileRecord */
        $fileRecord = UploadedFile::query()->create([
            'session_id' => $session->id,
            'original_name' => $originalName,
            'stored_path' => $storedPath,
            'mime_type' => $mimeType,
            'file_type' => $extension,
            'file_size' => $fileSize,
            'page_count' => $pageCount,
            'status' => 'ready',
            'preview_paths' => $previewPaths,
        ]);

        // Dispatch background processing if configured
        ProcessDocumentJob::dispatch($fileRecord)->afterResponse();

        return $this->successResponse(
            data: (new UploadedFileResource($fileRecord))->resolve(),
            code: Response::HTTP_CREATED
        );
    }

    /**
     * Get uploaded file metadata and processing status.
     *
     * @param int|string $fileId
     * @return JsonResponse
     */
    public function show(int|string $fileId): JsonResponse
    {
        $file = UploadedFile::query()->with('session')->find($fileId);

        if (!$file) {
            return $this->errorResponse(
                errorCode: 'NOT_FOUND',
                message: 'Uploaded file not found.',
                statusCode: Response::HTTP_NOT_FOUND
            );
        }

        return $this->successResponse(
            data: (new UploadedFileResource($file))->resolve()
        );
    }

    /**
     * Get preview images and metadata for an uploaded file.
     *
     * @param int|string $fileId
     * @return JsonResponse
     */
    public function preview(int|string $fileId): JsonResponse
    {
        $file = UploadedFile::query()->find($fileId);

        if (!$file) {
            return $this->errorResponse(
                errorCode: 'NOT_FOUND',
                message: 'Uploaded file not found.',
                statusCode: Response::HTTP_NOT_FOUND
            );
        }

        return $this->successResponse(
            data: (new PreviewResource($file))->resolve()
        );
    }
}
