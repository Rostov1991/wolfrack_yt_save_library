<?php

namespace Wolfrack\Library\Exception;

/**
 * Could not open popen stream.
 */
class PopenStreamException extends WolfrackLibraryException
{
    /**
     * Error message.
     * @var string
     */
    protected $message = 'Could not open popen stream.';
}
