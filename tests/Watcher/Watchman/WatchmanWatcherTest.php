<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\Watchman;

use Phpactor\AmpFsWatch\SystemDetector\CommandDetector;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherConfig;
use Phpactor\AmpFsWatch\Watcher\Watchman\WatchmanWatcher;
use Phpactor\AmpFsWatcher\Tests\Watcher\WatcherTestCase;
use Prophecy\Prophecy\ObjectProphecy;

class WatchmanWatcherTest extends WatcherTestCase
{
    use \Prophecy\PhpUnit\ProphecyTrait;
    private const PLAN_DELAY = 100;

    private ObjectProphecy|CommandDetector $commandDetector;

    public function testIsSupported(): void
    {
        $watcher = $this->createWatcher(new WatcherConfig([]));
        self::assertTrue($watcher->isSupported());
    }

    protected function createWatcher(WatcherConfig $config): Watcher
    {
        $this->commandDetector = $this->prophesize(CommandDetector::class);
        $this->commandDetector->commandExists('watchman')->willReturn(true);

        return new WatchmanWatcher(
            $config->withPollInterval(100),
            $this->createLogger(),
            $this->commandDetector->reveal()
        );
    }
}
