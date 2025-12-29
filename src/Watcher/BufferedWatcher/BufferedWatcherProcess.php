<?php

namespace Phpactor\AmpFsWatch\Watcher\BufferedWatcher;

use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\WatcherProcess;
use Throwable;
use function Amp\async;
use function Amp\delay;

class BufferedWatcherProcess implements WatcherProcess
{
    private WatcherProcess $innerProcess;

    /**
     * @var array<ModifiedFile>
     */
    private array $buffer = [];

    private bool $running = true;

    private int $interval;

    private ?Throwable $error = null;

    public function __construct(WatcherProcess $innerProcess, int $interval = 500)
    {
        $this->innerProcess = $innerProcess;
        $this->interval = $interval;

        async(function (): void {
            try {
                while (null !== $modifiedFile = $this->innerProcess->wait()) {
                    assert($modifiedFile instanceof ModifiedFile);
                    $this->buffer[$modifiedFile->path()] = $modifiedFile;
                }
            } catch (Throwable $error) {
                $this->error = $error;
            }
            $this->running = false;
        });
    }

    public function stop(): void
    {
        $this->running = false;
        $this->innerProcess->stop();
    }

    public function wait(): ?ModifiedFile
    {
        while ($this->running || !empty($this->buffer || null !== $this->error)) {
            if ($this->error) {
                $error = $this->error;
                $this->error = null;
                throw $error;
            }
            if ($this->buffer) {
                return array_shift($this->buffer);
            }
            delay($this->interval / 1000);
        }

        return null;
    }
}
