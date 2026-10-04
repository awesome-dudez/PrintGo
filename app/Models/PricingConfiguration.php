<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $name
 * @property string $item_key
 * @property int $price_in_cents
 * @property string $price
 * @property string $currency
 * @property bool $is_active
 * @property string|null $description
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class PricingConfiguration extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'item_key',
        'price_in_cents',
        'price',
        'currency',
        'is_active',
        'description',
        'updated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_in_cents' => 'integer',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'updated_by' => 'integer',
        ];
    }

    /**
     * Scope a query to only include active pricing configurations.
     *
     * @param  Builder<PricingConfiguration>  $query
     * @return Builder<PricingConfiguration>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the user that updated this pricing configuration.
     *
     * @return BelongsTo<User, $this>
     */
    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Default pricing matrix dictionary.
     *
     * @return array<string, float>
     */
    public static function getDefaultPricing(): array
    {
        return [
            'bw_a4' => 2.00,
            'color_a4' => 5.00,
            'bw_letter' => 2.00,
            'color_letter' => 5.00,
            'bw_legal' => 3.00,
            'color_legal' => 7.00,
            'size_a4' => 0.00,
            'size_letter' => 0.00,
            'size_legal' => 1.00,
            'paper_bond' => 0.00,
            'paper_glossy' => 5.00,
            'paper_matte' => 3.00,
            'paper_photo' => 10.00,
            'duplex_discount' => 0.50,
        ];
    }

    /**
     * Get all active pricing items merged with defaults.
     *
     * @return array<string, float>
     */
    public static function getAllPricing(): array
    {
        return Cache::remember('all_pricing_config', 300, function () {
            $defaults = static::getDefaultPricing();
            $dbItems = static::query()->pluck('price', 'item_key')->toArray();

            $formatted = [];
            foreach ($dbItems as $k => $v) {
                $formatted[$k] = (float) $v;
            }

            return array_merge($defaults, $formatted);
        });
    }

    /**
     * Update or insert pricing keys and refresh cache.
     *
     * @param array<string, float|int|string> $prices
     */
    public static function updatePricing(array $prices): void
    {
        foreach ($prices as $key => $value) {
            $floatVal = (float) $value;
            $cents = (int) round($floatVal * 100);

            static::query()->updateOrCreate(
                ['item_key' => $key],
                [
                    'name' => ucwords(str_replace('_', ' ', $key)),
                    'price' => $floatVal,
                    'price_in_cents' => $cents,
                    'currency' => 'PHP',
                    'is_active' => true,
                ]
            );
        }

        Cache::forget('all_pricing_config');
    }
}
