<?php

declare(strict_types=1);

namespace Redberry\Evals;

/**
 * Sentinel value indicating a parameter was not provided.
 *
 * @internal
 */
enum Missing
{
    case Value;
}
