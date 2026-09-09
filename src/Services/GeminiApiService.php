<?php

namespace Idpromogroup\LaravelGemini\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Idpromogroup\LaravelGemini\Contracts\LlmApiClient;
use Illuminate\Support\Facades\Log;

class GeminiApiService implements LlmApiClient
{
    public const DEFAULT_TIMEOUT = 60;

    private Client $client;
    private string $apiKey;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/';
    private int $timeout;

    public function __construct(?string $apiKey = null, ?int $timeout = null)
    {
        $this->apiKey = $apiKey ?? (string) config('gemini.api_key');
        $this->timeout = $timeout ?? (int) config('gemini.timeout', self::DEFAULT_TIMEOUT);
        $this->initClient();
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
        $this->initClient();
    }

    public function generate(array $request): ?array
    {
        $model = $request['model'] ?? config('gemini.default_model', 'gemini-2.5-flash');
        $body = $this->buildGenerateBody($request);

        lg_debug("GeminiApiService::generate() model={$model}");

        $response = $this->client->post("v1beta/models/{$model}:generateContent", [
            'json' => $body,
        ]);

        $raw = json_decode($response->getBody()->getContents(), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($raw)) {
            Log::error('LG: JSON decode error - ' . json_last_error_msg());
            return null;
        }

        if (isset($raw['error'])) {
            Log::error('LG: Gemini API error - ' . ($raw['error']['message'] ?? json_encode($raw['error'])));
            return null;
        }

        return $this->normalizeResponse($raw, $model);
    }

    public function uploadFile(string $path, ?string $mimeType = null): ?array
    {
        if (!file_exists($path) || filesize($path) === 0) {
            Log::error("LG: uploadFile failed - missing or empty: {$path}");
            return null;
        }

        $mimeType = $mimeType ?: (mime_content_type($path) ?: 'application/octet-stream');
        $numBytes = filesize($path);

        try {
            $start = $this->client->post('upload/v1beta/files', [
                'headers' => [
                    'X-Goog-Upload-Protocol' => 'resumable',
                    'X-Goog-Upload-Command' => 'start',
                    'X-Goog-Upload-Header-Content-Length' => (string) $numBytes,
                    'X-Goog-Upload-Header-Content-Type' => $mimeType,
                    'Content-Type' => 'application/json',
                ],
                'json' => ['file' => ['display_name' => basename($path)]],
            ]);

            $uploadUrl = $start->getHeaderLine('X-Goog-Upload-URL');
            if ($uploadUrl === '') {
                Log::error('LG: uploadFile failed - no upload URL');
                return null;
            }

            $uploadClient = new Client([
                'timeout' => 300,
                'headers' => ['x-goog-api-key' => $this->apiKey],
            ]);

            $resp = $uploadClient->post($uploadUrl, [
                'headers' => [
                    'Content-Length' => (string) $numBytes,
                    'X-Goog-Upload-Offset' => '0',
                    'X-Goog-Upload-Command' => 'upload, finalize',
                ],
                'body' => fopen($path, 'r'),
            ]);

            $json = json_decode($resp->getBody()->getContents(), true);
            $file = $json['file'] ?? $json;
            $name = $file['name'] ?? null;

            if ($name) {
                $file = $this->waitUntilActive($name) ?? $file;
            }

            return [
                'uri' => $file['uri'] ?? null,
                'mime_type' => $file['mimeType'] ?? $mimeType,
                'name' => $name,
            ];
        } catch (\Throwable $e) {
            Log::error('LG: uploadFile failed - ' . $e->getMessage());
            return null;
        }
    }

    private function initClient(): void
    {
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->timeout,
            'headers' => [
                'x-goog-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    private function buildGenerateBody(array $request): array
    {
        $body = [
            'contents' => $request['contents'] ?? [],
        ];

        if (!empty($request['instructions'])) {
            $body['systemInstruction'] = [
                'parts' => [['text' => $request['instructions']]],
            ];
        }

        $tools = $this->mapTools($request['tools'] ?? []);
        if ($tools !== []) {
            $body['tools'] = $tools;
        }

        $generationConfig = [];
        if (isset($request['temperature']) && $request['temperature'] !== null) {
            $generationConfig['temperature'] = $request['temperature'];
        }

        $format = $request['response_format'] ?? 'text';
        if ($format === 'json_schema' || $format === 'json_object') {
            $generationConfig['responseMimeType'] = 'application/json';
            $schema = $this->extractJsonSchema($request['json_schema'] ?? null);
            if ($schema) {
                $generationConfig['responseSchema'] = $schema;
            }
        }

        if ($generationConfig !== []) {
            $body['generationConfig'] = $generationConfig;
        }

        return $body;
    }

    private function mapTools(array $tools): array
    {
        if ($tools === []) {
            return [];
        }

        if (isset($tools[0]['functionDeclarations'])) {
            return $tools;
        }

        $declarations = [];
        foreach ($tools as $tool) {
            if (($tool['type'] ?? null) === 'file_search') {
                continue;
            }

            $fn = $tool['function'] ?? $tool;
            if (empty($fn['name'])) {
                continue;
            }

            $decl = [
                'name' => $fn['name'],
                'description' => $fn['description'] ?? '',
            ];

            if (!empty($fn['parameters'])) {
                $decl['parameters'] = $fn['parameters'];
            }

            $declarations[] = $decl;
        }

        return $declarations === [] ? [] : [['functionDeclarations' => $declarations]];
    }

    private function extractJsonSchema(?array $schema): ?array
    {
        if ($schema === null || $schema === []) {
            return null;
        }

        if (isset($schema['schema']) && is_array($schema['schema'])) {
            return $schema['schema'];
        }

        return $schema;
    }

    /**
     * @return array{model: string, output: array, usage: ?array, raw: array, model_content: ?array}
     */
    private function normalizeResponse(array $raw, string $model): array
    {
        $candidate = $raw['candidates'][0] ?? [];
        $content = $candidate['content'] ?? null;
        $parts = $content['parts'] ?? [];

        $text = '';
        $output = [];

        foreach ($parts as $i => $part) {
            if (isset($part['text']) && $part['text'] !== '') {
                $text .= $part['text'];
            }

            if (isset($part['functionCall']) && is_array($part['functionCall'])) {
                $fc = $part['functionCall'];
                $args = $fc['args'] ?? [];
                $output[] = [
                    'type' => 'function_call',
                    'name' => $fc['name'] ?? '',
                    'arguments' => is_array($args) ? $args : [],
                    'call_id' => ($fc['name'] ?? 'fn') . '_' . $i,
                ];
            }
        }

        if ($text !== '') {
            $parsed = json_decode($text, true);
            array_unshift($output, [
                'type' => 'message',
                'content' => [[
                    'text' => $text,
                    'parsed' => is_array($parsed) ? $parsed : null,
                ]],
            ]);
        }

        $usageMeta = $raw['usageMetadata'] ?? null;
        $usage = null;
        if (is_array($usageMeta)) {
            $usage = [
                'input_tokens' => (int) ($usageMeta['promptTokenCount'] ?? 0),
                'output_tokens' => (int) ($usageMeta['candidatesTokenCount'] ?? 0),
                'input_tokens_details' => [
                    'cached_tokens' => (int) ($usageMeta['cachedContentTokenCount'] ?? 0),
                ],
            ];
        }

        return [
            'model' => $raw['modelVersion'] ?? $model,
            'output' => $output,
            'usage' => $usage,
            'raw' => $raw,
            'model_content' => $content,
        ];
    }

    private function waitUntilActive(string $name): ?array
    {
        for ($i = 0; $i < 20; $i++) {
            try {
                $resp = $this->client->get('v1beta/' . ltrim($name, '/'));
                $json = json_decode($resp->getBody()->getContents(), true);
                $file = $json['file'] ?? $json;
                $state = $file['state'] ?? null;
                if ($state === 'ACTIVE' || $state === 'STATE_UNSPECIFIED') {
                    return $file;
                }
                if ($state === 'FAILED') {
                    Log::error('LG: uploaded file failed processing');
                    return $file;
                }
            } catch (GuzzleException $e) {
                Log::warning('LG: file poll failed - ' . $e->getMessage());
                return null;
            }
            usleep(500000);
        }

        return null;
    }
}
