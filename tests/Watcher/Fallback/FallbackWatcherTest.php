<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\Fallback;

use Amp\PHPUnit\AsyncTestCase;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\Watcher\Fallback\FallbackWatcher;
use Phpactor\AmpFsWatch\Watcher\Null\NullWatcher;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;

class FallbackWatcherTest extends AsyncTestCase
{
    use \Prophecy\PhpUnit\ProphecyTrait;

    private ObjectProphecy|LoggerInterface $logger;

    private ObjectProphecy $watcher1;

    private ObjectProphecy $watcher2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->prophesize(LoggerInterface::class);
        $this->watcher1 = $this->prophesize(Watcher::class);
        $this->watcher1->describe()->willReturn('watcher1');
        $this->watcher2 = $this->prophesize(Watcher::class);
        $this->watcher2->describe()->willReturn('watcher2');
    }

    public function testNameIsUnknownWhenCalledBeforeInitialization(): void
    {
        $watcher = $this->createWatcher([
            $this->watcher1->reveal(),
            $this->watcher2->reveal(),
        ]);
        self::assertEquals('unknown (pending invocation)', $watcher->describe());
    }

    public function testUsesFirstSupportedWatcher(): void
    {
        $this->watcher1->isSupported()->willReturn(false);

        $callback = function (): void {
        };
        $paths = ['path1'];

        $nullWatcher = new NullWatcher();

        $watcher = $this->createWatcher([
            $this->watcher1->reveal(),
            $nullWatcher,
        ]);
        $process = $watcher->watch($paths, $callback);

        self::assertSame($nullWatcher, $process);
        self::assertEquals('null', $watcher->describe());
    }

    public function testReturnsNullWatcherAndLogsWarningIfNoSupportedWatchers(): void
    {
        $this->watcher1->isSupported()->willReturn(false);
        $this->watcher2->isSupported()->willReturn(false);

        $callback = function (): void {
        };
        $paths = ['path1'];

        $process = $this->createWatcher([
            $this->watcher1->reveal(),
            $this->watcher2->reveal(),
        ])->watch($paths, $callback);

        $this->logger->warning(Argument::containingString('No supported watchers'))->shouldHaveBeenCalled();

        self::assertInstanceOf(NullWatcher::class, $process);
    }

    public function testIsAlwaysSupported(): void
    {
        $watcher = $this->createWatcher([]);
        self::assertTrue($watcher->isSupported());
    }

    private function createWatcher(array $watchers): Watcher
    {
        return new FallbackWatcher(
            $watchers,
            $this->logger->reveal()
        );
    }
}
