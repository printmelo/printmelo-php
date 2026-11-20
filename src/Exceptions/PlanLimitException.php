<?php

declare(strict_types=1);

namespace Printmelo\Exceptions;

/**
 * The limit of the subscription is reached or the trial has ended (`402 plan_limit`).
 */
class PlanLimitException extends ApiException
{
}
