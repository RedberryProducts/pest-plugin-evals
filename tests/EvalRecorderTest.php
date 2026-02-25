<?php

use Illuminate\Support\Collection;
use Redberry\Evals\EvalRecord;
use Redberry\Evals\EvalRecorder;

afterEach(function () {
    EvalRecorder::reset();
});

function makeRecord(string $name = 'test assertion', bool $passed = true): EvalRecord
{
    return new EvalRecord(
        assertionName: $name,
        input: 'test input',
        output: 'test output',
        passed: $passed,
        toolInvocations: new Collection,
    );
}

it('defaults to non-verbose', function () {
    expect(EvalRecorder::isVerbose())->toBeFalse();
});

it('sets and reads verbose flag', function () {
    EvalRecorder::setVerbose(true);

    expect(EvalRecorder::isVerbose())->toBeTrue();

    EvalRecorder::setVerbose(false);

    expect(EvalRecorder::isVerbose())->toBeFalse();
});

it('defaults showReasoning to true', function () {
    expect(EvalRecorder::showReasoning())->toBeTrue();
});

it('sets and reads showReasoning flag', function () {
    EvalRecorder::setShowReasoning(false);

    expect(EvalRecorder::showReasoning())->toBeFalse();

    EvalRecorder::setShowReasoning(true);

    expect(EvalRecorder::showReasoning())->toBeTrue();
});

it('does not record when not verbose', function () {
    EvalRecorder::record(makeRecord());

    expect(EvalRecorder::flush())->toBe([]);
});

it('records when verbose is enabled', function () {
    EvalRecorder::setVerbose(true);

    $record = makeRecord();
    EvalRecorder::record($record);

    $flushed = EvalRecorder::flush();

    expect($flushed)->toHaveCount(1)
        ->and($flushed[0])->toBe($record);
});

it('flush drains the buffer', function () {
    EvalRecorder::setVerbose(true);
    EvalRecorder::record(makeRecord('first'));
    EvalRecorder::record(makeRecord('second'));

    $first = EvalRecorder::flush();

    expect($first)->toHaveCount(2);

    $second = EvalRecorder::flush();

    expect($second)->toBe([]);
});

it('reset restores all defaults', function () {
    EvalRecorder::setVerbose(true);
    EvalRecorder::setShowReasoning(false);
    EvalRecorder::record(makeRecord());

    EvalRecorder::reset();

    expect(EvalRecorder::isVerbose())->toBeFalse()
        ->and(EvalRecorder::showReasoning())->toBeTrue()
        ->and(EvalRecorder::flush())->toBe([]);
});
