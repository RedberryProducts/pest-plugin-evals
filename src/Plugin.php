<?php

declare(strict_types=1);

namespace Redberry\Evals;

use Pest\Contracts\Plugins\Bootable;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Concerns\HandleArguments;
use PHPUnit\Event\Facade as EventFacade;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Plugin implements Bootable, HandlesArguments
{
    use HandleArguments;

    public function __construct(
        private readonly OutputInterface $output,
    ) {}

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    public function handleArguments(array $arguments): array
    {
        if ($this->hasArgument('--evals-verbose', $arguments)) {
            $arguments = $this->popArgument('--evals-verbose', $arguments);
            EvalRecorder::setVerbose(true);
        }

        if (! EvalRecorder::isVerbose()) {
            $envValue = getenv('EVALS_VERBOSE') ?: ($_ENV['EVALS_VERBOSE'] ?? '');

            if (is_string($envValue) && in_array(strtolower($envValue), ['true', '1', 'yes'], true)) {
                EvalRecorder::setVerbose(true);
            }
        }

        return $arguments;
    }

    public function boot(): void
    {
        $this->readConfig();

        // Register subscriber unconditionally; it checks verbose status at runtime
        // because boot() runs before handleArguments() parses --evals-verbose
        EventFacade::instance()->registerSubscribers(
            new EvalOutputSubscriber(new EvalOutputRenderer($this->output)),
        );
    }

    private function readConfig(): void
    {
        if (! function_exists('config')) {
            return;
        }

        try {
            if (config('evals.output.show_reasoning') === false) {
                EvalRecorder::setShowReasoning(false);
            }

            if (! EvalRecorder::isVerbose() && config('evals.output.verbose')) {
                EvalRecorder::setVerbose(true);
            }
        } catch (\Throwable) {
            // Config repository not bound (e.g., unit tests without Laravel)
        }
    }
}
