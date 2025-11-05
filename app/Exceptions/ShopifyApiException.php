<?php

namespace App\Exceptions;

use Exception;

class ShopifyApiException extends Exception
{
    protected array $userErrors;

    public function __construct(string $message, array $userErrors = [], int $code = 0)
    {
        parent::__construct($message, $code);
        $this->userErrors = $userErrors;
    }

    public function getUserErrors(): array
    {
        return $this->userErrors;
    }

    public function hasUserErrors(): bool
    {
        return !empty($this->userErrors);
    }
}

