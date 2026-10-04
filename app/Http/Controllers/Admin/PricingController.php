<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePricingRequest;
use App\Models\AuditLog;
use App\Models\PricingConfiguration;
use Illuminate\Http\JsonResponse;

class PricingController extends Controller
{
    /**
     * Get the full pricing configuration matrix.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $pricing = PricingConfiguration::getAllPricing();

        return $this->successResponse(
            data: [
                'pricing' => $pricing,
                'currency' => 'PHP',
            ]
        );
    }

    /**
     * Update the pricing configuration matrix.
     *
     * @param UpdatePricingRequest $request
     * @return JsonResponse
     */
    public function update(UpdatePricingRequest $request): JsonResponse
    {
        $prices = $request->input('pricing', []);

        PricingConfiguration::updatePricing($prices);

        AuditLog::log('pricing_updated', [
            'updated_keys' => array_keys($prices),
            'pricing' => $prices,
        ]);

        $updated = PricingConfiguration::getAllPricing();

        return $this->successResponse(
            data: [
                'pricing' => $updated,
                'currency' => 'PHP',
            ],
            message: 'Pricing configuration updated successfully.'
        );
    }
}
