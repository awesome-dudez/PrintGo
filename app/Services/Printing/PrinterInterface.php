<?php

declare(strict_types=1);

namespace App\Services\Printing;

use App\Models\PrintJob;

interface PrinterInterface
{
    /**
     * Get the current status of the printer.
     */
    public function getStatus(): PrinterStatusDto;

    /**
     * Get hardware and media capabilities supported by the active printer.
     *
     * @return array<string, mixed>
     */
    public function getCapabilities(): array;

    /**
     * Submit a print job to the physical printer or local print agent queue.
     */
    public function submitPrintJob(PrintJob $job): PrintResultDto;

    /**
     * Cancel an active or queued print job.
     */
    public function cancelPrintJob(string $jobId): bool;

    /**
     * Get execution status and progress details of a specific print job.
     *
     * @return array<string, mixed>
     */
    public function getPrintJobStatus(string $jobId): array;
}
