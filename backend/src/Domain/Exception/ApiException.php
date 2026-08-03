<?php

namespace App\Domain\Exception;

/**
 * A \RuntimeException that also carries an optional machine-readable code
 * (e.g. "EMAIL_NOT_VERIFIED") for responses where the frontend needs to
 * branch on something more specific than the HTTP status. Caught by the
 * same RuntimeExceptionListener as plain \RuntimeException — the code is
 * simply added to the JSON payload when present.
 */
class ApiException extends \RuntimeException
{
    public function __construct(string $message, int $httpStatus, private ?string $apiCode = null)
    {
        parent::__construct($message, $httpStatus);
    }

    public function getApiCode(): ?string
    {
        return $this->apiCode;
    }
}
