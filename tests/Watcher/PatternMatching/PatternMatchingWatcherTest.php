<?php

namespace Phpactor\AmpFsWatcher\Tests\Watcher\PatternMatching;

use Amp\PHPUnit\AsyncTestCase;
use Phpactor\AmpFsWatch\ModifiedFile;
use Phpactor\AmpFsWatch\ModifiedFileQueue;
use Phpactor\AmpFsWatch\Watcher;
use Phpactor\AmpFsWatch\Watcher\PatternMatching\PatternMatchingWatcher;
use Phpactor\AmpFsWatch\Watcher\TestWatcher\TestWatcher;

class PatternMatchingWatcherTest extends AsyncTestCase
{
    public function testIncludesFiles(): void
    {
        $process = $this->createWatcher(['/**/*.php'], [], [
            $this->createFile('/Foobar.php'),
            $this->createFile('/Foobar.php~'),
            $this->createFile('/timestamp'),
        ])->watch();

        $files = [];
        while (null !== $file = $process->wait()) {
            $files[] = $file;
        }

        self::assertCount(1, $files);
        self::assertEquals($this->createFile('/Foobar.php'), $files[0]);
    }

    public function testExcludesFiles(): void
    {
        $process = $this->createWatcher(['/**/*.php'], ['/**/Foobar.php'], [
            $this->createFile('/Foobar.php'),
            $this->createFile('/Barfoo.php'),
            $this->createFile('/timestamp'),
        ])->watch();

        $files = [];
        while (null !== $file = $process->wait()) {
            $files[] = $file;
        }

        self::assertCount(1, $files);
    }

    public function testIsSupported(): void
    {
        self::assertTrue($this->createWatcher([], [], [])->isSupported());
    }
    protected function createWatcher(array $includePatterns, array $excludePatterns, array $modifiedFiles): Watcher
    {
        return new PatternMatchingWatcher(new TestWatcher(new ModifiedFileQueue($modifiedFiles)), $includePatterns, $excludePatterns);
    }

    private function createFile(string $name): ModifiedFile
    {
        return new ModifiedFile($name, ModifiedFile::TYPE_FILE);
    }
}
