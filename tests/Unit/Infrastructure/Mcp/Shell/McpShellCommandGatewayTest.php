<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Mcp\Shell;

use App\Application\Logger\LoggerService;
use App\Infrastructure\Mcp\Shell\McpShellCommandGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(McpShellCommandGateway::class)]
#[CoversMethod(McpShellCommandGateway::class, 'run')]
final class McpShellCommandGatewayTest extends TestCase
{
    public function testSuccessfulCommandCapturesStdoutAndExitCode(): void
    {
        $result = $this->gateway()->run('printf hello');

        self::assertSame(0, $result->exitCode);
        self::assertFalse($result->timedOut);
        self::assertSame('hello', $result->output);
        self::assertSame('', $result->errorOutput);
    }

    public function testFailedCommandCapturesStderrAndExitCode(): void
    {
        $result = $this->gateway()->run('printf err >&2; exit 7');

        self::assertSame(7, $result->exitCode);
        self::assertFalse($result->timedOut);
        self::assertSame('err', $result->errorOutput);
    }

    public function testTimeoutKillsLongRunningCommand(): void
    {
        $result = $this->gateway(timeoutSeconds: 1)->run('sleep 10');

        self::assertTrue($result->timedOut);
        self::assertSame(-1, $result->exitCode);
        self::assertStringContainsString('таймауту', $result->errorOutput);
    }

    public function testOutputIsCapped(): void
    {
        $result = $this->gateway(maxOutputLength: 8)->run('printf abcdefghijklmnop');

        self::assertSame("abcdefgh\n… [вывод обрезан]", $result->output);
    }

    public function testBinaryOutputIsConvertedToUtf8(): void
    {
        $result = $this->gateway()->run('printf ' . escapeshellarg("ok\xFF\xFEbin"));

        self::assertSame(0, $result->exitCode);
        self::assertTrue(mb_check_encoding($result->output, 'UTF-8'));
        self::assertStringStartsWith('ok', $result->output);
        self::assertStringContainsString('bin', $result->output);
    }

    public function testWorkingDirectoryIsApplied(): void
    {
        $result = $this->gateway(workingDirectory: '/tmp')->run('pwd');

        self::assertSame(0, $result->exitCode);
        self::assertSame('/tmp', trim($result->output));
    }

    private function gateway(
        int $timeoutSeconds = 5,
        string $workingDirectory = '',
        int $maxOutputLength = 8000,
    ): McpShellCommandGateway {
        return new McpShellCommandGateway(
            $this->createStub(LoggerService::class),
            $timeoutSeconds,
            $workingDirectory,
            $maxOutputLength,
        );
    }
}
