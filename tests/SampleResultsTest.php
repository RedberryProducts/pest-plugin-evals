<?php

use Illuminate\Support\Collection;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Redberry\Evals\EvalResult;
use Redberry\Evals\JudgeResult;
use Redberry\Evals\SampleResults;

function makeEvalResult(string $text = 'output'): EvalResult
{
    return new EvalResult(
        text: $text,
        structured: null,
        toolInvocations: new Collection,
        response: new AgentResponse(
            invocationId: 'inv-1',
            text: $text,
            usage: new Usage,
            meta: new Meta,
        ),
    );
}

describe('basic collection behavior', function () {
    it('counts results', function () {
        $results = new SampleResults(
            new Collection([makeEvalResult('a'), makeEvalResult('b'), makeEvalResult('c')]),
        );

        expect($results->count())->toBe(3)
            ->and(count($results))->toBe(3);
    });

    it('returns outputs collection', function () {
        $results = new SampleResults(
            new Collection([makeEvalResult('a'), makeEvalResult('b')]),
        );

        expect($results->outputs())->toHaveCount(2)
            ->and($results->outputs()->first()->text)->toBe('a');
    });

    it('returns first result', function () {
        $results = new SampleResults(
            new Collection([makeEvalResult('first'), makeEvalResult('second')]),
        );

        expect($results->first()->text)->toBe('first');
    });

    it('returns last result', function () {
        $results = new SampleResults(
            new Collection([makeEvalResult('first'), makeEvalResult('last')]),
        );

        expect($results->last()->text)->toBe('last');
    });

    it('is iterable', function () {
        $results = new SampleResults(
            new Collection([makeEvalResult('a'), makeEvalResult('b')]),
        );

        $texts = [];
        foreach ($results as $result) {
            $texts[] = $result->text;
        }

        expect($texts)->toBe(['a', 'b']);
    });

    it('returns minimum value', function () {
        $results = new SampleResults(new Collection([makeEvalResult()]), minimum: 3);

        expect($results->minimum())->toBe(3);
    });

    it('returns null minimum by default', function () {
        $results = new SampleResults(new Collection([makeEvalResult()]));

        expect($results->minimum())->toBeNull();
    });

    it('iterates with each()', function () {
        $results = new SampleResults(
            new Collection([makeEvalResult('a'), makeEvalResult('b')]),
        );

        $collected = [];
        $results->each(function (EvalResult $r) use (&$collected) {
            $collected[] = $r->text;
        });

        expect($collected)->toBe(['a', 'b']);
    });
});

describe('judge results', function () {
    it('has null judge results initially', function () {
        $results = new SampleResults(new Collection([makeEvalResult()]));

        expect($results->judgeResults())->toBeNull();
    });

    it('attaches judge results immutably', function () {
        $original = new SampleResults(new Collection([makeEvalResult()]));

        $judgeResults = new Collection([
            new JudgeResult(passed: true, score: 90, reasoning: 'Good'),
        ]);

        $withJudge = $original->withJudgeResults($judgeResults);

        expect($original->judgeResults())->toBeNull()
            ->and($withJudge->judgeResults())->toHaveCount(1);
    });

    it('calculates pass rate with all passing', function () {
        $results = new SampleResults(
            new Collection([makeEvalResult(), makeEvalResult(), makeEvalResult()]),
        );

        $judged = $results->withJudgeResults(new Collection([
            new JudgeResult(passed: true, score: 90, reasoning: 'Good'),
            new JudgeResult(passed: true, score: 85, reasoning: 'Good'),
            new JudgeResult(passed: true, score: 95, reasoning: 'Great'),
        ]));

        expect($judged->passRate())->toBe(100.0);
    });

    it('calculates pass rate with partial passing', function () {
        $results = new SampleResults(
            new Collection([makeEvalResult(), makeEvalResult(), makeEvalResult(), makeEvalResult()]),
        );

        $judged = $results->withJudgeResults(new Collection([
            new JudgeResult(passed: true, score: 90, reasoning: 'Good'),
            new JudgeResult(passed: false, score: 30, reasoning: 'Bad'),
            new JudgeResult(passed: true, score: 85, reasoning: 'Good'),
            new JudgeResult(passed: false, score: 20, reasoning: 'Bad'),
        ]));

        expect($judged->passRate())->toBe(50.0);
    });

    it('calculates pass rate with none passing', function () {
        $results = new SampleResults(
            new Collection([makeEvalResult(), makeEvalResult()]),
        );

        $judged = $results->withJudgeResults(new Collection([
            new JudgeResult(passed: false, score: 10, reasoning: 'Bad'),
            new JudgeResult(passed: false, score: 20, reasoning: 'Bad'),
        ]));

        expect($judged->passRate())->toBe(0.0);
    });

    it('returns 0 pass rate when no judge results', function () {
        $results = new SampleResults(new Collection([makeEvalResult()]));

        expect($results->passRate())->toBe(0.0);
    });

    it('calculates average score', function () {
        $results = new SampleResults(
            new Collection([makeEvalResult(), makeEvalResult(), makeEvalResult()]),
        );

        $judged = $results->withJudgeResults(new Collection([
            new JudgeResult(passed: true, score: 80, reasoning: 'OK'),
            new JudgeResult(passed: true, score: 90, reasoning: 'Good'),
            new JudgeResult(passed: true, score: 100, reasoning: 'Great'),
        ]));

        expect($judged->averageScore())->toBe(90.0);
    });

    it('returns null average score for binary judges', function () {
        $results = new SampleResults(
            new Collection([makeEvalResult(), makeEvalResult()]),
        );

        $judged = $results->withJudgeResults(new Collection([
            new JudgeResult(passed: true, score: null, reasoning: 'Pass'),
            new JudgeResult(passed: false, score: null, reasoning: 'Fail'),
        ]));

        expect($judged->averageScore())->toBeNull();
    });

    it('returns null average score when no judge results', function () {
        $results = new SampleResults(new Collection([makeEvalResult()]));

        expect($results->averageScore())->toBeNull();
    });
});

describe('passed() verdict', function () {
    it('returns false when no judge results', function () {
        $results = new SampleResults(new Collection([makeEvalResult()]));

        expect($results->passed())->toBeFalse();
    });

    it('returns true when all pass and no minimum set', function () {
        $results = new SampleResults(
            new Collection([makeEvalResult(), makeEvalResult()]),
        );

        $judged = $results->withJudgeResults(new Collection([
            new JudgeResult(passed: true, score: 90, reasoning: 'Good'),
            new JudgeResult(passed: true, score: 85, reasoning: 'Good'),
        ]));

        expect($judged->passed())->toBeTrue();
    });

    it('returns false when not all pass and no minimum set', function () {
        $results = new SampleResults(
            new Collection([makeEvalResult(), makeEvalResult()]),
        );

        $judged = $results->withJudgeResults(new Collection([
            new JudgeResult(passed: true, score: 90, reasoning: 'Good'),
            new JudgeResult(passed: false, score: 30, reasoning: 'Bad'),
        ]));

        expect($judged->passed())->toBeFalse();
    });

    it('returns true when minimum threshold is met', function () {
        $results = new SampleResults(
            new Collection([makeEvalResult(), makeEvalResult(), makeEvalResult()]),
            minimum: 2,
        );

        $judged = $results->withJudgeResults(new Collection([
            new JudgeResult(passed: true, score: 90, reasoning: 'Good'),
            new JudgeResult(passed: true, score: 85, reasoning: 'Good'),
            new JudgeResult(passed: false, score: 30, reasoning: 'Bad'),
        ]));

        expect($judged->passed())->toBeTrue();
    });

    it('returns false when minimum threshold is not met', function () {
        $results = new SampleResults(
            new Collection([makeEvalResult(), makeEvalResult(), makeEvalResult()]),
            minimum: 3,
        );

        $judged = $results->withJudgeResults(new Collection([
            new JudgeResult(passed: true, score: 90, reasoning: 'Good'),
            new JudgeResult(passed: true, score: 85, reasoning: 'Good'),
            new JudgeResult(passed: false, score: 30, reasoning: 'Bad'),
        ]));

        expect($judged->passed())->toBeFalse();
    });
});
