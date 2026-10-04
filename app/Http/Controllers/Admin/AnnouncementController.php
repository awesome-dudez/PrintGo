<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Http\Requests\UpdateAnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AnnouncementController extends Controller
{
    /**
     * List all announcements (including inactive).
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $announcements = Announcement::query()->orderByDesc('created_at')->get();

        return $this->successResponse(
            data: AnnouncementResource::collection($announcements)->resolve()
        );
    }

    /**
     * Store a new announcement.
     *
     * @param StoreAnnouncementRequest $request
     * @return JsonResponse
     */
    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        $data = $request->only(['title', 'content', 'type', 'is_active']);
        if (!isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        $announcement = Announcement::query()->create($data);

        AuditLog::log('announcement_created', ['announcement_id' => $announcement->id, 'title' => $announcement->title]);

        return $this->successResponse(
            data: (new AnnouncementResource($announcement))->resolve(),
            message: 'Announcement created successfully.',
            code: Response::HTTP_CREATED
        );
    }

    /**
     * Update an existing announcement.
     *
     * @param UpdateAnnouncementRequest $request
     * @param int|string $id
     * @return JsonResponse
     */
    public function update(UpdateAnnouncementRequest $request, int|string $id): JsonResponse
    {
        $announcement = Announcement::query()->find($id);

        if (!$announcement) {
            return $this->errorResponse(
                errorCode: 'NOT_FOUND',
                message: 'Announcement not found.',
                statusCode: Response::HTTP_NOT_FOUND
            );
        }

        $fields = array_filter(
            $request->only(['title', 'content', 'type', 'is_active']),
            fn ($value) => $value !== null
        );

        $announcement->update($fields);

        AuditLog::log('announcement_updated', ['announcement_id' => $announcement->id, 'updated_fields' => array_keys($fields)]);

        return $this->successResponse(
            data: (new AnnouncementResource($announcement))->resolve(),
            message: 'Announcement updated successfully.'
        );
    }

    /**
     * Delete an announcement.
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function destroy(int|string $id): JsonResponse
    {
        $announcement = Announcement::query()->find($id);

        if (!$announcement) {
            return $this->errorResponse(
                errorCode: 'NOT_FOUND',
                message: 'Announcement not found.',
                statusCode: Response::HTTP_NOT_FOUND
            );
        }

        $title = $announcement->title;
        $announcement->delete();

        AuditLog::log('announcement_deleted', ['announcement_id' => $id, 'title' => $title]);

        return $this->successResponse(
            data: [],
            message: 'Announcement deleted successfully.'
        );
    }
}
