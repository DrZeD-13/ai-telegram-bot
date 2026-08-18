<?php

declare(strict_types=1);

namespace App\Tests\System\Driver;

use JsonException;
use PHPUnit\Framework\Assert;
use Spatie\Snapshots\Driver;
use Spatie\Snapshots\Exceptions\CantBeSerialized;

class JsonPrettyDriver implements Driver
{
    /**
     * @throws CantBeSerialized
     * @throws JsonException
     */
    public function serialize(mixed $data): string
    {
        if (is_string($data)) {
            $data = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        }

        if (is_resource($data)) {
            throw new CantBeSerialized('Resources can not be serialized to json');
        }

        return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ."\n";
    }

    public function extension(): string
    {
        return 'json';
    }

    /**
     * @throws JsonException
     */
    public function match(mixed $expected, mixed $actual): void
    {
        if (is_string($actual)) {
            $actual = json_decode($actual, false, 512, JSON_THROW_ON_ERROR);
        }
        if (!is_string($expected)) {
            throw new JsonException('Expected snapshot payload must be a JSON string.');
        }
        $expected = json_decode($expected, false, 512, JSON_THROW_ON_ERROR);

        Assert::assertJsonStringEqualsJsonString(
            json_encode($expected, JSON_THROW_ON_ERROR),
            json_encode($actual, JSON_THROW_ON_ERROR)
        );
    }
}
