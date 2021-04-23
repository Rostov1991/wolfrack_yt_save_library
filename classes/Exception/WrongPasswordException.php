<?php

namespace Wolfrack\Library\Exception;

/**
 * Wrong password.
 */
class WrongPasswordException extends WolfrackLibraryException
{
    /**
     * Error message.
     * @var string
     */
    protected $message = 'Wrong password.';
}
