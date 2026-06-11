<?php

declare(strict_types=1);

namespace Elgentos\UntranslatedStrings\Model\Translation;

use Elgentos\UntranslatedStrings\Model\Config;
use Magento\Framework\Exception\LocalizedException;

/**
 * Translates batches of UI strings with Claude (Anthropic API) using
 * structured output so the response is guaranteed to be parseable JSON.
 */
class ClaudeTranslator
{
    private const MAX_TOKENS = 16000;

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a professional translator for Magento e-commerce stores. You translate short user-interface strings.

Rules:
- Translate each source string into the requested locale, in natural wording appropriate for an online store.
- Preserve placeholders exactly as they appear: %1 through %9, %s, %d and named placeholders such as %store_name.
- Preserve HTML tags and their attributes; translate only the human-readable text.
- Preserve leading and trailing whitespace and the punctuation style of the source string.
- Keep brand names, product names and SKUs untranslated.
- If a string is ambiguous, choose the most common e-commerce meaning.
- Return a translation for every source string. Never add commentary.
PROMPT;

    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * @param string[] $strings
     * @return array<string, string> Map of source string => translation
     * @throws LocalizedException
     */
    public function translate(array $strings, string $locale): array
    {
        $strings = array_values(array_filter($strings, static fn (string $s): bool => trim($s) !== ''));
        if ($strings === []) {
            return [];
        }

        $apiKey = $this->config->getApiKey();
        if ($apiKey === '') {
            throw new LocalizedException(__(
                'The Anthropic API key is not configured. Set it under Stores → Configuration → Advanced → Untranslated Strings.'
            ));
        }

        if (!class_exists(\Anthropic\Client::class)) {
            throw new LocalizedException(__(
                'The anthropic-ai/sdk composer package is not installed. Run "composer require anthropic-ai/sdk".'
            ));
        }

        $client = new \Anthropic\Client(apiKey: $apiKey);

        try {
            $message = $client->messages->create(
                model: $this->config->getModel(),
                maxTokens: self::MAX_TOKENS,
                system: self::SYSTEM_PROMPT,
                messages: [
                    ['role' => 'user', 'content' => $this->buildPrompt($strings, $locale)],
                ],
                outputConfig: ['format' => $this->buildOutputFormat()],
            );
        } catch (\Throwable $exception) {
            throw new LocalizedException(__('Anthropic API error: %1', $exception->getMessage()));
        }

        if ($message->stopReason === 'refusal') {
            throw new LocalizedException(__('The model declined to translate this batch.'));
        }

        if ($message->stopReason === 'max_tokens') {
            throw new LocalizedException(__('The model response was truncated. Queue fewer strings at once.'));
        }

        $payload = null;
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $payload = json_decode($block->text, true);
                break;
            }
        }

        if (!is_array($payload) || !isset($payload['translations']) || !is_array($payload['translations'])) {
            throw new LocalizedException(__('Could not parse the model response.'));
        }

        $requested = array_flip($strings);
        $map = [];
        foreach ($payload['translations'] as $row) {
            $source = $row['source'] ?? null;
            $translation = $row['translation'] ?? null;
            if (is_string($source)
                && is_string($translation)
                && $translation !== ''
                && isset($requested[$source])
            ) {
                $map[$source] = $translation;
            }
        }

        return $map;
    }

    /**
     * @param string[] $strings
     */
    private function buildPrompt(array $strings, string $locale): string
    {
        return sprintf(
            "Translate the following Magento store user-interface strings into the locale \"%s\".\n"
            . "Return a translation for every source string.\n\nSource strings (JSON array):\n%s",
            $locale,
            json_encode($strings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOutputFormat(): array
    {
        return [
            'type' => 'json_schema',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'translations' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'source' => ['type' => 'string'],
                                'translation' => ['type' => 'string'],
                            ],
                            'required' => ['source', 'translation'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required' => ['translations'],
                'additionalProperties' => false,
            ],
        ];
    }
}
