<?php

declare(strict_types=1);

namespace App\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Application\Dto\DownloadJob;
use App\Application\Exception\NeuralNetworkTransportException;

final readonly class DownloadJobMapper
{
    /**
     * @param array<string, mixed> $payload
     *
     * @throws NeuralNetworkTransportException
     */
    public function map(array $payload): DownloadJob
    {
        $jobId = $payload['job_id'] ?? $payload['jobId'] ?? $payload['id'] ?? null;
        if (!is_string($jobId) || $jobId === '') {
            throw new NeuralNetworkTransportException('Download job payload is missing a valid job id.');
        }

        return new DownloadJob(
            jobId: $jobId,
            status: $this->optionalString($payload, 'status'),
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws NeuralNetworkTransportException
     */
    private function optionalString(array $payload, string $key): ?string
    {
        if (!array_key_exists($key, $payload) || $payload[$key] === null) {
            return null;
        }

        if (!is_string($payload[$key])) {
            throw new NeuralNetworkTransportException(sprintf('Download job payload has a non-string %s.', $key));
        }

        return $payload[$key];
    }
}
