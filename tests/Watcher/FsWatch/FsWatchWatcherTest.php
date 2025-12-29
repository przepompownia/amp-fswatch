<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\FsWatch;

use Phpactor\AmpFsWatch\SystemDetector\CommandDetector;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherConfig;
use Phpactor\AmpFsWatch\Watcher\FsWatch\FsWatchWatcher;
use Phpactor\AmpFsWatcher\Tests\Watcher\WatcherTestCase;
use Prophecy\Prophecy\ObjectProphecy;

class FsWatchWatcherTest extends WatcherTestCase
{
    use \Prophecy\PhpUnit\ProphecyTrait;

    private ObjectProphecy $commandDetector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->commandDetector = $this->prophesize(CommandDetector::class);
        $this->commandDetector->commandExists('fswatch')->willReturn(true);
    }

    public function testIsSupported(): void
    {
        $watcher = $this->createWatcher(new WatcherConfig([]));
        $this->commandDetector->commandExists('fswatch')->willReturn(true);

        self::assertTrue($watcher->isSupported());
    }

    public function testNotSupportedIfCommandNotFound(): void
    {
        $watcher = $this->createWatcher(new WatcherConfig([]));
        $this->commandDetector->commandExists('fswatch')->willReturn(false);
        self::assertFalse($watcher->isSupported());
    }

    protected function createWatcher(WatcherConfig $config): Watcher
    {
        return new FsWatchWatcher(
            $config,
            $this->createLogger(),
            $this->commandDetector->reveal()
        );
    }
}
