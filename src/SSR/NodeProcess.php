<?php

declare(strict_types=1);

namespace Akqa\SilverStripe\SSR;

use RuntimeException;
use SilverStripe\Core\Injector\Injectable;

/**
 * Runs a command with stdin and captures stdout/stderr, with a timeout.
 *
 * Used to invoke a Vite-built React SSR bundle via Node.js.
 */
class NodeProcess
{
    use Injectable;

    /**
     * @param list<string> $command
     */
    public function run(array $command, string $stdin, int $timeoutMs, ?string $cwd = null): NodeProcessResult
    {
        if (!function_exists('proc_open')) {
            throw new RuntimeException(
                'proc_open is disabled; React SSR requires it to invoke Node.js'
            );
        }

        if ($command === []) {
            throw new RuntimeException('SSR process command cannot be empty');
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $cwd);

        if (!is_resource($process)) {
            throw new RuntimeException('Failed to start SSR process: ' . implode(' ', $command));
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        fwrite($pipes[0], $stdin);
        fclose($pipes[0]);
        unset($pipes[0]);

        $stdout = '';
        $stderr = '';
        $stdoutPipe = $pipes[1];
        $stderrPipe = $pipes[2];
        $deadline = microtime(true) + ($timeoutMs / 1000);
        $status = proc_get_status($process);

        try {
            while ($status['running']) {
                if (microtime(true) >= $deadline) {
                    $this->terminate($process);
                    throw new RuntimeException(sprintf(
                        'SSR process timed out after %dms',
                        $timeoutMs
                    ));
                }

                $read = [];
                if (is_resource($stdoutPipe)) {
                    $read[] = $stdoutPipe;
                }
                if (is_resource($stderrPipe)) {
                    $read[] = $stderrPipe;
                }

                if ($read !== []) {
                    $write = null;
                    $except = null;
                    $remaining = max(0.0, $deadline - microtime(true));
                    $sec = (int) $remaining;
                    $usec = (int) (($remaining - $sec) * 1_000_000);
                    stream_select($read, $write, $except, $sec, $usec);

                    foreach ($read as $pipe) {
                        $chunk = fread($pipe, 8192);
                        if ($chunk === false || $chunk === '') {
                            continue;
                        }
                        if ($pipe === $stdoutPipe) {
                            $stdout .= $chunk;
                        } else {
                            $stderr .= $chunk;
                        }
                    }
                }

                $status = proc_get_status($process);
            }

            $stdout .= $this->drain($stdoutPipe);
            $stderr .= $this->drain($stderrPipe);
        } finally {
            $this->closePipes($pipes);
            $exit = proc_close($process);
        }

        if ($exit === -1) {
            $exit = (int) $status['exitcode'];
        }

        return new NodeProcessResult($exit, $stdout, $stderr);
    }


    /**
     * @param resource $process
     */
    private function terminate($process): void
    {
        proc_terminate($process, 15);
        usleep(100000);
        $status = proc_get_status($process);
        if ($status['running']) {
            proc_terminate($process, 9);
        }
    }


    /**
     * @param resource|closed-resource|null $pipe
     */
    private function drain(mixed $pipe): string
    {
        if (!is_resource($pipe)) {
            return '';
        }

        $rest = stream_get_contents($pipe);

        return $rest === false ? '' : $rest;
    }


    /**
     * @param array<int, resource|closed-resource> $pipes
     */
    private function closePipes(array $pipes): void
    {
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
    }
}
