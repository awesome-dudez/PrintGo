<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardResource;
use App\Models\MaintenanceConfiguration;
use App\Models\PrintJob;
use App\Models\Transaction;
use App\Services\PrinterStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __construct(
        protected PrinterStatusService $printerStatusService
    ) {}

    /**
     * Get aggregated metrics and stats for the admin dashboard.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();

        // Completed transactions earnings
        $completedTransactions = Transaction::query()->where('print_status', 'completed');

        $dailyEarnings = (float) (clone $completedTransactions)
            ->where('created_at', '>=', $today)
            ->sum('amount');

        $weeklyEarnings = (float) (clone $completedTransactions)
            ->where('created_at', '>=', $startOfWeek)
            ->sum('amount');

        $monthlyEarnings = (float) (clone $completedTransactions)
            ->where('created_at', '>=', $startOfMonth)
            ->sum('amount');

        $totalEarnings = (float) (clone $completedTransactions)->sum('amount');

        $totalTransactionsCount = (int) Transaction::query()->count();
        $totalPagesPrintedCount = (int) Transaction::query()
            ->where('print_status', 'completed')
            ->sum(\Illuminate\Support\Facades\DB::raw('page_count * copies'));

        $activeJobsCount = (int) PrintJob::query()
            ->whereIn('status', ['queued', 'printing'])
            ->count();

        $printerStatus = $this->printerStatusService->getStatus();
        $maintenanceEnabled = MaintenanceConfiguration::isEnabled();
        $maintenanceMessage = MaintenanceConfiguration::getMessage();

        $recentTransactions = Transaction::query()
            ->with(['printJob.file'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Transaction $tx) => [
                'id' => $tx->id,
                'amount' => (float) $tx->amount,
                'status' => $tx->print_status,
                'file_type' => $tx->file_type,
                'pages_printed' => (int) ($tx->page_count * $tx->copies),
                'created_at' => $tx->created_at?->toIso8601String(),
            ]);

        $payload = [
            'earnings' => [
                'daily' => $dailyEarnings,
                'weekly' => $weeklyEarnings,
                'monthly' => $monthlyEarnings,
                'total' => $totalEarnings,
            ],
            'total_transactions' => $totalTransactionsCount,
            'total_pages_printed' => $totalPagesPrintedCount,
            'active_jobs' => $activeJobsCount,
            'printer' => $printerStatus,
            'maintenance' => [
                'enabled' => $maintenanceEnabled,
                'message' => $maintenanceMessage,
            ],
            'recent_transactions' => $recentTransactions,
        ];

        return $this->successResponse(
            data: (new DashboardResource($payload))->resolve()
        );
    }
}
