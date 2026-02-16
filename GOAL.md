# Testing AI Agents

This PEST plugin should work using dependency inversion method to avoid API interactions inside PEST plug, which means that it should provide contracts (Interface) for AI Agents to be evaluated and for different Judge types. Each interface should requre only 1 methods.

- `Evaluatable` to generate output of target agent
- `Judge` to compare actual output to the expected one

Any class implementing these interfaces can be used in PEST tests with this plugin.

## Evaluatable interface

In this examples, we will use `DataExtrator` as sample agent:

```php
use Redberry\Evals\Contracts\Evaluatable;
use Redberry\Evals\LlmInput;
use Redberry\Evals\LlmOutput;

class DataExtrator implements Evaluatable {
    // Other code of DataExtractor

    public static function generateOutput(LlmInput $input): LlmOutput {
        // Configurations that can be set by developer to try different approaches
        $input->config->provider; // Example: "openrouter"
        $input->config->model; // Example: "gpt-5-mini-2025-08-07"
        $input->config->temperature; // Example: 0.7

        // Array of messages (OpenAI-compatible Chat History)
        $input->messages; // Can include any role: developer, Tool call, tool result, etc.
        $input->userMessage(); // Last user message
        $input->instructons(); // The first system or developer message

        // Run LLM interaction

        // Return output with tool calls and LLM resonse
        $output = LlmOutput::make($messages ?? null); // Can pass OpenAI-compatible chat history
        // Or register tool calls and response manually
        $output->registerToolCall("toolName", $arguments); // $arguments can be PHP array or JSON
        $output->registerToolCall("toolName2", $arguments);
        $output->registerResponse($response);
        // Output includes only tool calls and final response
        // Final response is required
        return $output;
    }
}
```

simple implementation Example:
```php
public static function generateOutput(LlmInput $input): LlmOutput {

    $response = (new self)->prompt($input->userMessage());

    // Return output with tool calls and LLM resonse
    return LlmOutput::make()->registerResponse($response);
}
```

## Judge interfaces

In this examples, we will use `SimilarityJudge` as sample judge:

```php
use Redberry\Evals\Contracts\Judge;
use Redberry\Evals\LlmInput;
use Redberry\Evals\LlmOutput;
use Redberry\Evals\JudgeResult;

class SimilarityJudge implements Judge {
    // Other code of DataExtractor

    public static function judge(
        LlmInput $input,
        LlmOutput $expectedOutput,
        LlmOutput $actualOutput,
        ?string $request = null
    ): JudgeResult {
        // $expectedOutput as well as $actualOutput can include tool calls and final response
        $actualOutput->toolUseSequence(); // ['tool1', 'tool5', 'tool2']
        $actualOutput->toolUsed('tool3'); // true/false
        $actualOutput->toolCalls(); // array of tool calls with arguments
        $actualOutput->toolArgs('toolName'); // array of tool arguments
        $actualOutput->response(); // string or JSON (structured output)

        // Judging here


        // Return Judge result
        return new JudgeResult(
            valid: true, // Is actual output valid? bool
            score: 78 // Optional: Score of output, int
            comment: 'Comment from judge LLM' // Optional: comment, string
        ); 
    }
}
```

simple implementation Example:
```php
public static function judge(
    LlmInput $input,
    LlmOutput $expectedOutput,
    LlmOutput $actualOutput,
    ?string $request = null
): JudgeResult {
    // Assuming judge agent already have structured output defined
    $response = MyJudgeAgent::actual($actualOuput->response())
        ->expecting($expectedOutput->response())
        ->prompt($request ?? "Is actual output similar to expected output?");

    // Return output with tool calls and LLM resonse
    return new JudgeResult(
        valid: $response->isValid
    ); 
}
```

# Evalset & Data Flow

Evals plugin has to manage 3 data types supporting their representation as PHP array, JSON and XML.

- **Input**: OpenAI-compatible chat history or just the user message. Input can include any type of message:
    - Developer message
    - User message
    - LLM Response
    - Tool use
    - Tool result
    - etc.
- **Actual Output**: Supports only tool calls and final response. Is used in any tests since it is object that should be checked.
- **Expected Output**: Support only tool calls and final response. Is used with Judges.

Evals plugin supports 2 types of checks:
- Deterministic: simple programatic checks, where developer checks if output contains specific string, or if string lenght is than 100 characters, or tool call sequence and etc.
    **Determinisitc check doesn't need Expected Output**
- LLM-as-a-Judge: AI-driven checks when LLM assess output of other LLM. Normally Needs Expected and actual outputs, but sometime input also can be interesting to assess result's relevance to the input

Evals plugin support full flow:
1. Evalset management (dataset for evaluation having at least inputs) 
2. Output generation from Target LLM (Evaluatable)
    1) Judging with LLM
    2) Programmatic checks

The most basic test would look like:

```php
test('PostWriter can write in formal tone', function () {
    evals(PostWriter::class)
        ->withPrompt("Write post about PEST in formal tone")
        ->judge(ToneJudge::class, "is post written in formal tone?")
        ->evaluate() // Runs "generateOutput" method and "judge" methods here
        ->assertContains("PEST") // programmatic check
        ->assertValid() // judge's check
});
```

## Datasets

TODO: Decide about structure of datasets

```php

/*
|--------------------------------------------------------------------------
| Simple Dataset (inline values)
|--------------------------------------------------------------------------
*/
dataset('email_extractions_simple', [
    'extraction_1' => [
        'Does this text contain an email address? If so, extract it.',
        'Yes, the email address is: example@example.com',
    ],
]);

/*
|--------------------------------------------------------------------------
| Simple Dataset with file paths (no DTO)
|--------------------------------------------------------------------------
|
| Use this approach for simple path-based datasets where you handle
| loading in the test itself. Order: input, expectedOutput, cachedOutput.
|
*/
dataset('email_extractions_paths', [
    'email_1' => [
        'evals/DataExtractor/email_1/input.json',
        'evals/DataExtractor/email_1/expectedOutput.json',
    ],
    'email_2' => [
        'evals/DataExtractor/email_2/input.json',
        'evals/DataExtractor/email_2/expectedOutput.json',
        'evals/DataExtractor/email_2/cachedOutput.json',
    ],
]);

/*
|--------------------------------------------------------------------------
| Dataset with EvalCase DTO (inline data)
|--------------------------------------------------------------------------
*/
dataset('email_extractions', [
    'extraction_1' => fn () => EvalCase::fromData(
        input: [
            ['role' => 'system', 'content' => 'Extract structured data from the provided vacancy email.'],
            ['role' => 'user', 'content' => "Subject: Job Opening: Senior Software Engineer\n\nWe are looking for a Senior Software Engineer at TechCorp."],
        ],
        expectedOutput: [
            'tool_calls' => '[{"name":"parse_email","arguments":{"format":"vacancy"}}]',
            'final_response' => '{"company":"TechCorp","role":"Software Engineer","seniority":"senior","skills":[]}',
        ],
    ),

    'extraction_2_with_cache' => fn () => EvalCase::fromData(
        input: [
            ['role' => 'system', 'content' => 'Extract structured data from the provided vacancy email.'],
            ['role' => 'user', 'content' => "Subject: Junior Developer Position at StartupXYZ\n\nStartupXYZ is hiring a Junior Developer. Skills: PHP, Laravel."],
        ],
        expectedOutput: [
            'tool_calls' => '[{"name":"parse_email","arguments":{"format":"vacancy"}}]',
            'final_response' => '{"company":"StartupXYZ","role":"Developer","seniority":"junior","skills":["PHP","Laravel"]}',
        ],
        cachedOutput: [
            'tool_calls' => '[{"name":"parse_email","arguments":{"format":"vacancy"}}]',
            'final_response' => '{"company":"StartupXYZ","role":"Developer","seniority":"junior","skills":["PHP","Laravel"]}',
        ],
    ),
]);

/*
|--------------------------------------------------------------------------
| Dataset with EvalCase DTO (from JSON files)
|--------------------------------------------------------------------------
|
| Use this approach when you have larger test cases stored in JSON files.
| Paths are relative to the tests/ directory.
|
*/
dataset('email_extractions_from_files', [
    'email_1' => fn () => EvalCase::fromPaths(
        inputPath: 'evals/DataExtractor/email_1/input.json',
        expectedOutputPath: 'evals/DataExtractor/email_1/expectedOutput.json',
    ),

    'email_2_with_cache' => fn () => EvalCase::fromPaths(
        inputPath: 'evals/DataExtractor/email_2/input.json',
        expectedOutputPath: 'evals/DataExtractor/email_2/expectedOutput.json',
        cachedOutputPath: 'evals/DataExtractor/email_2/cachedOutput.json',
    ),
]);

/*
|--------------------------------------------------------------------------
| Dataset with single evalCase.json file
|--------------------------------------------------------------------------
|
| Use this approach when you want all eval data in a single file.
| The JSON file contains: input, expected_output, and cached_output.
|
*/
dataset('email_extractions_single_file', [
    'email_1' => fn () => EvalCase::fromPath('evals/DataExtractor/email_1/evalCase.json'),
]);

/*
|--------------------------------------------------------------------------
| Dataset with tool_calls and final_response as array
|--------------------------------------------------------------------------
|
| This dataset demonstrates:
| - tool_calls: array of tool invocations made by the agent
| - final_response: array format (alternative to JSON string)
|
*/
dataset('candidate_matching', [
    'match_with_tools' => fn () => EvalCase::fromData(
        input: [
            ['role' => 'system', 'content' => 'Find candidates matching the job requirements. Use the search_candidates tool.'],
            ['role' => 'user', 'content' => 'Find senior PHP developers with Laravel experience.'],
        ],
        expectedOutput: [
            'tool_calls' => [
                [
                    'name' => 'search_candidates',
                    'arguments' => [
                        'skills' => ['PHP', 'Laravel'],
                        'seniority' => 'senior',
                    ],
                ],
            ],
            'final_response' => [
                'matched_candidates' => 3,
                'top_match' => [
                    'name' => 'John Doe',
                    'score' => 0.95,
                ],
            ],
        ],
        cachedOutput: [
            'tool_calls' => [
                [
                    'name' => 'search_candidates',
                    'arguments' => [
                        'skills' => ['PHP', 'Laravel'],
                        'seniority' => 'senior',
                    ],
                ],
            ],
            'final_response' => [
                'matched_candidates' => 3,
                'top_match' => [
                    'name' => 'John Doe',
                    'score' => 0.95,
                ],
            ],
        ],
    ),

    'multiple_tool_calls' => fn () => EvalCase::fromData(
        input: [
            ['role' => 'system', 'content' => 'Search for candidates and verify their availability.'],
            ['role' => 'user', 'content' => 'Find available Vue.js developers.'],
        ],
        expectedOutput: [
            'tool_calls' => [
                [
                    'name' => 'search_candidates',
                    'arguments' => [
                        'skills' => ['Vue.js'],
                    ],
                ],
                [
                    'name' => 'check_availability',
                    'arguments' => [
                        'candidate_ids' => [1, 2, 3],
                    ],
                ],
            ],
            'final_response' => [
                'available_candidates' => 2,
                'candidates' => [
                    ['id' => 1, 'name' => 'Jane Smith', 'available' => true],
                    ['id' => 3, 'name' => 'Bob Wilson', 'available' => true],
                ],
            ],
        ],
        cachedOutput: [
            'tool_calls' => [
                [
                    'name' => 'search_candidates',
                    'arguments' => [
                        'skills' => ['Vue.js'],
                    ],
                ],
                [
                    'name' => 'check_availability',
                    'arguments' => [
                        'candidate_ids' => [1, 2, 3],
                    ],
                ],
            ],
            'final_response' => [
                'available_candidates' => 2,
                'candidates' => [
                    ['id' => 1, 'name' => 'Jane Smith', 'available' => true],
                    ['id' => 3, 'name' => 'Bob Wilson', 'available' => true],
                ],
            ],
        ],
    ),
]);

```

# Features

## Expectations API

PEST have expectations api which should also be supported by evals plugin:

```php
test('PostWriter can write in formal tone', function () {
    $eval = eval(PostWriter::class)
        ->withPrompt("Write post about PEST in formal tone")
        ->judge(ToneJudge::class, "is post written in formal tone?")
        ->evaluate();

    expect($eval->toolUsed('tool1'))
        ->toBeTrue();

    expect($eval->toolUsed('tool2', ['url' => 'https://example.com']))
        ->toBeTrue();

    expect($eval->isValid())->toBeTrue();
});
```



## Nullable output and caching

Output can be pre-generated (cached) so in provided dataset output can be nullable. When it is `null`, evaluator should run `$input` against target agent (DataExtractor in this case).

It can be setted using `withOutput` method:

```php
it('can extract email from text', 
    function (string $input, ?string $output = null) {
        // If output is null, DataExtractor will be used against $input to return output
        eval(DataExtractor::class)
            ->withMessages($input) // input is array of messages in OpenAI's API structure
            ->withOutput($output)
            ->evaluate()
            ->assertContains("john.doe@example.com")
    }
)->with('email_extraction');
```

The same goes to tests with judge:

```php

it('can extract email from text', 
    function (string $input, string $expectedOutput, ?string $output = null) {
        // If output is null, DataExtractor will be runned with $input
        eval(DataExtractor::class)
            ->withMessages($input)
            ->withOutput($output)
            ->judge(SimilarityJudge::class)
            ->against($expectedOutput)
            ->evaluate()
            ->assertContains("john.doe@example.com") // deterministic check
            ->assertValid(); // AI check from Judge LLM
    }
)->with('email_extraction');
```

Optionally, when there is no need for deterministic checks, evaluator should be able to receive `$output` as optional argument in `against` method

```php


it('can extract email from text', 
    function (string $input, string $expectedOutput, ?string $output = null) {
        // If output is null, DataExtractor will be runned with $input
        eval(DataExtractor::class)
            ->withMessages($input)
            ->judge(SimilarityJudge::class)
            ->against($expectedOutput, $output)
            ->evaluate()
            ->assertScoreGreaterThan(0.8) // AI check from Judge LLM
            ->assertValid(); // AI check from Judge LLM
    }
)->with('email_extraction');

```

## Deterministic checks

Plugin should be able to provide deterministic checks with output.

Example of deteministic checks with JSON dataset:
```php

it('can extract email from text :dataset', 
    function (string $input, ?string $output = null) {
        // If output is null, DataExtractor will be used against $input to return output
        eval(DataExtractor::class)
            ->withMessages($input)
            ->withOutput($output)
            ->evaluate()
            ->assertContains("john.doe@example.com")
            ->assertLenghtGreaterThan(100)
            ->assertToolUseSequence(['tool1', 'tool2'])
            ->assertToolUsed('tool2', 
                [
                    'url' => 'https://example.com',
                    'query' => '?test=123'
                ]
            );
    }
)->with([
    'email_extraction_1' => [
        'evals/DataExtractor/email_extraction_1/input.json',
    ],
    'email_extraction_2' => [
        'evals/DataExtractor/email_extraction_2/input.json',
        'evals/DataExtractor/email_extraction_2/cachedOutput.json',
    ],
]);
```


## LLM-based checks (Judge)

Judge result includes required bool `valid` and optional `score` & `comment` fields

Example with JSON datasets and Judge evaluation:
```php

it('can extract email from text :dataset', 
    function (string $input, string $expectedOutput, ?string $output = null) {
        // If output is null, DataExtractor will be runned with $input
        eval(DataExtractor::class)
            ->withMessages($input)
            ->judge(SimilarityJudge::class)
            ->against($expectedOutput, $output)
            ->evaluate()
            ->assertValid()
            ->assertSimilarityGreaterThan(0.8);
    }
)->with([
    'email_extraction_1' => [
        'evals/DataExtractor/email_extraction_1/input.json',
        'evals/DataExtractor/email_extraction_1/expectedOutput.json',
    ],
    'email_extraction_2' => [
        'evals/DataExtractor/email_extraction_2/input.json',
        'evals/DataExtractor/email_extraction_2/expectedOutput.json',
        'evals/DataExtractor/email_extraction_2/cachedOutput.json',
    ],
]);

```

Example with EvalCase:

```php

it('can extract email from text :dataset', 
    function (EvalCase $case) {
        // If output is null, DataExtractor will be runned with $input
        eval(DataExtractor::class)
            ->withCase($case)
            ->judge(SimilarityJudge::class)
            ->evaluate()
            ->assertValid()
            ->assertSimilarityGreaterThan(0.8);
    }
)->with([
    'email_1' => fn () => EvalCase::fromPaths(
        inputPath: 'evals/DataExtractor/email_1/input.json',
        expectedOutputPath: 'evals/DataExtractor/email_1/expectedOutput.json',
    ),

    'email_2_with_cache' => fn () => EvalCase::fromPaths(
        inputPath: 'evals/DataExtractor/email_2/input.json',
        expectedOutputPath: 'evals/DataExtractor/email_2/expectedOutput.json',
        cachedOutputPath: 'evals/DataExtractor/email_2/cachedOutput.json',
    ),
]);

```

