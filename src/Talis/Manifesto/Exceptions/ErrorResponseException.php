<?php

namespace Talis\Manifesto\Exceptions;

class ErrorResponseException extends \Exception
{
    /**
     * @param string $message [optional] Manifesto error message
     * @param string $code [optional] Manifesto error code
     * @param \Exception $previous [optional] The previous exception used for the exception chaining.
     */
    public function __construct(string $message = '', int $code = 0, ?\Exception $previous = null)
    {
        $this->code = $code;
        parent::__construct($message, $code, $previous);
    }
}
