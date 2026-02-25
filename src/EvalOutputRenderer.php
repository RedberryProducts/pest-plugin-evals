<?php

declare(strict_types=1);

namespace Redberry\Evals;

use Symfony\Component\Console\Output\OutputInterface;

final class EvalOutputRenderer
{
    private const SEPARATOR = '  ─────────────────────────────────────────────────────────────────';

    public function __construct(
        private readonly OutputInterface $output,
    ) {}

    /**
     * Render verbose output for a completed test's eval records.
     *
     * @param  list<EvalRecord>  $records
     */
    public function render(array $records): void
    {
        if ($records === []) {
            return;
        }

        $grouped = $this->groupByAssertion($records);

        foreach ($grouped as $assertionRecords) {
            $this->renderAssertionBlock($assertionRecords);
        }
    }

    /**
     * @param  list<EvalRecord>  $records
     */
    private function renderAssertionBlock(array $records): void
    {
        $first = $records[0];
        $isSampled = $first->sampleIndex !== null;

        $this->output->writeln('');
        $this->output->writeln(self::SEPARATOR);

        $this->output->writeln("  Assertion: {$first->assertionName}");

        if ($isSampled) {
            $this->renderSampledBlock($records);
        } else {
            $this->renderSingleBlock($first);
        }

        $this->output->writeln(self::SEPARATOR);
    }

    private function renderSingleBlock(EvalRecord $record): void
    {
        $this->renderStatus($record->passed, $record->score);
        $this->output->writeln('');

        if (! $record->passed) {
            $this->output->writeln('  Input:  "'.$this->truncate($record->input, 200).'"');
            $this->output->writeln('  Output: "'.$this->truncate($record->output, 200).'"');
            $this->output->writeln('');
        }

        $this->renderToolInvocations($record);

        if ($record->reasoning !== null && EvalRecorder::showReasoning()) {
            $this->output->writeln('  Judge Reasoning:');
            $this->output->writeln('  "'.$this->truncate($record->reasoning, 300).'"');
            $this->output->writeln('');
        }

        if ($record->score !== null && ! $record->passed) {
            $this->output->writeln("  Score: {$record->score} / 100");
            $this->output->writeln('');
        }
    }

    /**
     * @param  list<EvalRecord>  $records
     */
    private function renderSampledBlock(array $records): void
    {
        $total = $records[0]->sampleTotal ?? count($records);
        $minimum = $records[0]->sampleMinimum ?? $total;
        $passCount = 0;

        foreach ($records as $record) {
            if ($record->passed) {
                $passCount++;
            }
        }

        $overallPassed = $passCount >= $minimum;

        if ($overallPassed) {
            $this->output->writeln("  <fg=green>✓ PASSED</>  ({$passCount} of {$total} passed)");
        } else {
            $this->output->writeln("  <fg=red>✗ FAILED</>  ({$passCount} of {$total} passed, minimum: {$minimum})");
        }

        $this->output->writeln('');

        foreach ($records as $record) {
            $idx = ($record->sampleIndex ?? 0) + 1;
            $scoreStr = $record->score !== null ? "score: {$record->score}" : '';

            if ($record->passed) {
                $this->output->writeln("  Sample #{$idx}: <fg=green>✓ PASS</>  ({$scoreStr})");
            } else {
                $this->output->writeln("  Sample #{$idx}: <fg=red>✗ FAIL</>  ({$scoreStr})");
                if ($record->reasoning !== null && EvalRecorder::showReasoning()) {
                    $this->output->writeln('    → "'.$this->truncate($record->reasoning, 200).'"');
                }
            }
        }

        $this->output->writeln('');
        $percentage = $total > 0 ? (int) round(($passCount / $total) * 100) : 0;
        $this->output->writeln("  Pass Rate: {$passCount}/{$total} ({$percentage}%)");
        $this->output->writeln('');
    }

    private function renderStatus(bool $passed, ?int $score): void
    {
        if ($passed) {
            $scoreStr = $score !== null ? "  (score: {$score})" : '';
            $this->output->writeln("  <fg=green>✓ PASSED</>{$scoreStr}");
        } else {
            $this->output->writeln('  <fg=red>✗ FAILED</>');
        }
    }

    private function renderToolInvocations(EvalRecord $record): void
    {
        if ($record->toolInvocations->isEmpty()) {
            return;
        }

        if ($record->passed) {
            $names = $record->toolInvocations->map(fn (ToolInvocation $t): string => $t->toolName)->implode(' → ');
            $this->output->writeln("  Tools: {$names}");
        } else {
            $first = true;
            foreach ($record->toolInvocations as $tool) {
                $argsJson = $tool->arguments !== [] ? json_encode($tool->arguments, JSON_UNESCAPED_SLASHES) : '';
                $argsStr = $argsJson !== '' && $argsJson !== false ? '('.$this->truncate($argsJson, 80).')' : '()';
                $resultStr = $this->formatToolResult($tool->result);

                $prefix = $first ? '  Tools: ' : '         ';
                $this->output->writeln("{$prefix}{$tool->toolName}{$argsStr} → {$resultStr}");
                $first = false;
            }
        }

        $this->output->writeln('');
    }

    private function formatToolResult(mixed $result): string
    {
        if ($result === null) {
            return 'null';
        }

        if (is_bool($result)) {
            return $result ? 'true' : 'false';
        }

        if (is_string($result)) {
            return '"'.$this->truncate($result, 60).'"';
        }

        if (is_array($result)) {
            $json = json_encode($result, JSON_UNESCAPED_SLASHES);

            return $json !== false ? $this->truncate($json, 80) : '[...]';
        }

        if (is_scalar($result)) {
            return (string) $result;
        }

        return '(object)';
    }

    /**
     * Group records by assertion name, preserving order.
     *
     * @param  list<EvalRecord>  $records
     * @return list<list<EvalRecord>>
     */
    private function groupByAssertion(array $records): array
    {
        /** @var array<string, list<EvalRecord>> $groups */
        $groups = [];

        foreach ($records as $record) {
            $groups[$record->assertionName][] = $record;
        }

        return array_values($groups);
    }

    private function truncate(string $text, int $maxLength): string
    {
        $text = str_replace(["\n", "\r"], ' ', $text);

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength - 3).'...';
    }
}
