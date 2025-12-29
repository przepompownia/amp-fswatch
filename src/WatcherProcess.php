<?php

namespace Phpactor\AmpFsWatch;

interface WatcherProcess
{
    public function stop(): void;

    public function wait(): ?ModifiedFile;
}
