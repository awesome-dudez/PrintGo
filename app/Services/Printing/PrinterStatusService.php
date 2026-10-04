<?php

declare(strict_types=1);

namespace App\Services\Printing;

use App\Models\Printer;
use Illuminate\Support\Facades\Cache;

class PrinterStatusService
{
    private PrinterInterface $printer;

    public function __construct(?PrinterInterface $printer = null)
    {
        $this->printer = $printer ?? new LocalPrintAgentAdapter();
    }

    /**
     * Get the strongly-typed printer status DTO.
     */
    public function getStatus(): PrinterStatusDto
    {
        return $this->printer->getStatus();
    }

    /**
     * Get the printer status as an associative array.
     *
     * @return array<string, mixed>
     */
    public function getStatusArray(): array
    {
        return $this->getStatus()->toArray();
    }

    /**
     * Get capabilities of the active printer.
     *
     * @return array<string, mixed>
     */
    public function getCapabilities(): array
    {
        return $this->printer->getCapabilities();
    }

    /**
     * Determine if printer is currently available for processing new print jobs.
     */
    public function isAvailable(): bool
    {
        $status = $this->getStatus();

        return $status->ready && in_array($status->status, ['online', 'busy', 'printing'], true);
    }

    /**
     * Validate whether a given combination of print options is supported by the active printer.
     *
     * @return array{valid: bool, errors: list<string>}
     */
    public function validatePrintOptions(
        string $paperSize,
        string $paperType,
        string $colorMode,
        bool $duplex = false
    ): array {
        $capabilities = $this->getCapabilities();
        $errors = [];

        $supportedSizes = array_map('strtolower', $capabilities['paper_sizes'] ?? ['a4', 'letter', 'legal']);
        $supportedTypes = array_map('strtolower', $capabilities['paper_types'] ?? ['bond', 'glossy', 'matte', 'photo']);
        $supportedColorModes = array_map('strtolower', $capabilities['color_modes'] ?? ['bw', 'color']);
        $duplexSupported = (bool) ($capabilities['duplex_supported'] ?? $capabilities['duplex'] ?? true);

        if (!in_array(strtolower($paperSize), $supportedSizes, true)) {
            $errors[] = sprintf('Paper size "%s" is not supported. Supported sizes: %s', $paperSize, implode(', ', (array) ($capabilities['paper_sizes'] ?? [])));
        }

        if (!in_array(strtolower($paperType), $supportedTypes, true)) {
            $errors[] = sprintf('Paper type "%s" is not supported. Supported types: %s', $paperType, implode(', ', (array) ($capabilities['paper_types'] ?? [])));
        }

        if (!in_array(strtolower($colorMode), $supportedColorModes, true)) {
            $errors[] = sprintf('Color mode "%s" is not supported. Supported modes: %s', $colorMode, implode(', ', (array) ($capabilities['color_modes'] ?? [])));
        }

        if ($duplex && !$duplexSupported) {
            $errors[] = 'Duplex (two-sided) printing is not supported on this printer.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Update printer hardware status (e.g. from agent heartbeat or sensor ping).
     *
     * @param array<string, mixed> $statusData
     */
    public function updateStatus(array $statusData): void
    {
        $printer = Printer::query()->where('is_active', true)->first();

        if ($printer !== null) {
            $update = [];
            if (isset($statusData['status'])) {
                $update['status'] = (string) $statusData['status'];
            }
            if (isset($statusData['is_ready'])) {
                $update['is_ready'] = (bool) $statusData['is_ready'];
            }
            if (array_key_exists('error_message', $statusData)) {
                $update['error_message'] = $statusData['error_message'];
            }
            if (isset($statusData['paper_status'])) {
                $update['paper_status'] = (string) $statusData['paper_status'];
            }
            if (isset($statusData['ink_status'])) {
                $update['ink_status'] = $statusData['ink_status'];
            }
            if (!empty($update)) {
                $printer->update($update);
            }
        }

        $current = Cache::get(LocalPrintAgentAdapter::CACHE_KEY_HARDWARE_STATUS, []);
        $merged = array_merge($current, $statusData, ['last_checked_at' => now()->toIso8601String()]);
        Cache::put(LocalPrintAgentAdapter::CACHE_KEY_HARDWARE_STATUS, $merged, 300);
    }
}
