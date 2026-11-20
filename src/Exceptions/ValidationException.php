<?php

declare(strict_types=1);

namespace Printmelo\Exceptions;

/**
 * The input is invalid (`422 validation_failed`). `getErrors()` lists the messages per field.
 */
class ValidationException extends ApiException
{
    /**
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        $errors = $this->getBody()['errors'] ?? [];

        return is_array($errors) ? $errors : [];
    }
}
