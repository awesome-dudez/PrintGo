<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\PricingConfiguration;
use App\Services\Pricing\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    use RefreshDatabase;

    private PricingService $pricingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricingService = new PricingService();

        PricingConfiguration::updatePricing([
            'bw_a4' => 2.00,
            'color_a4' => 5.00,
            'bw_legal' => 3.00,
            'color_legal' => 7.00,
            'paper_bond' => 0.00,
            'paper_glossy' => 5.00,
            'paper_photo' => 10.00,
            'duplex_discount' => 0.50,
        ]);
    }

    public function test_calculates_single_copy_bw_a4_bond(): void
    {
        $result = $this->pricingService->calculatePrice(
            pages: 10,
            copies: 1,
            paperSize: 'A4',
            paperType: 'bond',
            colorMode: 'bw',
            duplex: false
        );

        $this->assertSame(10, $result['pages']);
        $this->assertSame(1, $result['copies']);
        $this->assertSame(2.00, $result['breakdown']['base_per_page']);
        $this->assertSame(0.00, $result['breakdown']['paper_type_surcharge']);
        $this->assertSame(20.00, $result['total']);
        $this->assertSame('PHP', $result['currency']);
    }

    public function test_calculates_color_glossy_with_copies(): void
    {
        $result = $this->pricingService->calculatePrice(
            pages: 4,
            copies: 3,
            paperSize: 'A4',
            paperType: 'glossy',
            colorMode: 'color',
            duplex: false
        );

        // Base 5.00 + Glossy 5.00 = 10.00 per page
        // 4 pages * 10.00 = 40.00 per copy
        // 3 copies = 120.00
        $this->assertSame(40.00, $result['breakdown']['per_copy_subtotal']);
        $this->assertSame(120.00, $result['total']);
    }

    public function test_calculates_duplex_discount(): void
    {
        $result = $this->pricingService->calculatePrice(
            pages: 10,
            copies: 1,
            paperSize: 'A4',
            paperType: 'bond',
            colorMode: 'bw',
            duplex: true
        );

        // 10 pages * 2.00 = 20.00
        // 10 pages duplex saves 5 sheets * 0.50 = 2.50 discount
        // Total = 17.50
        $this->assertSame(2.50, $result['breakdown']['duplex_discount_per_copy']);
        $this->assertSame(17.50, $result['total']);
    }

    public function test_calculates_legal_size_surcharge(): void
    {
        $result = $this->pricingService->calculatePrice(
            pages: 2,
            copies: 1,
            paperSize: 'Legal',
            paperType: 'bond',
            colorMode: 'bw',
            duplex: false
        );

        // Legal size base 3.00 (or 2.00 base + 1.00 legal surcharge) = 3.00 * 2 = 6.00
        $this->assertSame(6.00, $result['total']);
    }
}
