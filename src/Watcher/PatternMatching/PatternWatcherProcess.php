<?php

namespace Phpactor\AmpFsWatch\Watcher\PatternMatching;

use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\WatcherProcess;

class PatternWatcherProcess implements WatcherProcess
{
    private WatcherProcess $process;

    /**
     * @var array<string>
     */
    private array $includePatterns;

    private PatternMatcher $matcher;

    /**
     * @var array<string>
     */
    private array $excludePatterns;

    /**
     * @param array<string> $includePatterns
     * @param array<string> $excludePatterns
     */
    public function __construct(WatcherProcess $process, array $includePatterns, array $excludePatterns, ?PatternMatcher $matcher = null)
    {
        $this->process = $process;
        $this->matcher = $matcher ?: new PatternMatcher();
        $this->includePatterns = $includePatterns;
        $this->excludePatterns = $excludePatterns;
    }

    public function stop(): void
    {
        $this->process->stop();
    }


    public function wait(): ?ModifiedFile
    {
        while (null !== $file = $this->process->wait()) {
            foreach ($this->includePatterns as $pattern) {
                if (false === $this->matcher->matches($file->path(), $pattern)) {
                    continue 2;
                }
            }

            foreach ($this->excludePatterns as $pattern) {
                if (true === $this->matcher->matches($file->path(), $pattern)) {
                    continue 2;
                }
            }

            return $file;
        }

        return null;
    }
}
