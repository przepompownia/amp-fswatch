<?php

namespace Phpactor\AmpFsWatch\Watcher\TestWatcher;

use Exception;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\ModifiedFileQueue;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherProcess;

use function Amp\delay;

class TestWatcher implements Watcher, WatcherProcess
{
    private ModifiedFileQueue $queue;

    private int $delay;

    private ?Exception $error;

    public function __construct(ModifiedFileQueue $queue, int $delay = 0, ?Exception $error = null)
    {
        $this->queue = $queue;
        $this->delay = $delay;
        $this->error = $error;
    }

    public function watch(): WatcherProcess
    {
        return $this;
    }

    public function isSupported(): bool
    {
        return true;
    }

    public function stop(): void
    {
    }

    public function wait(): ?ModifiedFile
    {
        if ($this->delay) {
            delay($this->delay / 1000);
        }

        if ($this->error) {
            throw $this->error;
        }

        while (null !== $file = $this->queue->dequeue()) {
            return $file;
        }

        return null;
    }

    public function describe(): string
    {
        return 'test';
    }
}
