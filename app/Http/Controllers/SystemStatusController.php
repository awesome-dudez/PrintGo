<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\SystemStatusResource;
use App\Models\Announcement;
use App\Models\MaintenanceConfiguration;
use App\Services\PrinterStatusService;
use Illuminate\Http\JsonResponse;

class SystemStatusController extends Controller
{
    public function __construct(
        protected PrinterStatusService $printerStatusService
    ) {}

    /**
     * Get overall system health and status.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $maintenanceEnabled = MaintenanceConfiguration::isEnabled();
        $maintenanceMessage = MaintenanceConfiguration::getMessage();
        $printerStatus = $this->printerStatusService->getStatus()->toArray();
        $announcements = Announcement::active()->get(['id', 'title', 'content', 'type', 'created_at']);

        $isOperational = !$maintenanceEnabled && ($printerStatus['is_ready'] ?? false);

        $payload = [
            'status' => $maintenanceEnabled ? 'maintenance' : ($isOperational ? 'operational' : 'degraded'),
            'is_operational' => $isOperational,
            'printer' => $printerStatus,
            'maintenance_enabled' => $maintenanceEnabled,
            'maintenance_message' => $maintenanceMessage,
            'active_announcements' => $announcements,
            'timestamp' => now()->toIso8601String(),
        ];

        return $this->successResponse(
            data: (new SystemStatusResource($payload))->resolve()
        );
    }
}
