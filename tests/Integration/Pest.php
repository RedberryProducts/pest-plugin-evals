<?php

use Tests\TestCase;

uses(TestCase::class)
    ->beforeEach(function () {
        $keyFile = dirname(__DIR__, 2).'/openai-api-key.php';

        if (! file_exists($keyFile)) {
            $this->markTestSkipped(
                'OpenAI API key file not found. Create openai-api-key.php in project root.'
            );
        }

        $key = require $keyFile;
        config()->set('ai.providers.openai.key', $key);
    })
    ->in(__DIR__);
