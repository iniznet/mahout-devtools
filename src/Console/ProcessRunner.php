<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Console;

use Iniznet\Mahout\Devtools\Exception\CommandFailed;

/**
 * Runs a child process with an extended environment, either streaming its output
 * or capturing it when the caller needs the child's answer rather than its noise.
 */
final class ProcessRunner
{
    /**
     * @param list<string>         $command
     * @param array<string,string> $environment
     */
    public function run(array $command, string $workingDirectory, array $environment = []): int
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => STDOUT,
            2 => STDERR,
        ];

        $process = proc_open(
            $command,
            $descriptors,
            $pipes,
            $workingDirectory,
            array_merge(getenv(), $environment),
        );

        if (!\is_resource($process)) {
            throw CommandFailed::couldNotStart(implode(' ', $command));
        }

        if (isset($pipes[0]) && \is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        return proc_close($process);
    }

    /**
     * Run a child process and return its combined output.
     *
     * @param list<string>         $command
     * @param array<string,string> $environment
     *
     * @return array{code: int, output: string}
     */
    public function capture(array $command, string $workingDirectory, array $environment = []): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            $command,
            $descriptors,
            $pipes,
            $workingDirectory,
            array_merge(getenv(), $environment),
        );

        if (!\is_resource($process)) {
            throw CommandFailed::couldNotStart(implode(' ', $command));
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'code' => $exitCode,
            'output' => (string) $stdout.(string) $stderr,
        ];
    }
}
