<?php

namespace Wolfrack\Library\Exception;

class AvconvException extends WolfrackLibraryException
{

    public function __construct(string $path)
    {
        parent::__construct("Can't find avconv or ffmpeg at " . $path . '.');
    }
}
