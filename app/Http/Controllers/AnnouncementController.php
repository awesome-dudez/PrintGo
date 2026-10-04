<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;

class AnnouncementController extends Controller
{
    /**
     * Get list of active system announcements.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $announcements = Announcement::active()->orderByDesc('created_at')->get();

        return $this->successResponse(
            data: AnnouncementResource::collection($announcements)->resolve()
        );
    }
}
