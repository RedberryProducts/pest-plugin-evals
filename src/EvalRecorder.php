<?php

declare(strict_types=1);

namespace Redberry\Evals;

final class EvalRecorder
{
    private static bool $verbose = false;

    private static bool $showReasoning = true;

    /** @var list<EvalRecord> */
    private static array $records = [];

    public static function isVerbose(): bool
    {
        return self::$verbose;
    }

    public static function setVerbose(bool $verbose): void
    {
        self::$verbose = $verbose;
    }

    public static function showReasoning(): bool
    {
        return self::$showReasoning;
    }

    public static function setShowReasoning(bool $show): void
    {
        self::$showReasoning = $show;
    }

    public static function record(EvalRecord $record): void
    {
        if (! self::$verbose) {
            return;
        }

        self::$records[] = $record;
    }

    /**
     * Drain all records and return them, resetting the buffer.
     *
     * @return list<EvalRecord>
     */
    public static function flush(): array
    {
        $records = self::$records;
        self::$records = [];

        return $records;
    }

    public static function reset(): void
    {
        self::$verbose = false;
        self::$showReasoning = true;
        self::$records = [];
    }
}
