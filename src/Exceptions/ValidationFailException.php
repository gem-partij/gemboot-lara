<?php

namespace Gemboot\Exceptions;

use Throwable;

class ValidationFailException extends HttpErrorException
{
    /**
     * Override constructor to handle backward compatibility logic if needed,
     * but ideally, passing array data should be handled via the $data property.
     */
    public function __construct($errors = [], string $message = 'Validation Failed', int $code = 400, Throwable $previous = null)
    {
        // Logic lama Anda mungkin mengirim errors sebagai message json
        // Kita simpan errors di $data agar lebih proper
        $data = is_array($errors) ? $errors : ['error' => $errors];

        // Backward compatibility: Jika handler Anda mengharapkan JSON string di message
        $finalMessage = is_array($errors) ? json_encode($errors) : $message;

        parent::__construct($code, $finalMessage, $data, $previous);
    }
}