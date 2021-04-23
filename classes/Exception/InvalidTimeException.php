<?php

namespace Wolfrack\Library\Exception;

/**
 * Invalid time.
 */
class InvalidTimeException extends WolfrackLibraryException
{

    /**
     * InvalidTimeException constructor.
     * @param string $time Invalid time
     */
    public function __construct(string $time)
    {
        parent::__construct('Invalid time: ' . $time);
    }
}
