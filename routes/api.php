<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\MaintenanceController as AdminMaintenanceController;
use App\Http\Controllers\Admin\PricingController as AdminPricingController;
use App\Http\Controllers\Admin\TransactionController as AdminTransactionController;
use App\Http\Controllers\Agent\JobController as AgentJobController;
use App\Http\Controllers\Agent\PaymentController as AgentPaymentController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PrinterStatusController;
use App\Http\Controllers\PrintJobController;
use App\Http\Controllers\PrintPriceController;
use App\Http\Controllers\SystemStatusController;
use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\CheckPrinterAvailable;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Version 1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ==========================================
    // Public / Kiosk Endpoints
    // ==========================================
    Route::get('/system/status', [SystemStatusController::class, 'index'])->name('v1.system.status');
    Route::get('/printer/status', [PrinterStatusController::class, 'index'])->name('v1.printer.status');
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('v1.announcements');

    // File handling
    Route::post('/files', [FileController::class, 'store'])
        ->middleware([CheckMaintenanceMode::class, CheckPrinterAvailable::class])
        ->name('v1.files.store');
    Route::get('/files/{file_id}', [FileController::class, 'show'])->name('v1.files.show');
    Route::get('/files/{file_id}/preview', [FileController::class, 'preview'])->name('v1.files.preview');

    // Price calculation
    Route::post('/print/price', [PrintPriceController::class, 'calculate'])->name('v1.print.price');

    // Print Job Submission & Tracking
    Route::post('/print/jobs', [PrintJobController::class, 'store'])
        ->middleware([CheckMaintenanceMode::class, CheckPrinterAvailable::class])
        ->name('v1.print.jobs.store');
    Route::get('/print/jobs/{job_id}', [PrintJobController::class, 'show'])->name('v1.print.jobs.show');

    // Payment Status for Kiosk
    Route::get('/payment/{session_id}', [PaymentController::class, 'show'])->name('v1.payment.show');

    // ==========================================
    // Hardware Agent / Webhook Endpoints
    // ==========================================
    Route::prefix('agent')->name('v1.agent.')->group(function () {
        Route::get('/jobs/pending', [AgentJobController::class, 'pending'])->name('jobs.pending');
        Route::put('/jobs/{id}/status', [AgentJobController::class, 'updateStatus'])->name('jobs.update-status');
        Route::post('/payment/confirm', [AgentPaymentController::class, 'confirm'])->name('payment.confirm');
    });

    // ==========================================
    // Admin Endpoints
    // ==========================================
    Route::prefix('admin')->name('v1.admin.')->group(function () {
        // Public Admin Auth
        Route::post('/login', [AdminAuthController::class, 'login'])->name('login');

        // Authenticated Admin Routes
        Route::middleware(['auth:sanctum', AdminOnly::class])->group(function () {
            Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

            // Dashboard
            Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

            // Transactions
            Route::get('/transactions', [AdminTransactionController::class, 'index'])->name('transactions.index');
            Route::get('/transactions/{id}', [AdminTransactionController::class, 'show'])->name('transactions.show');

            // Pricing Configuration
            Route::get('/pricing', [AdminPricingController::class, 'index'])->name('pricing.index');
            Route::put('/pricing', [AdminPricingController::class, 'update'])->name('pricing.update');

            // Maintenance Mode
            Route::get('/maintenance', [AdminMaintenanceController::class, 'index'])->name('maintenance.index');
            Route::put('/maintenance', [AdminMaintenanceController::class, 'update'])->name('maintenance.update');

            // Announcements Management
            Route::get('/announcements', [AdminAnnouncementController::class, 'index'])->name('announcements.index');
            Route::post('/announcements', [AdminAnnouncementController::class, 'store'])->name('announcements.store');
            Route::put('/announcements/{id}', [AdminAnnouncementController::class, 'update'])->name('announcements.update');
            Route::delete('/announcements/{id}', [AdminAnnouncementController::class, 'destroy'])->name('announcements.destroy');
        });
    });
});
