<?php

declare(strict_types=1);

namespace App\Services\Printing;

use JsonSerializable;

readonly class PrintResultDto implements JsonSerializable
{
    /**
     * @param bool $success Whether the job submission was successful
     * @param string $jobId Unique ID of the print job
     * @param string|null $message Success or status message
     * @param string|null $error Error message if submission failed
     * @param array<string, mixed>|null $details Additional metadata
     */
    public function __construct(
        public bool $success,
        public string $jobId,
        public ?string $message = null,
        public ?string $error = null,
        public ?array $details = null,
    ) {}

    /**
     * Create a successful print result.
     *
     * @param array<string, mixed>|null $details
     */
    public static function success(string $jobId, ?string $message = 'Print job queued successfully', ?array $details = null): self
    {
        return new self(
            success: true,
            jobId: $jobId,
            message: $message,
            error: null,
            details: $details,
        );
    }

    /**
     * Create a failed print result.
     *
     * @param array<string, mixed>|null $details
     */
    public static function failure(string $jobId, string $error, ?array $details = null): self
    {
        return new self(
            success: false,
            jobId: $jobId,
            message: null,
            error: $error,
            details: $details,
        );
    }

    /**
     * Convert DTO to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'jobId' => $this->jobId,
            'job_id' => $this->jobId,
            'message' => $this->message,
            'error' => $this->error,
            'details' => $this->details,
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
