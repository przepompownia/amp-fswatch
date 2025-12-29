<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\Inotify;

use Phpactor\AmpFsWatch\SystemDetector\CommandDetector;
use Phpactor\AmpFsWatch\SystemDetector\OsDetector;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherConfig;
use Phpactor\AmpFsWatch\Watcher\Inotify\InotifyWatcher;
use Phpactor\AmpFsWatcher\Tests\Watcher\WatcherTestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Filesystem\Path;

use function Amp\async;
use function Amp\delay;

class InotifyWatcherTest extends WatcherTestCase
{
    use \Prophecy\PhpUnit\ProphecyTrait;

    private ObjectProphecy $commandDetector;

    private ObjectProphecy $osValidator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->commandDetector = $this->prophesize(CommandDetector::class);
        $this->commandDetector->commandExists('inotifywait')->willReturn(true);
        $this->osValidator = $this->prophesize(OsDetector::class);
        $this->osValidator->isLinux()->willReturn(true);
    }

    public function testIsSupported(): void
    {
        $watcher = $this->createWatcher(new WatcherConfig([]));
        $this->commandDetector->commandExists('inotifywait')->willReturn(true);

        self::assertTrue($watcher->isSupported());
    }

    public function testNotSupportedOnNonLinux(): void
    {
        $watcher = $this->createWatcher(new WatcherConfig([]));
        $this->osValidator->isLinux()->willReturn(false);
        $this->commandDetector->commandExists('inotifywait')->willReturn(true);
        self::assertFalse($watcher->isSupported());
    }

    public function testNotSupportedIfCommandNotFound(): void
    {
        $watcher = $this->createWatcher(new WatcherConfig([]));
        $this->osValidator->isLinux()->willReturn(true);
        $this->commandDetector->commandExists('inotifywait')->willReturn(false);
        self::assertFalse($watcher->isSupported());
    }

    public function testMove(): void
    {
        $process = $this->startProcess();

        $this->delay();

        $this->workspace()->put('foobar/baz.php', 'content');
        $this->workspace()->put('foobar/bar.php', 'content');
        $this->workspace()->put('foobar/zog/bar.php', 'content');
        $this->workspace()->put('foobar/1.php', 'content');
        rename($this->workspace()->path('foobar'), $this->workspace()->path('barfoo'));

        $this->delay();
        $this->delay();

        $files = [];

        async(function () use (&$files, $process): void {
            while (null !== $file = $process->wait()) {
                $path = Path::makeRelative($file->path(), $this->workspace()->path());
                $files[$path] = true;
            }
        });

        delay(.01);
        $process->stop();

        self::assertArrayHasKey('barfoo/bar.php', $files);
        self::assertArrayHasKey('barfoo/baz.php', $files);
        self::assertArrayHasKey('barfoo/zog/bar.php', $files);

        $process->stop();
    }

    protected function createWatcher(WatcherConfig $config): Watcher
    {
        return new InotifyWatcher(
            $config,
            $this->createLogger(),
            $this->commandDetector->reveal(),
            $this->osValidator->reveal()
        );
    }
}
