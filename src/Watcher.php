<?php

namespace Phpactor\AmpFsWatch;

interface Watcher
{
    public function watch(): WatcherProcess;

    public function isSupported(): bool;

    public function describe(): string;
}
