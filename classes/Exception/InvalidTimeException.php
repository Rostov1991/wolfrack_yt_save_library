<?php

namespace Wolfrack\Library\Exception;

class InvalidTimeException extends WolfrackLibraryException
{
    public function __construct(string $time)
    {
        parent::__construct('Invalid time: ' . $time);
    }
}
