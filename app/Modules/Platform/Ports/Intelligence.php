<?php

namespace App\Modules\Platform\Ports;

use Closure;

/**
 * Claude for the suite: reading a scanned document into fields, and answering a question with
 * tools the calling module provides. Modules never talk to the AI provider themselves.
 */
interface Intelligence
{
    /** Whether AI is configured for this installation. Callers hide AI features when it is not. */
    public function isEnabled(): bool;

    /**
     * Read images or PDFs and return data matching the JSON schema.
     *
     * @param  list<array{media_type: string, data: string}>  $files  base64-encoded
     * @param  array<string, mixed>  $schema  a JSON schema object with additionalProperties: false
     * @return array<string, mixed>
     *
     * @throws IntelligenceUnavailable
     */
    public function extract(string $instructions, array $files, array $schema): array;

    /**
     * Answer the last user message, calling the given tools as often as needed. $runTool gets a
     * tool name and its input and returns what the tool found (it enforces the asker's access).
     *
     * @param  list<array{role: 'user'|'assistant', content: string}>  $conversation
     * @param  list<array{name: string, description: string, input_schema: array<string, mixed>}>  $tools
     * @param  Closure(string, array<string, mixed>): mixed  $runTool
     *
     * @throws IntelligenceUnavailable
     */
    public function converse(string $system, array $conversation, array $tools, Closure $runTool): string;
}
