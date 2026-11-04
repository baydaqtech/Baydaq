<?php

namespace Tests\Fakes;

use App\Modules\Platform\Ports\Intelligence;
use Closure;

/**
 * Stands in for Claude in tests: extract() returns the prepared answer, converse() runs the
 * prepared tool calls through the real tools and answers with what they returned.
 */
class FakeIntelligence implements Intelligence
{
    /** @var list<array{instructions: string, files: int, schema: array<string, mixed>}> */
    public array $extractions = [];

    /** @var list<array{name: string, input: array<string, mixed>, result: mixed}> */
    public array $toolResults = [];

    /**
     * @param  array<string, mixed>  $extracted
     * @param  list<array{0: string, 1: array<string, mixed>}>  $toolCalls
     */
    public function __construct(public array $extracted = [], public array $toolCalls = [], public bool $enabled = true) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function extract(string $instructions, array $files, array $schema): array
    {
        $this->extractions[] = ['instructions' => $instructions, 'files' => count($files), 'schema' => $schema];

        return $this->extracted;
    }

    public function converse(string $system, array $conversation, array $tools, Closure $runTool): string
    {
        foreach ($this->toolCalls as [$name, $input]) {
            $this->toolResults[] = ['name' => $name, 'input' => $input, 'result' => $runTool($name, $input)];
        }

        return (string) json_encode(array_column($this->toolResults, 'result'), JSON_UNESCAPED_UNICODE);
    }
}
