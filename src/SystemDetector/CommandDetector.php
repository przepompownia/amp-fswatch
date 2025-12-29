<?php

namespace Phpactor\AmpFsWatch\SystemDetector;

use Amp\Process\Process;

class CommandDetector
{
    public function commandExists(string $command): bool
    {
        return $this->checkPosixCommand($command);
    }

    private function checkPosixCommand(string $command): bool
    {
        $process = Process::start([
            'command',
            '-v',
            $command,
        ]);

        return 0 === $process->join();
    }
}
