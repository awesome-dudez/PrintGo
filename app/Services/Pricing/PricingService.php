<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\PricingConfiguration;
use App\Services\Printing\PrinterStatusService;
use InvalidArgumentException;

class PricingService
{
    private PrinterStatusService $printerStatusService;

    public function __construct(?PrinterStatusService $printerStatusService = null)
    {
        $this->printerStatusService = $printerStatusService ?? new PrinterStatusService();
    }

    /**
     * Calculate authoritative price for print job options.
     *
     * @param int $pages Total number of pages per copy (min 1)
     * @param int $copies Number of copies (min 1)
     * @param string $paperSize Paper size (e.g. 'A4', 'Letter', 'Legal')
     * @param string $paperType Paper type (e.g. 'bond', 'glossy', 'matte', 'photo')
     * @param string $colorMode Color mode ('bw' or 'color')
     * @param bool $duplex Whether duplex printing is requested
     * @return array<string, mixed>
     */
    public function calculatePrice(
        int $pages,
        int $copies = 1,
        string $paperSize = 'A4',
        string $paperType = 'bond',
        string $colorMode = 'bw',
        bool $duplex = false
    ): array {
        if ($pages < 1) {
            $pages = 1;
        }
        if ($copies < 1) {
            $copies = 1;
        }

        // Validate options against active printer capabilities
        $validation = $this->printerStatusService->validatePrintOptions(
            paperSize: $paperSize,
            paperType: $paperType,
            colorMode: $colorMode,
            duplex: $duplex
        );

        $prices = PricingConfiguration::getAllPricing();

        $sizeKey = strtolower(trim($paperSize));
        $colorKey = strtolower(trim($colorMode));
        $paperTypeKey = strtolower(trim($paperType));

        // 1. Base color mode price per page (standard A4)
        $basePerPage = $colorKey === 'color'
            ? (float) ($prices['color_a4'] ?? $prices['color'] ?? 5.00)
            : (float) ($prices['bw_a4'] ?? $prices['bw'] ?? 2.00);

        // 2. Paper size surcharge (e.g. Legal may have higher rate)
        $sizeSurchargeKey = "size_{$sizeKey}";
        $comboKey = "{$colorKey}_{$sizeKey}";
        if (isset($prices[$sizeSurchargeKey])) {
            $paperSizeSurcharge = (float) $prices[$sizeSurchargeKey];
        } elseif (isset($prices[$comboKey])) {
            $paperSizeSurcharge = max(0.0, (float) $prices[$comboKey] - $basePerPage);
        } else {
            $paperSizeSurcharge = ($sizeKey === 'legal') ? 1.00 : 0.00;
        }

        // 3. Paper type surcharge per page
        $paperTypeSurchargeKey = "paper_{$paperTypeKey}";
        $paperTypeSurcharge = isset($prices[$paperTypeSurchargeKey])
            ? (float) $prices[$paperTypeSurchargeKey]
            : 0.00;

        $costPerPage = $basePerPage + $paperSizeSurcharge + $paperTypeSurcharge;

        // 4. Duplex calculation & discount
        $duplexDiscountPerSheet = (float) ($prices['duplex_discount'] ?? 0.50);
        $duplexSheetsSavedPerCopy = $duplex ? (int) floor($pages / 2) : 0;
        $duplexDiscountPerCopy = $duplex ? round($duplexSheetsSavedPerCopy * $duplexDiscountPerSheet, 2) : 0.00;

        // 5. Per copy & Total calculations
        $rawPerCopy = ($costPerPage * $pages);
        $perCopySubtotal = max(0.00, round($rawPerCopy - $duplexDiscountPerCopy, 2));
        $subtotal = round($perCopySubtotal * $copies, 2);
        $total = round($subtotal, 2);

        return [
            'pages' => $pages,
            'copies' => $copies,
            'paper_size' => $paperSize,
            'paper_type' => $paperType,
            'color_mode' => $colorMode,
            'duplex' => $duplex,
            'breakdown' => [
                'base_per_page' => $basePerPage,
                'paper_type_surcharge' => $paperTypeSurcharge,
                'paper_size_surcharge' => $paperSizeSurcharge,
                'color_mode' => $colorMode,
                'cost_per_page' => $costPerPage,
                'duplex_discount_per_copy' => $duplexDiscountPerCopy,
                'per_copy_subtotal' => $perCopySubtotal,
                'subtotal' => $subtotal,
            ],
            'total' => $total,
            'currency' => 'PHP',
            'is_supported' => $validation['valid'],
            'validation_errors' => $validation['errors'],
        ];
    }

    /**
     * Backward-compatible alias matching previous calculate method signature.
     *
     * @param int $pageCount
     * @param int $copies
     * @param string $paperSize
     * @param string $paperType
     * @param string $colorMode
     * @param bool $duplex
     * @param array<string, mixed> $adjustments
     * @return array<string, mixed>
     */
    public function calculate(
        int $pageCount,
        int $copies = 1,
        string $paperSize = 'A4',
        string $paperType = 'bond',
        string $colorMode = 'bw',
        bool $duplex = false,
        array $adjustments = []
    ): array {
        $result = $this->calculatePrice(
            pages: $pageCount,
            copies: $copies,
            paperSize: $paperSize,
            paperType: $paperType,
            colorMode: $colorMode,
            duplex: $duplex
        );

        $result['total_pages'] = $pageCount * $copies;
        $result['base_price_per_page'] = $result['breakdown']['base_per_page'];
        $result['paper_type_fee_per_page'] = $result['breakdown']['paper_type_surcharge'];
        $result['cost_per_page'] = $result['breakdown']['cost_per_page'];
        $result['discount'] = round(($result['breakdown']['duplex_discount_per_copy'] ?? 0.0) * $copies, 2);
        $result['total_amount'] = $result['total'];

        return $result;
    }
}
