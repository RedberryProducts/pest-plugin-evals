<?php

declare(strict_types=1);

namespace Redberry\Evals;

use InvalidArgumentException;
use Laravel\Ai\Files;
use Redberry\Evals\Contracts\LoadsDatasets;
use RuntimeException;
use SimpleXMLElement;

final class DatasetLoader implements LoadsDatasets
{
    public function fromJson(string $path): EvalCase
    {
        $path = $this->resolvePath($path);

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read JSON file: {$path}");
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        if (! isset($data['prompt']) || ! is_string($data['prompt'])) {
            throw new InvalidArgumentException("JSON eval case must contain a 'prompt' string field: {$path}");
        }

        $case = EvalCase::make()->prompt($data['prompt']);

        if (array_key_exists('expected', $data)) {
            $case->expected($data['expected']);
        }

        if (isset($data['attachments']) && is_array($data['attachments'])) {
            /** @var array<int, array<string, string>> $attachments */
            $attachments = $data['attachments'];
            $case->attachments($this->resolveAttachments($attachments));
        }

        return $case;
    }

    /**
     * @return array<string, EvalCase>
     */
    public function fromXml(string $path): array
    {
        $path = $this->resolvePath($path);

        $xml = simplexml_load_file($path);

        if ($xml === false) {
            throw new RuntimeException("Unable to parse XML file: {$path}");
        }

        $cases = [];

        foreach ($xml->case as $caseNode) {
            $name = (string) ($caseNode['name'] ?? '');

            if ($name === '') {
                throw new InvalidArgumentException("Each <case> must have a 'name' attribute: {$path}");
            }

            $prompt = property_exists($caseNode, 'prompt') && $caseNode->prompt !== null ? (string) $caseNode->prompt : null;

            if ($prompt === null || $prompt === '') {
                throw new InvalidArgumentException("Each <case> must contain a <prompt> element: {$path}");
            }

            $case = EvalCase::make()->prompt($prompt);

            if (property_exists($caseNode, 'expected') && $caseNode->expected !== null) {
                $case->expected($this->parseExpectedXml($caseNode->expected));
            }

            if (property_exists($caseNode, 'attachments') && $caseNode->attachments !== null) {
                $case->attachments($this->resolveAttachmentsFromXml($caseNode->attachments));
            }

            $cases[$name] = $case;
        }

        return $cases;
    }

    /**
     * @return array<string, EvalCase>
     */
    public function fromDirectory(string $dir): array
    {
        $dir = $this->resolvePath($dir);

        if (! is_dir($dir)) {
            throw new InvalidArgumentException("Directory does not exist: {$dir}");
        }

        $cases = [];

        // Scan for *.case.json files
        $jsonFiles = glob($dir.'/*.case.json');
        if ($jsonFiles !== false) {
            foreach ($jsonFiles as $file) {
                $key = basename($file, '.case.json');
                $cases[$key] = $this->fromJson($file);
            }
        }

        // Scan for *.case.xml files
        $xmlFiles = glob($dir.'/*.case.xml');
        if ($xmlFiles !== false) {
            foreach ($xmlFiles as $file) {
                $xmlCases = $this->fromXml($file);
                $cases = array_merge($cases, $xmlCases);
            }
        }

        return $cases;
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * Parse the <expected> XML element into a string or associative array.
     *
     * @return string|array<string, mixed>
     */
    private function parseExpectedXml(SimpleXMLElement $expected): string|array
    {
        $children = $expected->children();

        if ($children->count() === 0) {
            return (string) $expected;
        }

        // Has child elements — parse as associative array (structured output)
        return $this->xmlToArray($expected);
    }

    /**
     * Recursively convert an XML element to an associative array.
     *
     * @return array<string, mixed>
     */
    private function xmlToArray(SimpleXMLElement $element): array
    {
        $result = [];

        foreach ($element->children() as $child) {
            $name = $child->getName();

            $result[$name] = $child->children()->count() > 0 ? $this->xmlToArray($child) : (string) $child;
        }

        return $result;
    }

    /**
     * Resolve JSON attachment descriptors into Laravel AI SDK file objects.
     *
     * @param  array<int, array<string, string>>  $attachments
     * @return array<int, mixed>
     */
    private function resolveAttachments(array $attachments): array
    {
        return array_map(fn (array $attachment): \Laravel\Ai\Files\Document|\Laravel\Ai\Files\Image => $this->resolveAttachment(
            type: $attachment['type'] ?? '',
            source: $attachment['source'] ?? '',
            path: $attachment['path'] ?? '',
        ), $attachments);
    }

    /**
     * Resolve XML attachment elements into Laravel AI SDK file objects.
     *
     * @return array<int, mixed>
     */
    private function resolveAttachmentsFromXml(SimpleXMLElement $attachmentsNode): array
    {
        $attachments = [];

        foreach ($attachmentsNode->attachment as $node) {
            $attachments[] = $this->resolveAttachment(
                type: (string) ($node['type'] ?? ''),
                source: (string) ($node['source'] ?? ''),
                path: (string) ($node['path'] ?? ''),
            );
        }

        return $attachments;
    }

    private function resolveAttachment(string $type, string $source, string $path): Files\Document|Files\Image
    {
        return match (true) {
            $type === 'document' && $source === 'storage' => Files\Document::fromStorage($path),
            $type === 'document' && $source === 'path' => Files\Document::fromPath($path),
            $type === 'image' && $source === 'storage' => Files\Image::fromStorage($path),
            $type === 'image' && $source === 'path' => Files\Image::fromPath($path),
            default => throw new InvalidArgumentException(
                "Invalid attachment: type='{$type}', source='{$source}'. Expected type 'document'|'image' and source 'storage'|'path'."
            ),
        };
    }
}
