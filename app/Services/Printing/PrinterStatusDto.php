<?php

declare(strict_types=1);

namespace App\Services\Printing;

use JsonSerializable;

readonly class PrinterStatusDto implements JsonSerializable
{
    /**
     * @param string $status Current status: 'online', 'offline', 'busy', 'error', 'printing'
     * @param bool $ready Whether the printer is ready to accept new print jobs
     * @param string|null $error Error message if printer is in error state
     * @param array<string, mixed> $paper Paper tray status and paper level details
     * @param array<string, mixed>|null $ink Ink levels details
     * @param string|null $currentJob Currently active print job ID
     * @param string|null $lastCheckedAt ISO 8601 timestamp of status inquiry
     */
    public function __construct(
        public string $status,
        public bool $ready,
        public ?string $error = null,
        public array $paper = [],
        public ?array $ink = null,
        public ?string $currentJob = null,
        public ?string $lastCheckedAt = null,
    ) {}

    /**
     * Instantiate DTO from an array or database attributes.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $paperData = $data['paper'] ?? [
            'status' => $data['paper_status'] ?? 'available',
            'level' => $data['paper_level'] ?? 100,
            'supported_sizes' => ['A4', 'Letter', 'Legal'],
            'supported_types' => ['bond', 'glossy', 'matte', 'photo'],
        ];

        $inkData = $data['ink'] ?? $data['ink_status'] ?? [
            'level' => $data['ink_level'] ?? 100,
            'black' => 100,
            'cyan' => 100,
            'magenta' => 100,
            'yellow' => 100,
        ];

        $currentJob = null;
        if (isset($data['currentJob'])) {
            $currentJob = (string) $data['currentJob'];
        } elseif (isset($data['current_job_id'])) {
            $currentJob = $data['current_job_id'] ? (string) $data['current_job_id'] : null;
        } elseif (isset($data['current_job']) && is_array($data['current_job'])) {
            $currentJob = isset($data['current_job']['id']) ? (string) $data['current_job']['id'] : null;
        }

        return new self(
            status: (string) ($data['status'] ?? 'online'),
            ready: (bool) ($data['ready'] ?? $data['is_ready'] ?? true),
            error: isset($data['error']) ? (string) $data['error'] : ($data['error_message'] ?? null),
            paper: is_array($paperData) ? $paperData : ['status' => 'available', 'level' => 100],
            ink: is_array($inkData) ? $inkData : null,
            currentJob: $currentJob,
            lastCheckedAt: $data['last_checked_at'] ?? now()->toIso8601String(),
        );
    }

    /**
     * Convert DTO to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'ready' => $this->ready,
            'is_ready' => $this->ready,
            'error' => $this->error,
            'error_message' => $this->error,
            'paper' => $this->paper,
            'ink' => $this->ink,
            'currentJob' => $this->currentJob,
            'current_job_id' => $this->currentJob,
            'last_checked_at' => $this->lastCheckedAt ?? now()->toIso8601String(),
        ];
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
