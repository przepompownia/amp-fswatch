<?php

namespace Phpactor\AmpFsWatch\Watcher\Watchman;

use Amp\Future;
use Amp\Pipeline\ConcurrentIterator;
use Amp\Pipeline\Pipeline;
use Amp\Process\Process;
use Amp\Process\ProcessException;
use Phpactor\AmpFsWatch\Exception\WatcherDied;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\SystemDetector\CommandDetector;
use Phpactor\AmpFsWatch\ModifiedFileBuilder;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\WatcherConfig;
use Phpactor\AmpFsWatch\WatcherProcess;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

use function Amp\ByteStream\buffer;
use function Amp\ByteStream\splitLines;
use function Amp\Future\awaitAny;
use function Amp\async;

class WatchmanWatcher implements Watcher, WatcherProcess
{
    const WATCHMAN_CMD = 'watchman';

    private LoggerInterface $logger;

    /**
     * @var Process[]
     */
    private array $subscribers = [];

    private CommandDetector $commandDetector;

    private WatcherConfig $config;

    /**
     * @var ConcurrentIterator<string>[]
     */
    private array $lineReaders = [];

    /**
     * @var array<int,Future<array{int, ?string}>>
     */
    private array $lineReaderFutures = [];

    /**
     * @var array<ModifiedFile>
     */
    private array $fileBuffer = [];

    public function __construct(
        WatcherConfig $config,
        ?LoggerInterface $logger = null,
        ?CommandDetector $commandDetector = null
    ) {
        $this->logger = $logger ?: new NullLogger();
        $this->commandDetector = $commandDetector ?: new CommandDetector();
        $this->config = $config;
    }

    public function watch(): WatcherProcess
    {
        $this->watchPaths();

        foreach ($this->config->paths() as $path) {
            $subscriber = $this->subscribe($path);
            $this->subscribers[] = $subscriber;
            $this->lineReaders[] = Pipeline::fromIterable(splitLines($subscriber->getStdout()))->getIterator();
        }

        return $this;
    }

    public function wait(): ?ModifiedFile
    {
        while (null !== $file = array_shift($this->fileBuffer)) {
            return $file;
        }

        while (null !== $line = $this->readLine()) {
            $notification = json_decode($line, true);

            if (false === $notification) {
                throw new RuntimeException(sprintf(
                    'Could not decode JSON from watchman: %s %s',
                    $line,
                    json_last_error_msg()
                ));
            }

            $files = array_map(function (array $file) use ($notification) {
                $modifiedFile = ModifiedFileBuilder::fromPathSegments(
                    $notification['root'],
                    $file['name']
                );
                if ($file['type'] === 'd') {
                    $modifiedFile = $modifiedFile->asFolder();
                }

                return $modifiedFile->build();
            }, $notification['files'] ?? []);

            if (empty($files)) {
                continue;
            }

            $file = array_shift($files);
            $this->fileBuffer = array_merge($this->fileBuffer, $files);

            return $file;
        };

        return null;
    }

    public function stop(): void
    {
        foreach ($this->subscribers as $subscriber) {
            try {
                $subscriber->signal(SIGTERM);
            } catch (ProcessException) {
            }
        }
    }

    public function isSupported(): bool
    {
        return $this->commandDetector->commandExists(self::WATCHMAN_CMD);
    }


    public function describe(): string
    {
        return 'watchman';
    }

    private function watchPaths(): void
    {
        foreach ($this->config->paths() as $path) {
            $process = Process::start([
                self::WATCHMAN_CMD,
                'watch',
                $path,
            ]);


            $this->logger->debug(sprintf('Watchman: %s', $process->getCommand()));
            $exit = $process->join();

            if ($exit !== 0) {
                throw new RuntimeException(sprintf(
                    'Watchman exited with code "%s": %s ',
                    $exit,
                    buffer($process->getStderr())
                ));
            }
        }
    }

    private function subscribe(string $path): Process
    {
        $process = Process::start([
            self::WATCHMAN_CMD,
            '-j',
            '-p',
            '--no-pretty',
        ]);
        $this->logger->debug(sprintf('Watchman: %s', $process->getCommand()));

        $payload = (string)json_encode([
            'subscribe',
            $path,
            'ampfs-watch',
            [
                'expression' => [
                    'allof',
                    [
                        'anyof',
                        ['type', 'f'],
                        ['type', 'd'],
                    ],
                    [
                        'since',
                        time(),
                        'ctime',
                    ],
                ],
                'fields' => [
                    'name','type',
                ],
            ],
        ]);
        $this->logger->debug(sprintf('Watchman: %s', $payload));
        $process->getStdin()->write($payload);

        if (!$process->isRunning()) {
            throw new WatcherDied(sprintf(
                'Could not start process: %s',
                $process->getCommand()
            ));
        }

        return $process;
    }

    private function readLine(): ?string
    {
        foreach ($this->lineReaders as $index => $lineReader) {
            if (array_key_exists((int)$index, $this->lineReaderFutures)) {
                continue;
            }

            $this->lineReaderFutures[(int)$index] = async(
                function (int $index, ConcurrentIterator $lineReader): array {
                    if (false === $lineReader->continue()) {
                        return [$index, null];
                    }
                    return [$index, $lineReader->getValue()];
                },
                $index,
                $lineReader,
            );
        }

        [$index, $line] = awaitAny($this->lineReaderFutures);
        unset($this->lineReaderFutures[(int)$index]);
        $this->logger->debug(print_r($line, true));

        if (null !== $line) {
            return $line;
        }

        foreach ($this->subscribers as $subscriber) {
            if ($subscriber->isRunning()) {
                continue;
            }
            $exitCode = $subscriber->join();

            // probably ran out of watchers, throw an error which can be
            // handled downstream.
            if ($exitCode === 1) {
                throw new WatcherDied(sprintf(
                    'Watchman subscriber exited with status code "%s": %s',
                    $exitCode,
                    buffer($subscriber->getStderr())
                ));
            }
        }

        return null;
    }
}
