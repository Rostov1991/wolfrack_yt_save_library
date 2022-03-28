<?php

namespace Wolfrack\Library\Exception;

class EmptyUrlException extends WolfrackLibraryException
{

    protected $message = 'youtube-dl returned an empty URL.';
}
