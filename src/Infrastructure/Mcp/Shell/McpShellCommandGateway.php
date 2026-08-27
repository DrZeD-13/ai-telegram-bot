<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp\Shell;

use App\Application\Dto\ShellCommandResult;
use App\Application\Logger\LoggerService;
use App\Application\Port\ShellCommandGateway;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * MCP-style shell tool: exposes host shell execution to the neural network agent.
 *
 * Commands run through `/bin/sh -c` with a hard timeout and captured stdout/stderr
 * so the model receives structured feedback for every invocation.
 */
#[AsAlias(ShellCommandGateway::class)]
final readonly class McpShellCommandGateway implements ShellCommandGateway
{
    private const int READ_CHUNK = 8192;

    public function __construct(
        private LoggerService $logger,
        #[Autowire('%env(int:MCP_SHELL_TIMEOUT)%')]
        private int $timeoutSeconds,
        #[Autowire('%env(MCP_SHELL_WORKDIR)%')]
        private string $workingDirectory,
        #[Autowire('%env(int:MCP_SHELL_MAX_OUTPUT)%')]
        private int $maxOutputLength,
    ) {
    }

    public function run(string $command): ShellCommandResult
    {
        $this->logger->info('MCP shell: выполняется команда', [
            'command' => $command,
            'timeout' => (string) $this->timeoutSeconds,
        ]);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $cwd = $this->workingDirectory !== '' ? $this->workingDirectory : null;
        $process = @proc_open(['/bin/sh', '-c', $command], $descriptors, $pipes, $cwd);

        if (!is_resource($process)) {
            return new ShellCommandResult(
                command: $command,
                output: '',
                errorOutput: 'Не удалось запустить процесс оболочки.',
                exitCode: -1,
            );
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $timedOut = false;
        $deadline = microtime(true) + (float) max(1, $this->timeoutSeconds);

        while (true) {
            $stdout .= $this->drain($pipes[1]);
            $stderr .= $this->drain($pipes[2]);

            $status = proc_get_status($process);
            if ($status['running'] === false) {
                break;
            }

            if (microtime(true) >= $deadline) {
                $timedOut = true;
                proc_terminate($process, 9);
                break;
            }

            usleep(50_000);
        }

        $stdout .= $this->drain($pipes[1]);
        $stderr .= $this->drain($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $status = proc_get_status($process);
        $exitCode = $status['running'] ? -1 : $status['exitcode'];
        proc_close($process);

        if ($timedOut) {
            $exitCode = -1;
            $stderr = trim($stderr . "\nКоманда прервана по таймауту ({$this->timeoutSeconds}s).");
        }

        return new ShellCommandResult(
            command: $command,
            output: $this->cap($stdout),
            errorOutput: $this->cap($stderr),
            exitCode: $exitCode,
            timedOut: $timedOut,
        );
    }

    /**
     * @param resource $pipe
     */
    private function drain($pipe): string
    {
        $buffer = '';
        while (($chunk = fread($pipe, self::READ_CHUNK)) !== false && $chunk !== '') {
            $buffer .= $chunk;
        }

        return $buffer;
    }

    private function cap(string $value): string
    {
        if ($this->maxOutputLength <= 0 || mb_strlen($value) <= $this->maxOutputLength) {
            return $value;
        }

        return mb_substr($value, 0, $this->maxOutputLength) . "\n… [вывод обрезан]";
    }
}
