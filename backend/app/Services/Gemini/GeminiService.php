<?php

namespace App\Services\Gemini;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Gemini AI service for text generation and analysis.
 *
 * Provides methods for interacting with the Gemini API including
 * text generation, chat, and structured output extraction.
 *
 * Usage:
 *   $gemini = app(GeminiService::class);
 *   $response = $gemini->generateText('Explain quantum computing');
 */
class GeminiService
{
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
    protected ?string $apiKey;
    protected string $model;
    protected int $maxTokens;
    protected float $temperature;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.model', 'gemini-2.0-flash');
        $this->maxTokens = config('services.gemini.max_tokens', 8192);
        $this->temperature = config('services.gemini.temperature', 0.7);
    }

    /**
     * Generate text from a prompt.
     */
    public function generateText(string $prompt, array $options = []): GeminiResponse
    {
        if (!$this->isConfigured()) {
            return GeminiResponse::error('Gemini API not configured');
        }

        try {
            $response = Http::timeout(60)->post(
                "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}",
                [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => $options['temperature'] ?? $this->temperature,
                        'maxOutputTokens' => $options['max_tokens'] ?? $this->maxTokens,
                        'topP' => $options['top_p'] ?? 0.95,
                        'topK' => $options['top_k'] ?? 40,
                    ],
                ]
            );

            if ($response->failed()) {
                $error = $response->json('error.message') ?? 'Unknown error';
                Log::error('GeminiService: API request failed', [
                    'error' => $error,
                    'status' => $response->status(),
                ]);

                return GeminiResponse::error($error, $response->json() ?? []);
            }

            $data = $response->json();
            $candidate = $data['candidates'][0] ?? null;

            if (!$candidate) {
                return GeminiResponse::error('No response generated', $data);
            }

            $text = $candidate['content']['parts'][0]['text'] ?? '';
            $finishReason = $candidate['finishReason'] ?? null;
            $usageMetadata = $data['usageMetadata'] ?? [];

            Log::info('GeminiService: Text generated', [
                'prompt_length' => strlen($prompt),
                'response_length' => strlen($text),
                'finish_reason' => $finishReason,
            ]);

            return GeminiResponse::text(
                text: $text,
                promptTokens: $usageMetadata['promptTokenCount'] ?? null,
                completionTokens: $usageMetadata['candidatesTokenCount'] ?? null,
                finishReason: $finishReason,
                raw: $data
            );
        } catch (\Exception $e) {
            Log::error('GeminiService: Exception during generation', [
                'error' => $e->getMessage(),
            ]);

            return GeminiResponse::error($e->getMessage());
        }
    }

    /**
     * Chat with conversation history.
     *
     * @param array $messages Array of ['role' => 'user'|'model', 'content' => 'text']
     */
    public function chat(array $messages, array $options = []): GeminiResponse
    {
        if (!$this->isConfigured()) {
            return GeminiResponse::error('Gemini API not configured');
        }

        try {
            $contents = array_map(function ($message) {
                return [
                    'role' => $message['role'] === 'assistant' ? 'model' : $message['role'],
                    'parts' => [
                        ['text' => $message['content']],
                    ],
                ];
            }, $messages);

            $response = Http::timeout(60)->post(
                "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}",
                [
                    'contents' => $contents,
                    'generationConfig' => [
                        'temperature' => $options['temperature'] ?? $this->temperature,
                        'maxOutputTokens' => $options['max_tokens'] ?? $this->maxTokens,
                        'topP' => $options['top_p'] ?? 0.95,
                        'topK' => $options['top_k'] ?? 40,
                    ],
                ]
            );

            if ($response->failed()) {
                $error = $response->json('error.message') ?? 'Unknown error';

                return GeminiResponse::error($error, $response->json() ?? []);
            }

            $data = $response->json();
            $candidate = $data['candidates'][0] ?? null;

            if (!$candidate) {
                return GeminiResponse::error('No response generated', $data);
            }

            $text = $candidate['content']['parts'][0]['text'] ?? '';
            $finishReason = $candidate['finishReason'] ?? null;
            $usageMetadata = $data['usageMetadata'] ?? [];

            return GeminiResponse::text(
                text: $text,
                promptTokens: $usageMetadata['promptTokenCount'] ?? null,
                completionTokens: $usageMetadata['candidatesTokenCount'] ?? null,
                finishReason: $finishReason,
                raw: $data
            );
        } catch (\Exception $e) {
            Log::error('GeminiService: Chat exception', [
                'error' => $e->getMessage(),
            ]);

            return GeminiResponse::error($e->getMessage());
        }
    }

    /**
     * Generate text with a system instruction.
     */
    public function generateWithSystem(string $systemPrompt, string $userPrompt, array $options = []): GeminiResponse
    {
        if (!$this->isConfigured()) {
            return GeminiResponse::error('Gemini API not configured');
        }

        try {
            $response = Http::timeout(60)->post(
                "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}",
                [
                    'systemInstruction' => [
                        'parts' => [
                            ['text' => $systemPrompt],
                        ],
                    ],
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $userPrompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => $options['temperature'] ?? $this->temperature,
                        'maxOutputTokens' => $options['max_tokens'] ?? $this->maxTokens,
                        'topP' => $options['top_p'] ?? 0.95,
                        'topK' => $options['top_k'] ?? 40,
                    ],
                ]
            );

            if ($response->failed()) {
                $error = $response->json('error.message') ?? 'Unknown error';

                return GeminiResponse::error($error, $response->json() ?? []);
            }

            $data = $response->json();
            $candidate = $data['candidates'][0] ?? null;

            if (!$candidate) {
                return GeminiResponse::error('No response generated', $data);
            }

            $text = $candidate['content']['parts'][0]['text'] ?? '';
            $finishReason = $candidate['finishReason'] ?? null;
            $usageMetadata = $data['usageMetadata'] ?? [];

            return GeminiResponse::text(
                text: $text,
                promptTokens: $usageMetadata['promptTokenCount'] ?? null,
                completionTokens: $usageMetadata['candidatesTokenCount'] ?? null,
                finishReason: $finishReason,
                raw: $data
            );
        } catch (\Exception $e) {
            Log::error('GeminiService: System prompt exception', [
                'error' => $e->getMessage(),
            ]);

            return GeminiResponse::error($e->getMessage());
        }
    }

    /**
     * Extract structured JSON data from text.
     */
    public function extractJson(string $text, string $schema, array $options = []): GeminiResponse
    {
        $prompt = <<<PROMPT
Extract structured data from the following text and return it as valid JSON matching this schema:

Schema:
{$schema}

Text to analyze:
{$text}

Return ONLY valid JSON, no markdown formatting or explanation.
PROMPT;

        $response = $this->generateText($prompt, array_merge($options, [
            'temperature' => 0.1, // Lower temperature for more consistent extraction
        ]));

        if ($response->isError()) {
            return $response;
        }

        // Try to parse the JSON
        $jsonText = trim($response->getText());

        // Remove markdown code blocks if present
        if (str_starts_with($jsonText, '```')) {
            $jsonText = preg_replace('/^```(?:json)?\s*/', '', $jsonText);
            $jsonText = preg_replace('/\s*```$/', '', $jsonText);
        }

        $decoded = json_decode($jsonText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return GeminiResponse::error('Failed to parse JSON: ' . json_last_error_msg(), [
                'raw_text' => $response->getText(),
            ]);
        }

        return GeminiResponse::success($decoded, $response->raw);
    }

    /**
     * Analyze an image with a prompt.
     */
    public function analyzeImage(string $imagePath, string $prompt, array $options = []): GeminiResponse
    {
        if (!$this->isConfigured()) {
            return GeminiResponse::error('Gemini API not configured');
        }

        if (!file_exists($imagePath)) {
            return GeminiResponse::error("Image file not found: {$imagePath}");
        }

        try {
            $imageData = base64_encode(file_get_contents($imagePath));
            $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';

            $response = Http::timeout(120)->post(
                "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}",
                [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'inlineData' => [
                                        'mimeType' => $mimeType,
                                        'data' => $imageData,
                                    ],
                                ],
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => $options['temperature'] ?? $this->temperature,
                        'maxOutputTokens' => $options['max_tokens'] ?? $this->maxTokens,
                    ],
                ]
            );

            if ($response->failed()) {
                $error = $response->json('error.message') ?? 'Unknown error';

                return GeminiResponse::error($error, $response->json() ?? []);
            }

            $data = $response->json();
            $candidate = $data['candidates'][0] ?? null;

            if (!$candidate) {
                return GeminiResponse::error('No response generated', $data);
            }

            $text = $candidate['content']['parts'][0]['text'] ?? '';
            $finishReason = $candidate['finishReason'] ?? null;
            $usageMetadata = $data['usageMetadata'] ?? [];

            return GeminiResponse::text(
                text: $text,
                promptTokens: $usageMetadata['promptTokenCount'] ?? null,
                completionTokens: $usageMetadata['candidatesTokenCount'] ?? null,
                finishReason: $finishReason,
                raw: $data
            );
        } catch (\Exception $e) {
            Log::error('GeminiService: Image analysis exception', [
                'error' => $e->getMessage(),
            ]);

            return GeminiResponse::error($e->getMessage());
        }
    }

    /**
     * Analyze image from base64 data.
     */
    public function analyzeImageBase64(string $base64Data, string $mimeType, string $prompt, array $options = []): GeminiResponse
    {
        if (!$this->isConfigured()) {
            return GeminiResponse::error('Gemini API not configured');
        }

        try {
            $response = Http::timeout(120)->post(
                "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}",
                [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'inlineData' => [
                                        'mimeType' => $mimeType,
                                        'data' => $base64Data,
                                    ],
                                ],
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => $options['temperature'] ?? $this->temperature,
                        'maxOutputTokens' => $options['max_tokens'] ?? $this->maxTokens,
                    ],
                ]
            );

            if ($response->failed()) {
                $error = $response->json('error.message') ?? 'Unknown error';

                return GeminiResponse::error($error, $response->json() ?? []);
            }

            $data = $response->json();
            $candidate = $data['candidates'][0] ?? null;

            if (!$candidate) {
                return GeminiResponse::error('No response generated', $data);
            }

            $text = $candidate['content']['parts'][0]['text'] ?? '';
            $usageMetadata = $data['usageMetadata'] ?? [];

            return GeminiResponse::text(
                text: $text,
                promptTokens: $usageMetadata['promptTokenCount'] ?? null,
                completionTokens: $usageMetadata['candidatesTokenCount'] ?? null,
                finishReason: $candidate['finishReason'] ?? null,
                raw: $data
            );
        } catch (\Exception $e) {
            Log::error('GeminiService: Image analysis exception', [
                'error' => $e->getMessage(),
            ]);

            return GeminiResponse::error($e->getMessage());
        }
    }

    /**
     * List available models.
     */
    public function listModels(): GeminiResponse
    {
        if (!$this->isConfigured()) {
            return GeminiResponse::error('Gemini API not configured');
        }

        try {
            $response = Http::get("{$this->baseUrl}/models?key={$this->apiKey}");

            if ($response->failed()) {
                $error = $response->json('error.message') ?? 'Unknown error';

                return GeminiResponse::error($error, $response->json() ?? []);
            }

            $data = $response->json();
            $models = array_map(function ($model) {
                return [
                    'name' => $model['name'],
                    'display_name' => $model['displayName'] ?? null,
                    'description' => $model['description'] ?? null,
                    'input_token_limit' => $model['inputTokenLimit'] ?? null,
                    'output_token_limit' => $model['outputTokenLimit'] ?? null,
                    'supported_methods' => $model['supportedGenerationMethods'] ?? [],
                ];
            }, $data['models'] ?? []);

            return GeminiResponse::success(['models' => $models], $data);
        } catch (\Exception $e) {
            return GeminiResponse::error($e->getMessage());
        }
    }

    /**
     * Check if the service is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Get the current model name.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Set a different model for this instance.
     */
    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }
}
