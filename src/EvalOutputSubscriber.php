<?php

declare(strict_types=1);

namespace Redberry\Evals;

use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;

final class EvalOutputSubscriber implements FinishedSubscriber
{
    public function __construct(
        private readonly EvalOutputRenderer $renderer,
    ) {}

    public function notify(Finished $event): void
    {
        if (! EvalRecorder::isVerbose()) {
            return;
        }

        $records = EvalRecorder::flush();

        if ($records === []) {
            return;
        }

        $this->renderer->render($records);
    }
}
