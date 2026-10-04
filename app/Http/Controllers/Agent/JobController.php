<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePrintJobStatusRequest;
use App\Http\Resources\PrintJobResource;
use App\Models\PrintJob;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class JobController extends Controller
{
    /**
     * Get list of pending (queued) print jobs waiting for execution.
     *
     * @return JsonResponse
     */
    public function pending(): JsonResponse
    {
        $jobs = PrintJob::query()
            ->with(['uploadedFile', 'session'])
            ->where('status', 'queued')
            ->orderBy('created_at', 'asc')
            ->get();

        return $this->successResponse(
            data: PrintJobResource::collection($jobs)->resolve()
        );
    }

    /**
     * Update status and progress of a print job by agent/worker.
     *
     * @param UpdatePrintJobStatusRequest $request
     * @param int|string $id
     * @return JsonResponse
     */
    public function updateStatus(UpdatePrintJobStatusRequest $request, int|string $id): JsonResponse
    {
        /** @var PrintJob|null $job */
        $job = PrintJob::query()->with(['uploadedFile', 'session'])->find($id);

        if (!$job) {
            return $this->errorResponse(
                errorCode: 'JOB_NOT_FOUND',
                message: 'Print job not found.',
                statusCode: Response::HTTP_NOT_FOUND
            );
        }

        $newStatus = $request->input('status');
        $pagesCompleted = $request->input('pages_completed');
        $errorMessage = $request->input('error_message');

        $updateData = [
            'status' => $newStatus,
        ];

        if ($pagesCompleted !== null) {
            $updateData['pages_completed'] = (int) $pagesCompleted;
        }

        if ($errorMessage !== null) {
            $updateData['error_message'] = $errorMessage;
        }

        if ($newStatus === 'completed' && $pagesCompleted === null) {
            $updateData['pages_completed'] = $job->page_count * $job->copies;
            $updateData['progress'] = 100;
            $updateData['completed_at'] = now();
        }

        $job->update($updateData);

        // If completed, ensure transaction record exists or is recorded
        if ($newStatus === 'completed') {
            $existingTx = Transaction::query()->where('print_job_id', $job->id)->first();
            if (!$existingTx) {
                Transaction::query()->create([
                    'session_id' => $job->session_id,
                    'print_job_id' => $job->id,
                    'file_type' => $job->uploadedFile?->file_type ?? 'pdf',
                    'page_count' => $job->page_count,
                    'copies' => $job->copies,
                    'paper_size' => $job->paper_size,
                    'paper_type' => $job->paper_type,
                    'orientation' => $job->orientation,
                    'color_mode' => $job->color_mode,
                    'duplex' => $job->duplex,
                    'amount' => $job->total_price,
                    'currency' => 'PHP',
                    'payment_status' => 'paid',
                    'print_status' => 'completed',
                ]);
            } else {
                $existingTx->update([
                    'print_status' => 'completed',
                ]);
            }

            // Update session status if all jobs done
            if ($job->session) {
                $hasPendingJobs = PrintJob::query()
                    ->where('session_id', $job->session_id)
                    ->whereIn('status', ['queued', 'printing'])
                    ->exists();

                if (!$hasPendingJobs) {
                    $job->session->update(['status' => 'completed']);
                }
            }
        }

        return $this->successResponse(
            data: (new PrintJobResource($job->fresh(['uploadedFile', 'session'])))->resolve(),
            message: "Print job status updated to {$newStatus}."
        );
    }
}
