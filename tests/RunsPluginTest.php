<?php

declare(strict_types=1);

namespace Crab\Runs\Tests;

use Crab\App;
use Crab\Config\Config;
use Crab\Console\Output;
use Crab\Contracts\Runs\Runs as RunsContract;
use Crab\Database\DatabasePlugin;
use Crab\Engine\Async\Cancellation;
use Crab\Queue\QueuePlugin;
use Crab\Runs\ResumeRun;
use Crab\Runs\Runs;
use Crab\Runs\RunsPlugin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RunsPluginTest extends TestCase
{
    public function testReplacingTheContractReplacesItsAliasCommandsAndResumptionJobs(): void
    {
        $app = new App(new Config([
            'database' => ['connections' => ['main' => ['driver' => 'sqlite', 'database' => ':memory:']]],
            'queue' => ['store' => 'memory'],
        ]));
        $app->register(DatabasePlugin::class, QueuePlugin::class, RunsPlugin::class);
        $original = $app->container->get(RunsContract::class);
        self::assertSame($original, $app->container->get(Runs::class));
        $replacement = $this->createMock(RunsContract::class);
        $replacement->expects(self::once())->method('latest')->with(null, 50)->willReturn([]);
        $replacement->expects(self::once())->method('retry')->with(12)->willReturn(true);
        $replacement->expects(self::once())->method('recover')->willReturn(1);
        $cancellation = new Cancellation();
        $replacement->expects(self::once())->method('resume')->with(12, $cancellation, 'failed-claim');
        $app->container->instance(RunsContract::class, $replacement);

        self::assertSame($replacement, $app->container->get(Runs::class));
        $app->container->get(ResumeRun::class)->handle(12, $cancellation, 'failed-claim');
        $stream = fopen('php://memory', 'w+');
        try {
            foreach ([['runs:list'], ['runs:retry', '12'], ['runs:recover']] as $arguments) {
                self::assertSame(0, $app->console->run($arguments, new Output($stream, $stream)));
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function invalidSettings(): iterable
    {
        yield 'table' => ['table', ''];
        yield 'steps' => ['steps', null];
        yield 'database' => ['database', false];
        yield 'queue' => ['queue', []];
        yield 'tries' => ['tries', 0];
        yield 'backoff' => ['backoff', [-1]];
        yield 'lease' => ['lease', 2];
        yield 'unknown' => ['leases', 90];
    }

    #[DataProvider('invalidSettings')]
    public function testTheServiceChecksEachSetting(string $key, mixed $value): void
    {
        $app = new App(new Config(['runs' => [$key => $value]]));
        $app->register(RunsPlugin::class);
        $failure = null;
        try {
            $app->container->get(RunsContract::class);
        } catch (\Crab\Contracts\Config\InvalidSettings $error) {
            $failure = $error;
        }

        self::assertNotNull($failure);
        self::assertStringContainsString('runs.' . $key, $failure->getMessage());
    }
}
