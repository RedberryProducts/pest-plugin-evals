<?php

use Redberry\Evals\DatasetLoader;
use Redberry\Evals\EvalCase;

function fixturesPath(string $file = ''): string
{
    return __DIR__.'/Fixtures/datasets'.($file ? '/'.$file : '');
}

describe('fromJson', function () {
    it('loads a simple JSON case', function () {
        $loader = new DatasetLoader;
        $case = $loader->fromJson(fixturesPath('simple.case.json'));

        expect($case)->toBeInstanceOf(EvalCase::class)
            ->and($case->prompt)->toBe('What is the capital of France?')
            ->and($case->expected)->toBeNull();
    });

    it('loads a JSON case with expected value', function () {
        $loader = new DatasetLoader;
        $case = $loader->fromJson(fixturesPath('with-expected.case.json'));

        expect($case->prompt)->toBe('What is the capital of France?')
            ->and($case->expected)->toBe('Paris');
    });

    it('loads a JSON case with attachments', function () {
        $loader = new DatasetLoader;
        $case = $loader->fromJson(fixturesPath('with-attachments.case.json'));

        expect($case->prompt)->toBe('Summarize the document')
            ->and($case->expected)->toBe('A summary of the content')
            ->and($case->attachments)->toHaveCount(1);
    });

    it('throws for missing file', function () {
        $loader = new DatasetLoader;

        expect(fn () => $loader->fromJson('/nonexistent/path.json'))
            ->toThrow(ErrorException::class);
    });

    it('throws for missing prompt field', function () {
        $loader = new DatasetLoader;

        expect(fn () => $loader->fromJson(fixturesPath('missing-prompt.case.json')))
            ->toThrow(InvalidArgumentException::class, 'prompt');
    });

    it('throws for invalid JSON', function () {
        $tmpFile = tempnam(sys_get_temp_dir(), 'eval_test_');
        file_put_contents($tmpFile, '{invalid json}');

        $loader = new DatasetLoader;

        expect(fn () => $loader->fromJson($tmpFile))
            ->toThrow(JsonException::class);

        unlink($tmpFile);
    });
});

describe('fromXml', function () {
    it('loads multiple cases from XML', function () {
        $loader = new DatasetLoader;
        $cases = $loader->fromXml(fixturesPath('cases.case.xml'));

        expect($cases)->toBeArray()
            ->and($cases)->toHaveCount(2)
            ->and($cases)->toHaveKeys(['capital-france', 'capital-japan'])
            ->and($cases['capital-france']->prompt)->toBe('What is the capital of France?')
            ->and($cases['capital-france']->expected)->toBe('Paris')
            ->and($cases['capital-japan']->prompt)->toBe('What is the capital of Japan?')
            ->and($cases['capital-japan']->expected)->toBe('Tokyo');
    });

    it('loads structured expected from XML', function () {
        $loader = new DatasetLoader;
        $cases = $loader->fromXml(fixturesPath('structured.case.xml'));

        expect($cases)->toHaveKey('structured-test')
            ->and($cases['structured-test']->expected)->toBe(['name' => 'John', 'age' => '30']);
    });

    it('throws for missing name attribute', function () {
        $tmpFile = tempnam(sys_get_temp_dir(), 'eval_test_');
        file_put_contents($tmpFile, '<?xml version="1.0"?><evalset><case><prompt>Test</prompt></case></evalset>');

        $loader = new DatasetLoader;

        expect(fn () => $loader->fromXml($tmpFile))
            ->toThrow(InvalidArgumentException::class, 'name');

        unlink($tmpFile);
    });

    it('throws for missing prompt element', function () {
        $tmpFile = tempnam(sys_get_temp_dir(), 'eval_test_');
        file_put_contents($tmpFile, '<?xml version="1.0"?><evalset><case name="test"></case></evalset>');

        $loader = new DatasetLoader;

        expect(fn () => $loader->fromXml($tmpFile))
            ->toThrow(InvalidArgumentException::class, 'prompt');

        unlink($tmpFile);
    });
});

describe('fromDirectory', function () {
    it('discovers JSON case files', function () {
        $tmpDir = sys_get_temp_dir().'/eval_test_json_'.uniqid();
        mkdir($tmpDir);
        copy(fixturesPath('simple.case.json'), $tmpDir.'/geo.case.json');
        copy(fixturesPath('with-expected.case.json'), $tmpDir.'/expected.case.json');

        $loader = new DatasetLoader;
        $cases = $loader->fromDirectory($tmpDir);

        expect($cases)->toBeArray()
            ->and($cases)->toHaveCount(2)
            ->and($cases)->toHaveKeys(['geo', 'expected']);

        // Cleanup
        unlink($tmpDir.'/geo.case.json');
        unlink($tmpDir.'/expected.case.json');
        rmdir($tmpDir);
    });

    it('discovers XML case files', function () {
        // Create temp dir with only XML
        $tmpDir = sys_get_temp_dir().'/eval_test_xml_'.uniqid();
        mkdir($tmpDir);
        copy(fixturesPath('cases.case.xml'), $tmpDir.'/cases.case.xml');

        $loader = new DatasetLoader;
        $cases = $loader->fromDirectory($tmpDir);

        expect($cases)->toHaveKeys(['capital-france', 'capital-japan']);

        // Cleanup
        unlink($tmpDir.'/cases.case.xml');
        rmdir($tmpDir);
    });

    it('merges JSON and XML cases', function () {
        $tmpDir = sys_get_temp_dir().'/eval_test_mixed_'.uniqid();
        mkdir($tmpDir);
        copy(fixturesPath('simple.case.json'), $tmpDir.'/geography.case.json');
        copy(fixturesPath('cases.case.xml'), $tmpDir.'/capitals.case.xml');

        $loader = new DatasetLoader;
        $cases = $loader->fromDirectory($tmpDir);

        expect($cases)->toHaveKey('geography')
            ->and($cases)->toHaveKey('capital-france')
            ->and($cases)->toHaveKey('capital-japan');

        // Cleanup
        unlink($tmpDir.'/geography.case.json');
        unlink($tmpDir.'/capitals.case.xml');
        rmdir($tmpDir);
    });

    it('throws for non-existent directory', function () {
        $loader = new DatasetLoader;

        expect(fn () => $loader->fromDirectory('/path/that/does/not/exist'))
            ->toThrow(InvalidArgumentException::class, 'does not exist');
    });

    it('returns empty array for directory with no case files', function () {
        $tmpDir = sys_get_temp_dir().'/eval_test_empty_'.uniqid();
        mkdir($tmpDir);

        $loader = new DatasetLoader;
        $cases = $loader->fromDirectory($tmpDir);

        expect($cases)->toBe([]);

        rmdir($tmpDir);
    });
});
