<?php

declare(strict_types=1);

namespace App\Application\Logger;

use JsonException;
use JsonSerializable;
use Psr\Log\LoggerInterface;
use Stringable;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

class LoggerService implements LoggerInterface
{
    /**
     * @param array<string, mixed> $defaultContext
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SerializerInterface $serializer,
        private array $defaultContext = [],
    ) {
    }

    public function addAdditionalContext(string $contextKey, string $value): void
    {
        $this->defaultContext[$contextKey] = $value;
    }

    public function deleteContextKey(string $key): void
    {
        unset($this->defaultContext[$key]);
    }

    private function prepareMessage(
        mixed $message,
    ): string {
        if (is_scalar($message)) {
            return (string) $message;
        }

        if (is_iterable($message)) {
            $messages = [];
            foreach ($message as $value) {
                $messages[] = $this->prepareMessage($value);
            }

            try {
                return json_encode($messages, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            } catch (JsonException $e) {
                return $this->getPreparingErrorMessage($message, $e);
            }
        }

        if ($message instanceof Throwable) {
            return $this->prepareMessage(LogContextBuilder::makeExceptionContext($message));
        }

        if ($message instanceof JsonSerializable) {
            try {
                return json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            } catch (JsonException $e) {
                return $this->getPreparingErrorMessage($message, $e);
            }
        }

        if ($message instanceof Stringable) {
            return (string) $message;
        }

        try {
            return $this->serializer->serialize($message, 'json');
        } catch (Throwable $e) {
            return $this->getPreparingErrorMessage($message, $e);
        }
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array{string, array<string, mixed>}
     */
    private function prepareArgs(mixed $message, array $context): array
    {
        $preparedContext = [];
        foreach ($context as $key => $value) {
            $preparedContext[$key] = $this->prepareMessage($value);
        }

        return [$this->prepareMessage($message), $this->defaultContext + $preparedContext];
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    protected function getPreparingErrorMessage(mixed $message, Throwable $e): string
    {
        return $this->prepareMessage(
            [
                'Не удалось подготовить сообщение:  ',
                is_object($message) ? $message::class : gettype($message),
                $this->prepareMessage($e),
            ],
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function emergency(string|Stringable $message, array $context = []): void
    {
        [$message, $context] = $this->prepareArgs($message, $context);
        $this->logger->emergency($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function alert(string|Stringable $message, array $context = []): void
    {
        [$message, $context] = $this->prepareArgs($message, $context);
        $this->logger->alert($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function critical(string|Stringable $message, array $context = []): void
    {
        [$message, $context] = $this->prepareArgs($message, $context);
        $this->logger->critical($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string|Stringable $message, array $context = []): void
    {
        [$message, $context] = $this->prepareArgs($message, $context);
        $this->logger->error($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string|Stringable $message, array $context = []): void
    {
        [$message, $context] = $this->prepareArgs($message, $context);
        $this->logger->warning($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function notice(string|Stringable $message, array $context = []): void
    {
        [$message, $context] = $this->prepareArgs($message, $context);
        $this->logger->notice($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string|Stringable $message, array $context = []): void
    {
        [$message, $context] = $this->prepareArgs($message, $context);
        $this->logger->info($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function debug(string|Stringable $message, array $context = []): void
    {
        [$message, $context] = $this->prepareArgs($message, $context);
        $this->logger->debug($message, $context);
    }

    /**
     * @param mixed $level
     * @param array<string, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        [$message, $context] = $this->prepareArgs($message, $context);
        $this->logger->log($level, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function logException(string $message, Throwable $exception, array $context = []): void
    {
        [$message, $context] = $this->prepareArgs($message, $context);
        $this->logger->error($message, LogContextBuilder::makeExceptionContext($exception, $context));
    }
}
