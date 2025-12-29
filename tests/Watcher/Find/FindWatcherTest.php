<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\Find;

use Phpactor\AmpFsWatch\SystemDetector\CommandDetector;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherConfig;
use Phpactor\AmpFsWatch\Watcher\Find\FindWatcher;
use Phpactor\AmpFsWatcher\Tests\Watcher\WatcherTestCase;
use Prophecy\Prophecy\ObjectProphecy;

class FindWatcherTest extends WatcherTestCase
{
    use \Prophecy\PhpUnit\ProphecyTrait;
    private const PLAN_DELAY = 100;

    /**
     * @var ObjectProphecy<CommandDetector>
     */
    private ObjectProphecy $commandDetector;

    public function testRemoval(): void
    {
        $this->markTestSkipped('Not supported');
    }

    public function testIsSupported(): void
    {
        $watcher = $this->createWatcher(new WatcherConfig([]));
        self::assertTrue($watcher->isSupported());
    }

    public function testIsNotSupportedIfFindNotFound(): void
    {
        $watcher = $this->createWatcher(new WatcherConfig([]));
        $this->commandDetector->commandExists('find')->willReturn(false);
        self::assertFalse($watcher->isSupported());
    }

    protected function createWatcher(WatcherConfig $config): Watcher
    {
        $this->commandDetector = $this->prophesize(CommandDetector::class);
        $this->commandDetector->commandExists('find')->willReturn(true);

        return new FindWatcher(
            $config->withPollInterval(100),
            $this->createLogger(),
            $this->commandDetector->reveal()
        );
    }
}
