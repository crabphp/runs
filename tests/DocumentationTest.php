<?php

declare(strict_types=1);

namespace Crab\Runs\Tests;

use Crab\App;
use Crab\Contracts\Container\Container;
use Crab\Contracts\Plugin;
use Crab\Contracts\Runs\Runs;
use PHPUnit\Framework\TestCase;

final class ExampleSetup implements Plugin
{
    public function register(Container $container): void
    {
        $container->instance('example.runs-registered', $container->has(Runs::class));
    }
}

final class DocumentationTest extends TestCase
{
    public function testTheReadmeBuildsTheApplicationAndLoadsItsRunsSettings(): void
    {
        $readme = file_get_contents(__DIR__ . '/../README.md');
        preg_match_all('/\x60{3}php\n(.*?)\n\x60{3}/s', $readme, $matches);
        $samples = [];
        foreach ($matches[1] as $sample) {
            $samples[strtok($sample, "\n")] = $sample;
        }
        $directory = sys_get_temp_dir() . '/crab-runs-docs-' . bin2hex(random_bytes(6));
        mkdir($directory);
        mkdir($directory . '/config');
        try {
            file_put_contents($directory . '/config/runs.php', "<?php\n" . $samples['// config/runs.php']);
            file_put_contents($directory . '/config/database.php', <<<'PHP'
                <?php
                return ['connections' => ['main' => ['driver' => 'sqlite', 'database' => ':memory:']]];
                PHP);
            file_put_contents($directory . '/config/queue.php', "<?php\nreturn ['store' => 'memory'];");
            $sample = str_replace(
                'use App\Setup;',
                'use ' . ExampleSetup::class . ' as Setup;',
                $samples['// app.php'],
            );
            file_put_contents($directory . '/app.php', "<?php\n" . $sample);

            $app = require $directory . '/app.php';

            self::assertInstanceOf(App::class, $app);
            $app->config->check();
            self::assertTrue($app->container->get('example.runs-registered'));
            self::assertInstanceOf(Runs::class, $app->container->get(Runs::class));
            self::assertTrue($app->console->has('runs:install'));
            self::assertTrue($app->console->has('queue:install'));
            self::assertSame(5, $app->config->get('runs.tries'));
            self::assertSame([10, 60, 300], $app->config->get('runs.backoff'));
            self::assertSame(120, $app->config->get('runs.lease'));
        } finally {
            foreach (glob($directory . '/config/*') ?: [] as $file) {
                unlink($file);
            }
            foreach (glob($directory . '/*.php') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory . '/config');
            rmdir($directory);
        }
    }
}
