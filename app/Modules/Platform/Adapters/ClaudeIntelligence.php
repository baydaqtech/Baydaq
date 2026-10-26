<?php

namespace App\Modules\Platform\Adapters;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use Anthropic\Lib\Tools\BetaRunnableTool;
use App\Modules\Platform\Ports\Intelligence;
use App\Modules\Platform\Ports\IntelligenceUnavailable;
use Closure;
use JsonException;

/**
 * Intelligence through Claude. A request the model declines for policy reasons is retried on
 * the server's default fallback model before it reaches us as a refusal.
 */
class ClaudeIntelligence implements Intelligence
{
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    private ?Client $client = null;

    public function __construct(private ?string $apiKey, private string $model) {}

    public function isEnabled(): bool
    {
        return (string) $this->apiKey !== '';
    }

    public function extract(string $instructions, array $files, array $schema): array
    {
        $content = array_map(fn (array $file): array => [
            'type' => $file['media_type'] === 'application/pdf' ? 'document' : 'image',
            'source' => ['type' => 'base64', 'media_type' => $file['media_type'], 'data' => $file['data']],
        ], $files);
        $content[] = ['type' => 'text', 'text' => $instructions];

        $message = $this->call(fn (Client $client): BetaMessage => $client->beta->messages->create(
            maxTokens: 4096,
            messages: [['role' => 'user', 'content' => $content]],
            model: $this->model,
            fallbacks: 'default',
            outputConfig: ['effort' => 'medium', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
            betas: [self::FALLBACK_BETA],
        ));

        try {
            return json_decode($this->text($message), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new IntelligenceUnavailable(__('suite.ai.unreadable'));
        }
    }

    public function converse(string $system, array $conversation, array $tools, Closure $runTool): string
    {
        $runnable = array_map(fn (array $tool): BetaRunnableTool => new BetaRunnableTool(
            definition: ['name' => $tool['name'], 'description' => $tool['description'], 'inputSchema' => $tool['input_schema']],
            run: fn (array $input): string => json_encode($runTool($tool['name'], $input), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}',
        ), $tools);

        $message = $this->call(fn (Client $client): BetaMessage => $client->beta->messages->toolRunner(
            maxTokens: 16000,
            messages: $conversation,
            model: $this->model,
            tools: $runnable,
            maxIterations: 8,
            extraParams: [
                'system' => $system,
                'fallbacks' => 'default',
                'outputConfig' => ['effort' => 'medium'],
                'betas' => [self::FALLBACK_BETA],
            ],
        )->runUntilDone());

        return $this->text($message);
    }

    /**
     * @param  Closure(Client): BetaMessage  $request
     */
    private function call(Closure $request): BetaMessage
    {
        if (! $this->isEnabled()) {
            throw new IntelligenceUnavailable(__('suite.ai.disabled'));
        }

        try {
            $message = $request($this->client ??= new Client(apiKey: $this->apiKey));
        } catch (APIException $exception) {
            report($exception);

            throw new IntelligenceUnavailable(__('suite.ai.failed'), previous: $exception);
        }

        if ($message->stopReason === 'refusal') {
            throw new IntelligenceUnavailable(__('suite.ai.declined'));
        }

        return $message;
    }

    private function text(BetaMessage $message): string
    {
        $text = '';

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return trim($text);
    }
}
