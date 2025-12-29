<?php

namespace Phpactor\AmpFsWatch\Watcher\Find;

use Amp\ByteStream\ReadableResourceStream;
use Amp\Pipeline\Pipeline;
use Amp\Process\Process;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\ModifiedFileQueue;
use Phpactor\AmpFsWatch\SystemDetector\CommandDetector;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherConfig;
use Phpactor\AmpFsWatch\WatcherProcess;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

use function Amp\ByteStream\splitLines;
use function Amp\async;
use function Amp\delay;

class FindWatcher implements Watcher, WatcherProcess
{
    private LoggerInterface $logger;

    private bool $running = true;

    private CommandDetector $commandDetector;

    private ModifiedFileQueue $queue;

    private WatcherConfig $config;

    private string $lastUpdateFile;

    public function __construct(
        WatcherConfig $config,
        ?LoggerInterface $logger = null,
        ?CommandDetector $commandDetector = null
    ) {
        $this->logger = $logger ?: new NullLogger();
        $this->commandDetector = $commandDetector ?: new CommandDetector();
        $this->queue = new ModifiedFileQueue();
        $this->config = $config;
        $this->lastUpdateFile = $config->lastUpdateReferenceFile() ?: $this->createTempFile();
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
            delay(.01);

            while ($this->running) {
                foreach ($this->config->paths() as $path) {
                    $this->search($path);
                }
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
        return $this->commandDetector->commandExists('find');
    }


    public function describe(): string
    {
        return 'find (BSD/GNU)';
    }

    private function search(string $path): void
    {
        $start = microtime(true);
        $process = $this->startProcess($path);

        $this->feedQueue($process->getStdout());

        $exitCode = $process->join();
        $stop = microtime(true);

        $this->logger->debug(sprintf(
            'pid:%s Find process "%s" done in %s seconds',
            getmypid(),
            $process->getCommand(),
            number_format($stop - $start, 2)
        ));

        if ($exitCode === 0) {
            return;
        }

        $stderr = $process->getStderr()->read();
        $this->logger->error(sprintf(
            'Process "%s" exited with error code %s: %s',
            $process->getCommand(),
            $exitCode,
            $stderr
        ));
    }

    private function feedQueue(ReadableResourceStream $stream): void
    {
        $reader = Pipeline::fromIterable(splitLines($stream))->getIterator();

        while (false !== $reader->continue()) {
            $line = $reader->getValue();
            $this->logger->debug('find found: ' . $line);
            $this->queue->enqueue(new ModifiedFile($line, is_file($line) ? ModifiedFile::TYPE_FILE : ModifiedFile::TYPE_FOLDER));
        }
    }

    private function startProcess(string $path): Process
    {
        // use ctime (inode status change time) rather than modification
        // time as vendor libraries (for example) preserve the modification
        // times.
        $process = Process::start([
            'find',
            $path,
            '-mindepth',
            '1',
            '-newercc',
            $this->lastUpdateFile,
        ]);

        if (!$process->isRunning()) {
            throw new RuntimeException(sprintf(
                'Could not start process: %s',
                $process->getCommand()
            ));
        }

        return $process;
    }

    private function updateDateReference(): void
    {
        touch($this->lastUpdateFile);
    }

    private function createTempFile(): string
    {
        $name = tempnam(sys_get_temp_dir(), 'amp-fs-watch');

        if (!$name) {
            throw new RuntimeException(sprintf(
                'Could not create temporary file "%s"',
                $name
            ));
        }

        return $name;
    }
}
