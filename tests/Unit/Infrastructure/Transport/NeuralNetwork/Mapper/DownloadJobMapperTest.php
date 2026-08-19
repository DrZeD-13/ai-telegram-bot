<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Infrastructure\Transport\NeuralNetwork\Mapper\DownloadJobMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(DownloadJobMapper::class)]
#[CoversMethod(DownloadJobMapper::class, 'map')]
final class DownloadJobMapperTest extends TestCase
{
    public function testMapReadsJobId(): void
    {
        $job = (new DownloadJobMapper())->map([
            'job_id' => 'job-1',
            'status' => 'queued',
        ]);

        self::assertSame('job-1', $job->jobId);
        self::assertSame('queued', $job->status);
    }
}
