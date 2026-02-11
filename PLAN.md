# PEST Plugin Evals - Implementation Plan

## Overview

A PEST plugin for evaluating LLM outputs using LLM-as-a-judge pattern with structured assertions.

## Architecture

### Core Components

#### 1. `Eval` Class (Main Orchestrator)
- Manages `LlmOutput` and `Judge` instances
- Entry point for evaluation workflow
- Coordinates between output parsing and judgment

**Expected Usage:**
```php
evals()->load($output)      // Load from file path or PHP array
evals()->loadJson($output)  // Load from JSON file path or JSON string
```

#### 2. `LlmOutput` Class
- Parses and structures LLM output data
- Supports multiple input formats:
  - JSON file path
  - PHP array file path  
- Direct array/object argument
- OpenAI API-style chat history format
  
**Provides access to:**
- Tool use sequence
- Specific tool calls with arguments
- Response content
- Chat history navigation

#### 3. `Judge` Interface & Abstract Classes
- Interface defining judge contract
- Abstract base classes for common judge types
- Developers extend these to create custom judges

**Judge Capabilities:**
- Custom prompt injection (e.g., "Does response contain email 'test@example.com'?")
- Different judge types (similarity, validation, custom criteria)
- Returns structured DTO, for example, similarity judge:
  - `valid`: boolean (true/false)
  - `similarityScore`: float (optional)
  - Additional metadata if needed

### Assertions (To Be Determined)

Two possible approaches:

**Option A: Built-in Assertions**
```php
evals()->load($output)
    ->assertToolUseSequence(['tool1', 'tool2', 'tool3'])
    ->assertToolUsed('tool2', 
        [
            'url' => 'https://example.com',
            'query' => '?test=123'
        ]
    );

evals()->load($output)
    ->judge(SimilarityJudge::class)
    ->assertValid()
    ->assertSimilarityGreaterThan(0.8);
```

**Option B: Return Values for PEST Expectations**
```php
$eval = evals()->load($output);
expect($eval->toolUseSequence())->toBe(['tool1', 'tool2', 'tool3']);

expect($eval->toolUsed('tool1'))
    ->toBeTrue();

expect($eval->toolUsed('tool2', ['url' => 'https://example.com']))
    ->toBeTrue();

$result = evals()->load($output)->judge(SimilarityJudge::class);
expect($result->isValid())->toBeTrue();
expect($result->similarityScore)->toBeGreaterThan(0.8);
```

**Decision pending:** Discuss which approach fits better with PEST philosophy and developer experience.


## Open Questions

1. **Assertions approach**: Built-in vs. return values for PEST expectations?
2. **Judge extensibility**: How much flexibility should custom judges have?
- Should each judge type has it own assertions?
- How LlmOutput handles structured output? Can we test it with assertions?
