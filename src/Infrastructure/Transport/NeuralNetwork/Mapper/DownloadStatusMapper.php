<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Application\Dto\DownloadStatus;
use App\Application\Exception\NeuralNetworkTransportException;

final readonly class DownloadStatusMapper
{
    /**
     * @param array<string, mixed> $payload
     *
     * @throws NeuralNetworkTransportException
     */
    public function map(array $payload): DownloadStatus
    {
        $jobId = $payload['job_id'] ?? $payload['jobId'] ?? $payload['id'] ?? null;
        if (!is_string($jobId) || $jobId === '') {
            throw new NeuralNetworkTransportException('Download status payload is missing a valid job id.');
        }

        $status = $payload['status'] ?? null;
        if (!is_string($status) || $status === '') {
            throw new NeuralNetworkTransportException('Download status payload is missing a valid status.');
        }

        return new DownloadStatus(
            jobId: $jobId,
            status: $status,
        );
    }
}
