<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Pricing\PricingService as BasePricingService;

/**
 * Backward compatibility alias for PricingService.
 */
class PricingService extends BasePricingService
{
}
