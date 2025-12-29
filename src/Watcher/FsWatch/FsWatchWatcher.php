<?php

namespace Phpactor\AmpFsWatch\Watcher\FsWatch;

use Amp\Pipeline\Pipeline;
use Amp\Process\Process;
use Amp\Process\ProcessException;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\ModifiedFileQueue;
use Phpactor\AmpFsWatch\SystemDetector\CommandDetector;
use Phpactor\AmpFsWatch\ModifiedFileBuilder;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherConfig;
use Phpactor\AmpFsWatch\WatcherProcess;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

use function Amp\ByteStream\splitLines;
use function Amp\async;
use function Amp\delay;

class FsWatchWatcher implements Watcher, WatcherProcess
{
    private const CMD = 'fswatch';
    private const POLL_TIME = 1;

    private LoggerInterface $logger;

    private ?Process $process;

    private CommandDetector $commandDetector;

    private ModifiedFileQueue $queue;

    private bool $running;

    private WatcherConfig $config;

    public function __construct(
        WatcherConfig $config,
        ?LoggerInterface $logger = null,
        ?CommandDetector $commandDetector = null
    ) {
        $this->logger = $logger ?: new NullLogger();
        $this->commandDetector = $commandDetector ?: new CommandDetector();
        $this->queue = new ModifiedFileQueue();
        $this->config = $config;
    }


    public function watch(): WatcherProcess
    {
        $this->process = $this->startProcess();
        $this->running = true;
        $this->feedQueue($this->process);
        return $this;
    }

    public function wait(): ?ModifiedFile
    {
        while (false === $this->process->isRunning()) {
            delay(self::POLL_TIME / 1000);
        }

        while ($this->running) {
            $this->queue = $this->queue->compress();

            if ($next = $this->queue->dequeue()) {
                return $next;
            }

            delay(self::POLL_TIME / 1000);
        }

        return null;
    }

    public function stop(): void
    {
        if (null === $this->process) {
            throw new RuntimeException(
                'fs-watcher process was not started, cannot call stop()'
            );
        }
        $this->running = false;
        try {
            $this->process->signal(SIGTERM);
        } catch (ProcessException) {
        }
    }

    public function isSupported(): bool
    {
        return $this->commandDetector->commandExists(self::CMD);
    }


    public function describe(): string
    {
        return 'fs-watch';
    }

    private function startProcess(): Process
    {
        $process = Process::start(array_merge([
            self::CMD,
        ], $this->config->paths(), [
                '-r',
                '--event=Created',
                '--event=Updated',
                '--event=Removed',
            ]));

        $this->logger->debug(sprintf('Started "%s"', $process->getCommand()));

        if (!$process->isRunning()) {
            throw new RuntimeException(sprintf(
                'Could not start process: %s',
                $process->getCommand()
            ));
        }

        return $process;
    }

    private function feedQueue(Process $process): void
    {
        $reader = Pipeline::fromIterable(splitLines($process->getStdout()))
            ->getIterator();
        async(function () use ($reader): void {
            while (false !== $reader->continue()) {
                $line = $reader->getValue();
                $builder = ModifiedFileBuilder::fromPath($line);
                if (file_exists($line) && !is_file($line)) {
                    $builder->asFolder();
                }
                $this->queue->enqueue($builder->build());
            }
        });
    }
}
