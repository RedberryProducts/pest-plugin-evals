<?php

declare(strict_types=1);

namespace Redberry\Evals\Contracts;

use Redberry\Evals\EvalCase;

interface LoadsDatasets
{
    /**
     * Load a single case from a JSON file.
     */
    public function fromJson(string $path): EvalCase;

    /**
     * Load multiple cases from an XML file.
     *
     * @return array<string, EvalCase>
     */
    public function fromXml(string $path): array;

    /**
     * Auto-discover *.case.json and *.case.xml files in a directory.
     *
     * @return array<string, EvalCase>
     */
    public function fromDirectory(string $dir): array;
}
