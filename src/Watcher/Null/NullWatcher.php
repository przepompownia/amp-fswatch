<?php

namespace Phpactor\AmpFsWatch\Watcher\Null;

use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\WatcherProcess;
use Phpactor\AmpFsWatch\Watcher;

class NullWatcher implements Watcher, WatcherProcess
{
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
        return null;
    }


    public function describe(): string
    {
        return 'null';
    }
}
