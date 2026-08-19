<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Transport\NeuralNetwork\Mapper;

use App\Infrastructure\Transport\NeuralNetwork\Mapper\DownloadStatusMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(DownloadStatusMapper::class)]
#[CoversMethod(DownloadStatusMapper::class, 'map')]
final class DownloadStatusMapperTest extends TestCase
{
    public function testMapReadsJobIdAndStatus(): void
    {
        $status = (new DownloadStatusMapper())->map([
            'job_id' => 'job-1',
            'status' => 'completed',
        ]);

        self::assertSame('job-1', $status->jobId);
        self::assertSame('completed', $status->status);
    }
}
