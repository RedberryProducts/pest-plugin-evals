<?php

use Illuminate\Support\Collection;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Redberry\Evals\Contracts\Rubric;
use Redberry\Evals\EvalContext;
use Redberry\Evals\EvalResult;
use Redberry\Evals\Judges\LlmJudge;
use Redberry\Evals\Judges\SimilarityJudge;

function makeTestContext(
    string $input = 'What is AI?',
    string $output = 'Artificial Intelligence',
    mixed $expected = null,
): EvalContext {
    $evalResult = new EvalResult(
        text: $output,
        structured: null,
        toolInvocations: new Collection,
        response: new AgentResponse(
            invocationId: 'inv-1',
            text: $output,
            usage: new Usage,
            meta: new Meta,
        ),
    );

    return new EvalContext(
        input: $input,
        output: $output,
        expected: $expected,
        result: $evalResult,
    );
}

describe('LlmJudge', function () {
    it('can be constructed with a string criterion', function () {
        $judge = new LlmJudge(criterion: 'Is the response helpful?');

        expect($judge)->toBeInstanceOf(LlmJudge::class);
    });

    it('can be constructed with a Rubric', function () {
        $rubric = new class extends Rubric
        {
            public function description(): string
            {
                return 'Test rubric description';
            }
        };

        $judge = new LlmJudge(criterion: $rubric);

        expect($judge)->toBeInstanceOf(LlmJudge::class);
    });

    it('can be constructed with threshold', function () {
        $judge = new LlmJudge(criterion: 'test', threshold: 90);

        expect($judge)->toBeInstanceOf(LlmJudge::class);
    });

    it('can be constructed with custom provider and model', function () {
        $judge = new LlmJudge(
            criterion: 'test',
            provider: 'anthropic',
            model: 'claude-3-haiku',
        );

        expect($judge)->toBeInstanceOf(LlmJudge::class);
    });

    it('implements Judge contract', function () {
        $judge = new LlmJudge(criterion: 'test');

        expect($judge)->toBeInstanceOf(Redberry\Evals\Contracts\Judge::class);
    });

    it('can be constructed with custom instructions', function () {
        $judge = new LlmJudge(
            criterion: 'test',
            instructions: 'Evaluate in a medical context',
        );

        expect($judge)->toBeInstanceOf(LlmJudge::class);
    });
});

describe('SimilarityJudge', function () {
    it('can be constructed with defaults', function () {
        $judge = new SimilarityJudge;

        expect($judge)->toBeInstanceOf(SimilarityJudge::class);
    });

    it('can be constructed with custom threshold', function () {
        $judge = new SimilarityJudge(threshold: 95);

        expect($judge)->toBeInstanceOf(SimilarityJudge::class);
    });

    it('can be constructed with custom provider and model', function () {
        $judge = new SimilarityJudge(
            provider: 'anthropic',
            model: 'claude-3-haiku',
        );

        expect($judge)->toBeInstanceOf(SimilarityJudge::class);
    });

    it('throws when expected is null', function () {
        $judge = new SimilarityJudge;
        $context = makeTestContext(expected: null);

        expect(fn () => $judge->evaluate($context))
            ->toThrow(InvalidArgumentException::class, 'expected');
    });

    it('implements Judge contract', function () {
        $judge = new SimilarityJudge;

        expect($judge)->toBeInstanceOf(Redberry\Evals\Contracts\Judge::class);
    });

    it('can be constructed with custom instructions', function () {
        $judge = new SimilarityJudge(
            instructions: 'Focus on medical terminology equivalence',
        );

        expect($judge)->toBeInstanceOf(SimilarityJudge::class);
    });
});

describe('Rubric', function () {
    it('defaults scored() to false', function () {
        $rubric = new class extends Rubric
        {
            public function description(): string
            {
                return 'Test description';
            }
        };

        expect($rubric->scored())->toBeFalse()
            ->and($rubric->description())->toBe('Test description');
    });

    it('can override scored to true', function () {
        $rubric = new class extends Rubric
        {
            public function description(): string
            {
                return 'Scored rubric';
            }

            public function scored(): bool
            {
                return true;
            }
        };

        expect($rubric->scored())->toBeTrue();
    });
});
