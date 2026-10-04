<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Document\DocumentProcessorFactory;
use App\Services\Payment\PaymentService;
use App\Services\Pricing\PricingService;
use App\Services\Printing\LocalPrintAgentAdapter;
use App\Services\Printing\PrinterInterface;
use App\Services\Printing\PrinterStatusService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DocumentProcessorFactory::class, function () {
            return new DocumentProcessorFactory();
        });

        $this->app->bind(PrinterInterface::class, LocalPrintAgentAdapter::class);

        $this->app->singleton(PrinterStatusService::class, function ($app) {
            return new PrinterStatusService($app->make(PrinterInterface::class));
        });

        $this->app->singleton(PricingService::class, function ($app) {
            return new PricingService($app->make(PrinterStatusService::class));
        });

        $this->app->singleton(PaymentService::class, function () {
            return new PaymentService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
