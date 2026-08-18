<?php

declare(strict_types=1);

namespace App\Application\Logger;

use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Throwable;

class LogContextBuilder
{
    private static ?Serializer $normalizer = null;

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>
     */
    public static function makeExceptionContext(Throwable $exception, array $attributes = []): array
    {
        $attributes['message'] = $exception->getMessage();
        $attributes['exception'] = [
            'type' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'code' => $exception->getCode(),
        ];
        if ($exception instanceof HttpExceptionInterface) {
            $response = $exception->getResponse();
            $attributes['exception']['response'] = [
                'info' => $response->getInfo(),
                'status' => $response->getInfo('http_code') ?: 0,
            ];

            try {
                $attributes['exception']['response']['headers'] = $response->getHeaders(false);
                $attributes['exception']['response']['content'] = substr($response->getContent(false), 0, 1024) . '...';
            } catch (Throwable $e) {
                $attributes['exception']['response']['error'] = 'Ошибка получения тела ошибки: ' . $e->getMessage();
            }
        }

        return self::makeContext($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>
     */
    public static function makeContext(array $attributes = []): array
    {
        try {
            $normalized = self::getSerializer()->normalize($attributes);
            /** @var array<string, mixed> */
            return is_array($normalized) ? $normalized : [];
        } catch (Throwable $e) {
            return ['message' => $e->getMessage()];
        }
    }

    private static function getSerializer(): Serializer
    {
        if (self::$normalizer === null) {
            self::$normalizer = new Serializer([new ObjectNormalizer()]);
        }

        return self::$normalizer;
    }
}
