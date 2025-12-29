<?php

namespace Phpactor\AmpFsWatch\Watcher\PhpPollWatcher;

use DateTimeImmutable;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\ModifiedFileQueue;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherConfig;
use Phpactor\AmpFsWatch\WatcherProcess;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Path;

use function Amp\async;
use function Amp\delay;

class PhpPollWatcher implements Watcher, WatcherProcess
{
    private LoggerInterface $logger;

    private DateTimeImmutable $lastUpdate;

    private WatcherConfig $config;

    private ModifiedFileQueue $queue;

    private bool $running;

    public function __construct(
        WatcherConfig $config,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?: new NullLogger();
        $this->queue = new ModifiedFileQueue();
        $this->config = $config;
    }

    public function watch(): WatcherProcess
    {
        $this->logger->info(sprintf(
            'Polling at interval of "%s" milliseconds for changes paths "%s"',
            $this->config->pollInterval(),
            implode('", "', $this->config->paths())
        ));

        $this->updateDateReference();
        $this->running = true;

        async(function (): void {
            while ($this->running) {
                $start = microtime(true);
                $searches = [];

                foreach ($this->config->paths() as $path) {
                    $this->search($path);
                }

                $this->logger->debug(sprintf(
                    'pid: %s PHP watcher scanned paths "%s" in %s seconds',
                    getmypid(),
                    implode('", "', $this->config->paths()),
                    number_format(microtime(true) - $start, 2)
                ));

                $this->updateDateReference();

                delay($this->config->pollInterval() / 1000);
            }
        });

        return $this;
    }

    public function wait(): ?ModifiedFile
    {
        while ($this->running) {
            $this->queue = $this->queue->compress();

            if ($next = $this->queue->dequeue()) {
                return $next;
            }

            delay($this->config->pollInterval() / 2000);
        }

        return null;
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function isSupported(): bool
    {
        return true;
    }


    public function describe(): string
    {
        return 'php-poll';
    }

    private function search(string $path): void
    {
        $files = scandir($path);
        foreach ((array)$files as $file) {
            if (false === $file || $file === '.' || $file === '..') {
                continue;
            }
            $filePath = Path::join($path, $file);
            clearstatcache();
            $mtime = filectime($filePath);
            $isDir = is_dir($filePath);


            // we are only accurate to seconds, so accept also
            // if mtime is the same as current timestamp
            if ($mtime >= $this->lastUpdate->format('U')) {
                $this->queue->enqueue(
                    new ModifiedFile($filePath, $isDir ? ModifiedFile::TYPE_FOLDER : ModifiedFile::TYPE_FILE)
                );
            }

            if ($isDir) {
                $this->search($filePath);
            }
        }
    }


    private function updateDateReference(): void
    {
        $this->lastUpdate = new DateTimeImmutable();
    }
}
