<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\PhpPollWatcher;

use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherConfig;
use Phpactor\AmpFsWatch\Watcher\PhpPollWatcher\PhpPollWatcher;
use Phpactor\AmpFsWatcher\Tests\Watcher\WatcherTestCase;

class PhpPollWatcherTest extends WatcherTestCase
{
    public function testIsSupported(): void
    {
        $watcher = $this->createWatcher(new WatcherConfig([]));
        self::assertTrue($watcher->isSupported());
    }

    public function testRemoval(): void
    {
        $this->markTestSkipped('Not supported');
    }

    protected function createWatcher(WatcherConfig $config): Watcher
    {
        return new PhpPollWatcher(
            $config,
            $this->createLogger()
        );
    }
}
