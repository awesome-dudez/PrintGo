<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Printing\PrinterStatusService as BasePrinterStatusService;

/**
 * Backward compatibility alias for PrinterStatusService.
 */
class PrinterStatusService extends BasePrinterStatusService
{
}
