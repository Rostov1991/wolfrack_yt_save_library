<?php

/**
 * EmptyUrlException class.
 */

namespace Wolfrack\Library\Exception;

/**
 * Exception thrown when youtube-dl returns an empty URL.
 */
class EmptyUrlException extends WolfrackLibraryException
{
    /**
     * Error message.
     * @var string
     */
    protected $message = 'youtube-dl returned an empty URL.';
}
