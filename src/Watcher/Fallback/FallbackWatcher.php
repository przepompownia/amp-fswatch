<?php

namespace Phpactor\AmpFsWatch\Watcher\Fallback;

use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherProcess;
use Phpactor\AmpFsWatch\Watcher\Null\NullWatcher;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class FallbackWatcher implements Watcher
{
    /**
     * @var array<Watcher>
     */
    private array $watchers;

    private LoggerInterface $logger;

    private ?string $lastWatcherName = null;

    /**
     * @param array<Watcher> $watchers
     */
    public function __construct(array $watchers, ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?: new NullLogger();
        foreach ($watchers as $watcher) {
            $this->add($watcher);
        }
    }

    public function watch(): WatcherProcess
    {
        $watcher = $this->resolveWatcher();
        $this->lastWatcherName = $watcher->describe();

        return $watcher->watch();
    }

    public function isSupported(): bool
    {
        return true;
    }


    public function describe(): string
    {
        if (null === $this->lastWatcherName) {
            return 'unknown (pending invocation)';
        }

        return $this->lastWatcherName;
    }

    private function add(Watcher $watcher): void
    {
        $this->watchers[] = $watcher;
    }

    private function resolveWatcher(): Watcher
    {
        $names = [];
        foreach ($this->watchers as $watcher) {
            if (!$watcher->isSupported()) {
                $names[] = $watcher->describe();
                continue;
            }

            return $watcher;
        }

        $this->logger->warning(sprintf(
            'No supported watchers, tried "%s".',
            implode('", "', $names)
        ));

        return new NullWatcher();
    }
}
