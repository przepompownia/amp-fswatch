<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher;

use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherConfig;
use Phpactor\AmpFsWatch\WatcherProcess;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Phpactor\AmpFsWatcher\Tests\IntegrationTestCase;

use function Amp\async;
use function Amp\delay;

abstract class WatcherTestCase extends IntegrationTestCase
{
    const DELAY_MILLI = 30;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTimeout(5);
        $this->workspace()->reset();
    }

    public function testSingleFileChange(): void
    {
        $process = $this->startProcess();
        $this->delay();
        $this->workspace()->put('foobar', '');
        $this->delay();

        self::assertEquals(
            new ModifiedFile(
                $this->workspace()->path('foobar'),
                ModifiedFile::TYPE_FILE
            ),
            async(fn () => $process->wait())->await(),
        );

        $process->stop();
    }

    public function testSingleFileChangeWithModificationTimeInPast(): void
    {
        $process = $this->startProcess();
        $this->delay();
        touch($this->workspace()->path('foobar'), time() - 3600);
        $this->delay();

        self::assertEquals(
            new ModifiedFile(
                $this->workspace()->path('foobar'),
                ModifiedFile::TYPE_FILE
            ),
            async(fn () => $process->wait())->await(),
        );

        $process->stop();
    }

    public function testMultipleSameFile(): void
    {
        $process = $this->startProcess();

        $this->delay();
        $this->workspace()->put('foobar', '');
        $this->workspace()->put('foobar', 'foobar');
        $this->delay();

        self::assertEquals(
            new ModifiedFile(
                $this->workspace()->path('foobar'),
                ModifiedFile::TYPE_FILE
            ),
            async(fn () => $process->wait())->await(),
        );

        $process->stop();
    }

    public function testDirectory(): void
    {
        $process = $this->startProcess();

        $this->delay();
        $this->workspace()->mkdir('foobar');
        $this->delay();

        self::assertEquals(
            new ModifiedFile(
                $this->workspace()->path('foobar'),
                ModifiedFile::TYPE_FOLDER
            ),
            async(fn () => $process->wait())->await(),
        );

        $process->stop();
    }

    public function testRemoval(): void
    {
        $this->workspace()->put('foobar', '');

        $process = $this->startProcess();

        $this->delay();

        unlink($this->workspace()->path('foobar'));

        $this->delay();

        self::assertEquals(
            new ModifiedFile(
                $this->workspace()->path('foobar'),
                ModifiedFile::TYPE_FILE
            ),
            async(fn () => $process->wait())->await(),
        );

        $process->stop();
    }

    public function testMultiplePaths(): void
    {
        $this->workspace()->mkdir('foobar');
        $this->workspace()->mkdir('barfoo');

        $process = $this->startProcess([
            $this->workspace()->path('barfoo'),
            $this->workspace()->path('foobar'),
        ]);

        $this->delay();

        $this->workspace()->put('barfoo/foobar', '');
        $this->workspace()->put('foobar/barfoo', '');

        $this->delay();

        $files = [];
        for ($i = 0; $i < 2; $i++) {
            $file = async(fn () => $process->wait())->await();
            $files[$file->path()] = $file;
        }

        self::assertArrayHasKey($this->workspace()->path('barfoo/foobar'), $files);
        self::assertArrayHasKey($this->workspace()->path('foobar/barfoo'), $files);

        $process->stop();
    }

    public function testReturnsNameAsString(): void
    {
        self::assertIsString($this->createWatcher(new WatcherConfig([]))->describe());
    }

    abstract public function testIsSupported(): void;

    abstract protected function createWatcher(WatcherConfig $config): Watcher;

    protected function delay(): void
    {
        delay(self::DELAY_MILLI / 1000);
    }

    protected function createLogger(): LoggerInterface
    {
        return new class extends AbstractLogger {
            public function log($level, $message, array $context = []): void
            {
                if ($level === 'debug') {
                    return;
                }
                fwrite(STDERR, sprintf('[%s] [%s] %s', microtime(), $level, $message) . "\n");
            }
        };
    }

    /**
     * @param array<string> $paths
     */
    protected function startProcess(?array $paths = []): WatcherProcess
    {
        $paths = $paths ?: [ $this->workspace()->path() ];
        $watcher = $this->createWatcher(new WatcherConfig($paths));
        return $watcher->watch();
    }
}
