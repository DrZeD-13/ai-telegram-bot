<?php

declare(strict_types=1);

namespace App\Tests\System\Trait;

use App\Tests\System\Driver\JsonPrettyDriver;
use Spatie\Snapshots\Driver;
use Spatie\Snapshots\MatchesSnapshots;

trait JsonPrettyMatchesSnapshots
{
    use MatchesSnapshots;

    public function assertMatchesSnapshotJsonPretty(mixed $actual, ?Driver $driver = null): void
    {
        $this->assertMatchesSnapshot($actual, $driver ?? new JsonPrettyDriver());
    }
}
