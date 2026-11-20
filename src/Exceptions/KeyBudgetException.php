<?php

declare(strict_types=1);

namespace Printmelo\Exceptions;

/**
 * The API key has used its daily print budget (`429 key_budget`). It can print again tomorrow.
 */
class KeyBudgetException extends RateLimitException
{
}
