<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\Dto\ShellCommandResult;

interface ShellCommandGateway
{
    /**
     * Execute a shell command and capture its output and exit code.
     *
     * Command failures (non-zero exit codes, timeouts) are reported through the
     * result so the agent can reason about them; the method itself does not throw.
     */
    public function run(string $command): ShellCommandResult;
}
