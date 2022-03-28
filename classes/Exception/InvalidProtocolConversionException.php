<?php

namespace Wolfrack\Library\Exception;

class InvalidProtocolConversionException extends WolfrackLibraryException
{

    public function __construct(string $protocol)
    {
        parent::__construct($protocol . ' protocol is not supported in conversions.');
    }
}
