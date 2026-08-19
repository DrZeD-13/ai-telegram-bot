<?php

declare(strict_types=1);

namespace App\Application\Dto;

final readonly class DownloadStatus
{
    public function __construct(
        public string $jobId,
        public string $status,
    ) {
    }
}
