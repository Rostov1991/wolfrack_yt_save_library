<?php

namespace Wolfrack\Library\Exception;

/**
 * Conversion of playlists is not supported.
 */
class PlaylistConversionException extends WolfrackLibraryException
{
    /**
     * Error message.
     * @var string
     */
    protected $message = 'Conversion of playlists is not supported.';
}
