<?php

declare(strict_types=1);

namespace Akqa\SilverStripe\Tests\SSR;

use Akqa\SilverStripe\SSR\NodeProcess;
use Akqa\SilverStripe\SSR\NodeProcessResult;
use RuntimeException;
use SilverStripe\Dev\SapphireTest;

class NodeProcessTest extends SapphireTest
{
    public function testRunCapturesStdoutFromStdin(): void
    {
        $process = NodeProcess::create();
        $result = $process->run(
            [PHP_BINARY, '-r', 'echo stream_get_contents(STDIN);'],
            '{"title":"Hello"}',
            5000
        );

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('{"title":"Hello"}', $result->stdout);
        $this->assertSame('', $result->stderr);
    }


    public function testRunCapturesStderrAndExitCode(): void
    {
        $process = NodeProcess::create();
        $result = $process->run(
            [PHP_BINARY, '-r', 'fwrite(STDERR, "boom"); exit(2);'],
            '',
            5000
        );

        $this->assertFalse($result->isSuccessful());
        $this->assertSame(2, $result->exitCode);
        $this->assertSame('boom', $result->stderr);
    }


    public function testRunTimesOut(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SSR process timed out after 200ms');

        $process = NodeProcess::create();
        $process->run(
            [PHP_BINARY, '-r', 'sleep(2);'],
            '',
            200
        );
    }


    public function testRunRejectsEmptyCommand(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SSR process command cannot be empty');

        $process = NodeProcess::create();
        $process->run([], '', 1000);
    }


    public function testResultExposesOutput(): void
    {
        $result = new NodeProcessResult(0, '<p>ok</p>', '');

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('<p>ok</p>', $result->stdout);
        $this->assertSame('', $result->stderr);
    }
}
