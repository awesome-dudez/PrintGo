<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PricingConfiguration;
use Illuminate\Database\Seeder;

class PricingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $prices = [
            [
                'name' => 'Black & White Base Page',
                'item_key' => 'base_page_bw',
                'price_in_cents' => 200,
                'price' => 2.00,
                'currency' => 'PHP',
                'description' => 'Base price per page for black and white printing.',
                'is_active' => true,
            ],
            [
                'name' => 'Color Base Page',
                'item_key' => 'base_page_color',
                'price_in_cents' => 500,
                'price' => 5.00,
                'currency' => 'PHP',
                'description' => 'Base price per page for full color printing.',
                'is_active' => true,
            ],
            [
                'name' => 'Bond Paper',
                'item_key' => 'paper_type_bond',
                'price_in_cents' => 0,
                'price' => 0.00,
                'currency' => 'PHP',
                'description' => 'Standard 70-80gsm bond paper (no extra charge).',
                'is_active' => true,
            ],
            [
                'name' => 'Glossy Paper',
                'item_key' => 'paper_type_glossy',
                'price_in_cents' => 300,
                'price' => 3.00,
                'currency' => 'PHP',
                'description' => 'Glossy finish paper surcharge per page.',
                'is_active' => true,
            ],
            [
                'name' => 'Matte Paper',
                'item_key' => 'paper_type_matte',
                'price_in_cents' => 200,
                'price' => 2.00,
                'currency' => 'PHP',
                'description' => 'Matte specialty paper surcharge per page.',
                'is_active' => true,
            ],
            [
                'name' => 'Photo Paper',
                'item_key' => 'paper_type_photo',
                'price_in_cents' => 500,
                'price' => 5.00,
                'currency' => 'PHP',
                'description' => 'High quality photo paper surcharge per page.',
                'is_active' => true,
            ],
            [
                'name' => 'A4 Paper Size',
                'item_key' => 'paper_size_a4',
                'price_in_cents' => 0,
                'price' => 0.00,
                'currency' => 'PHP',
                'description' => 'Standard A4 paper size (210 x 297mm).',
                'is_active' => true,
            ],
            [
                'name' => 'Letter Paper Size',
                'item_key' => 'paper_size_letter',
                'price_in_cents' => 0,
                'price' => 0.00,
                'currency' => 'PHP',
                'description' => 'Short / Letter paper size (8.5 x 11 inches).',
                'is_active' => true,
            ],
            [
                'name' => 'Legal Paper Size',
                'item_key' => 'paper_size_legal',
                'price_in_cents' => 100,
                'price' => 1.00,
                'currency' => 'PHP',
                'description' => 'Long / Legal paper size surcharge (8.5 x 13/14 inches).',
                'is_active' => true,
            ],
            [
                'name' => 'Duplex Discount',
                'item_key' => 'duplex_discount',
                'price_in_cents' => 0,
                'price' => 0.00,
                'currency' => 'PHP',
                'description' => 'Discount applied per duplex side (if applicable).',
                'is_active' => true,
            ],
        ];

        foreach ($prices as $pricing) {
            PricingConfiguration::query()->updateOrCreate(
                ['item_key' => $pricing['item_key']],
                $pricing
            );
        }
    }
}
