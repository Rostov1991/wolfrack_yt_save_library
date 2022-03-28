<?php

namespace Wolfrack\Library\Exception;

use Symfony\Component\Process\Process;

class YoutubedlException extends WolfrackLibraryException
{

    public function __construct(Process $process)
    {
        parent::__construct(
            $process->getCommandLine() . ' failed with this error:' . PHP_EOL . trim($process->getErrorOutput()),
            intval($process->getExitCode())
        );
    }
}
