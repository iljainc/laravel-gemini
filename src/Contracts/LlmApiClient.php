<?php

namespace Idpromogroup\LaravelGemini\Contracts;

interface LlmApiClient
{
    public function getApiKey(): string;

    public function setTimeout(int $timeout): void;

    /**
     * @param  array{
     *     model: string,
     *     instructions?: string|null,
     *     contents: array,
     *     tools?: array,
     *     temperature?: float|null,
     *     response_format?: string|null,
     *     json_schema?: array|null
     * }  $request
     * @return array{model: string, output: array, usage: ?array, raw: array, model_content: ?array}|null
     */
    public function generate(array $request): ?array;

    /**
     * @return array{uri: string, mime_type: string, name: ?string}|null
     */
    public function uploadFile(string $path, ?string $mimeType = null): ?array;
}
