<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMaintenanceRequest;
use App\Models\AuditLog;
use App\Models\MaintenanceConfiguration;
use Illuminate\Http\JsonResponse;

class MaintenanceController extends Controller
{
    /**
     * Get the current maintenance mode settings.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        return $this->successResponse(
            data: [
                'enabled' => MaintenanceConfiguration::isEnabled(),
                'message' => MaintenanceConfiguration::getMessage(),
            ]
        );
    }

    /**
     * Update maintenance mode configuration.
     *
     * @param UpdateMaintenanceRequest $request
     * @return JsonResponse
     */
    public function update(UpdateMaintenanceRequest $request): JsonResponse
    {
        $enabled = (bool) $request->input('enabled');
        $message = $request->input('message');

        $config = MaintenanceConfiguration::setMaintenance($enabled, $message);

        AuditLog::log('maintenance_mode_toggled', [
            'is_enabled' => $enabled,
            'message' => $config->message,
        ]);

        return $this->successResponse(
            data: [
                'enabled' => (bool) $config->is_enabled,
                'message' => $config->message,
            ],
            message: 'Maintenance configuration updated successfully.'
        );
    }
}
