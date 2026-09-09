<?php

namespace Idpromogroup\LaravelGemini;

use Idpromogroup\LaravelGemini\Contracts\LlmApiClient;
use Idpromogroup\LaravelGemini\Contracts\LlmService;
use Idpromogroup\LaravelGemini\Models\LgConversation;
use Idpromogroup\LaravelGemini\Models\LgFunctionCall;
use Idpromogroup\LaravelGemini\Models\LgTemplate;
use Idpromogroup\LaravelGemini\Services\ProcessService;
use Idpromogroup\LaravelGemini\Services\UsageBillingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LgService implements LlmService
{
    private string $externalKey;
    private string $model = 'gemini-3.8-flash';
    private ?string $message = null;
    private ?ProcessService $processService = null;
    private ?string $instructions = null;
    private array $tools = [];
    private ?string $responseFormat = null;
    private ?array $jsonSchema = null;
    private ?float $temperature = null;
    private ?int $timeout = null;
    private ?string $conversationUser = null;
    private ?LgConversation $conversation = null;
    private array $attachments = [];
    private ?LgTemplate $template = null;
    private ?int $billingSourceCode = null;
    private ?int $billingUser = null;
    private ?string $apiKeyOverride = null;

    public function __construct(string $externalKey, ?string $message = null)
    {
        $this->externalKey = $externalKey;
        $this->model = (string) config('gemini.default_model', 'gemini-3.8-flash');
        if ($message !== null) {
            $this->message = $message;
        }
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function setModel(string $model): static
    {
        $this->model = $model;
        return $this;
    }

    public function setInstructions(string $instructions): static
    {
        $this->instructions = $instructions;
        return $this;
    }

    public function setTools(array $tools): static
    {
        $this->tools = $tools;
        return $this;
    }

    public function setResponseFormat(string $format): static
    {
        $this->responseFormat = $format;
        return $this;
    }

    public function setJSONSchema(array $schema): static
    {
        $this->jsonSchema = $schema;
        return $this;
    }

    public function setTemperature(float $temperature): static
    {
        $this->temperature = $temperature;
        return $this;
    }

    public function setTimeout(int $timeout): static
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function setConversation(string $user): static
    {
        $this->conversationUser = $user;
        return $this;
    }

    public function setBillingContext(?int $billingSourceCode = null, ?int $billingUser = null): static
    {
        $this->billingSourceCode = $billingSourceCode;
        $this->billingUser = $billingUser;
        return $this;
    }

    public function setApiKey(string $apiKey): static
    {
        $this->apiKeyOverride = $apiKey;
        return $this;
    }

    public static function hashApiKey(?string $apiKey): ?string
    {
        if ($apiKey === null || $apiKey === '') {
            return null;
        }

        return hash('sha256', $apiKey);
    }

    public function attachLocalFile(string $absolutePath): static
    {
        if (!file_exists($absolutePath)) {
            throw new \InvalidArgumentException("File not found: {$absolutePath}");
        }

        if (filesize($absolutePath) === 0) {
            throw new \InvalidArgumentException("Empty file: {$absolutePath}");
        }

        $mimeType = mime_content_type($absolutePath);
        $fileType = $mimeType ? $this->getFileTypeFromMime($mimeType) : null;
        if (!$fileType) {
            throw new \InvalidArgumentException(__("Unsupported format. Upload PDF or image (JPG/PNG/WEBP)."));
        }

        $attachment = [
            'path' => $absolutePath,
            'mime_type' => $mimeType,
            'type' => $fileType,
        ];

        if ($fileType === 'pdf') {
            $uploaded = $this->createApiService()->uploadFile($absolutePath, $mimeType);
            if (empty($uploaded['uri'])) {
                throw new \RuntimeException("Failed to upload file: {$absolutePath}");
            }
            $attachment['uri'] = $uploaded['uri'];
            $attachment['mime_type'] = $uploaded['mime_type'] ?? $mimeType;
        }

        $this->attachments[] = $attachment;
        lg_debug("LgService::attachLocalFile() {$absolutePath} ({$fileType})");

        return $this;
    }

    public function useTemplate(int|string $template): static
    {
        $templateModel = is_numeric($template)
            ? LgTemplate::find($template)
            : LgTemplate::where('name', $template)->first();

        if (!$templateModel) {
            throw new \InvalidArgumentException("Template not found: {$template}");
        }

        $this->template = $templateModel;

        if ($templateModel->instructions) {
            $this->instructions = $templateModel->instructions;
        }
        if ($templateModel->model) {
            $this->model = $templateModel->model;
        }
        if ($templateModel->tools) {
            $this->tools = $templateModel->tools;
        }
        if (!empty($templateModel->temperature)) {
            $this->temperature = $templateModel->temperature;
        }
        if ($templateModel->response_format) {
            $this->setResponseFormat($templateModel->response_format);
        }
        if ($templateModel->json_schema) {
            $this->setJSONSchema($templateModel->json_schema);
        }

        return $this;
    }

    public function execute(): Result
    {
        if (empty($this->message)) {
            throw new \InvalidArgumentException('Message must be set before execution');
        }

        lg_debug("LgService::execute() model={$this->model} key={$this->externalKey}");

        $startTime = microtime(true);
        $this->processService = app(ProcessService::class);

        if ($this->conversationUser) {
            $this->conversation = $this->getOrCreateConversation();
        }

        $contents = $this->buildContents();
        $requestData = $this->buildRequestData($contents);
        $logData = $this->buildRequestData($this->stripInlineData($contents));

        if (!$this->processService->init($this->externalKey, $logData, $this->billingLogAttributes())) {
            return Result::status('Already in work');
        }

        if ($this->conversation) {
            $this->processService->responseLog->update([
                'conversation_id' => $this->conversation->conversation_id,
            ]);
        }

        try {
            $api = $this->createApiService();
            if ($this->timeout !== null) {
                $api->setTimeout($this->timeout);
            }

            $response = $api->generate($requestData);
            if ($response === null) {
                $this->failProcess($startTime, 'Invalid or empty JSON from Gemini');
                return Result::failure('Invalid response from Gemini API');
            }

            $this->recordBilling($response);
            $this->processService->comment('SUCCESS: response received');

            $depth = 0;
            while ($this->extractFunctionCalls($response) !== [] && $depth < 8) {
                $depth++;
                $this->processService->comment('Run functions');
                $contents[] = $response['model_content'] ?? [
                    'role' => 'model',
                    'parts' => $this->functionCallParts($response),
                ];
                $contents[] = $this->runFunctionCalls($this->extractFunctionCalls($response));
                $requestData['contents'] = $contents;
                $response = $api->generate($requestData);
                if ($response === null) {
                    $this->failProcess($startTime, 'Invalid JSON after function calls');
                    return Result::failure('Invalid response from Gemini API');
                }
                $this->recordBilling($response);
            }

            if ($this->conversation) {
                $this->persistConversation($contents, $response);
            }

            $this->processService->close(
                json_encode($response, JSON_UNESCAPED_UNICODE),
                round(microtime(true) - $startTime, 2)
            );

            return Result::success($response);
        } catch (\Exception $e) {
            $errorMessage = $this->extractApiError($e);
            Log::error('LG: LgService::execute() failed - ' . $errorMessage);
            $this->failProcess($startTime, $errorMessage);
            return Result::failure('API Error: ' . $errorMessage);
        }
    }

    public function getInputText(): array
    {
        return $this->buildContents();
    }

    private function getFileTypeFromMime(string $mimeType): ?string
    {
        $allowedImageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (in_array($mimeType, $allowedImageTypes, true)) {
            return 'image';
        }
        if ($mimeType === 'application/pdf') {
            return 'pdf';
        }

        return null;
    }

    private function buildContents(): array
    {
        $contents = [];

        if ($this->conversation && is_array($this->conversation->history)) {
            $contents = array_slice($this->conversation->history, -30);
        }

        $parts = [];
        if ($this->message) {
            $parts[] = ['text' => $this->message];
        }

        foreach ($this->attachments as $attachment) {
            if (($attachment['type'] ?? null) === 'image') {
                $parts[] = [
                    'inlineData' => [
                        'mimeType' => $attachment['mime_type'],
                        'data' => base64_encode(file_get_contents($attachment['path'])),
                    ],
                ];
            } elseif (!empty($attachment['uri'])) {
                $parts[] = [
                    'fileData' => [
                        'mimeType' => $attachment['mime_type'],
                        'fileUri' => $attachment['uri'],
                    ],
                ];
            }
        }

        $contents[] = ['role' => 'user', 'parts' => $parts];

        return $contents;
    }

    private function stripInlineData(array $contents): array
    {
        foreach ($contents as &$turn) {
            foreach ($turn['parts'] ?? [] as $i => $part) {
                if (isset($part['inlineData']) || isset($part['inline_data'])) {
                    $meta = $part['inlineData'] ?? $part['inline_data'];
                    $turn['parts'][$i] = [
                        'inlineData' => [
                            'mimeType' => $meta['mimeType'] ?? $meta['mime_type'] ?? 'unknown',
                            'data' => '[omitted]',
                        ],
                    ];
                }
            }
        }

        return $contents;
    }

    private function buildRequestData(array $contents): array
    {
        return [
            'model' => $this->model,
            'instructions' => $this->instructions,
            'contents' => $contents,
            'tools' => $this->tools,
            'temperature' => $this->temperature,
            'response_format' => $this->responseFormat,
            'json_schema' => $this->jsonSchema,
        ];
    }

    private function extractFunctionCalls(array $response): array
    {
        $calls = [];
        foreach ($response['output'] ?? [] as $item) {
            if (($item['type'] ?? null) === 'function_call') {
                $calls[] = $item;
            }
        }

        return $calls;
    }

    private function functionCallParts(array $response): array
    {
        $parts = [];
        foreach ($this->extractFunctionCalls($response) as $call) {
            $parts[] = [
                'functionCall' => [
                    'name' => $call['name'],
                    'args' => $call['arguments'] ?? [],
                ],
            ];
        }

        return $parts;
    }

    private function runFunctionCalls(array $toolCalls): array
    {
        $parts = [];

        foreach ($toolCalls as $toolCall) {
            $functionName = $toolCall['name'] ?? null;
            $args = $toolCall['arguments'] ?? [];
            if (!$functionName) {
                continue;
            }

            $startTime = microtime(true);
            $functionLog = LgFunctionCall::create([
                'request_log_id' => $this->processService->responseLog->id,
                'external_key' => $this->externalKey,
                'function_name' => $functionName,
                'arguments' => $args,
                'status' => LgFunctionCall::STATUS_PENDING,
            ]);

            try {
                $handlerClass = config('gemini.function_handler');
                if ($handlerClass && class_exists($handlerClass)) {
                    $result = app($handlerClass)->execute($functionName, $args);
                } else {
                    $result = ['error' => 'Function handler not configured or not found'];
                }

                $functionLog->update([
                    'output' => $result,
                    'status' => LgFunctionCall::STATUS_SUCCESS,
                    'execution_time' => round(microtime(true) - $startTime, 2),
                ]);
            } catch (\Exception $e) {
                $result = ['error' => $e->getMessage()];
                $functionLog->update([
                    'status' => LgFunctionCall::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                    'execution_time' => round(microtime(true) - $startTime, 2),
                ]);
                Log::error("LG: Function call failed - {$functionName}: " . $e->getMessage());
            }

            $parts[] = [
                'functionResponse' => [
                    'name' => $functionName,
                    'response' => $this->functionResponsePayload($result),
                ],
            ];
        }

        return ['role' => 'user', 'parts' => $parts];
    }

    private function functionResponsePayload(mixed $result): array
    {
        if (!is_array($result) || $result === [] || array_is_list($result)) {
            return ['result' => $result];
        }

        return $result;
    }

    private function getOrCreateConversation(): LgConversation
    {
        $conversation = LgConversation::where('user', $this->conversationUser)
            ->where('status', LgConversation::STATUS_ACTIVE)
            ->first();

        if ($conversation) {
            return $conversation;
        }

        return LgConversation::create([
            'conversation_id' => (string) Str::uuid(),
            'user' => $this->conversationUser,
            'status' => LgConversation::STATUS_ACTIVE,
            'history' => [],
        ]);
    }

    private function persistConversation(array $contents, array $response): void
    {
        $history = $contents;
        if (!empty($response['model_content'])) {
            $history[] = $response['model_content'];
        } elseif ($msg = (new Result(true, $response))->getMsg()) {
            $history[] = ['role' => 'model', 'parts' => [['text' => $msg]]];
        }

        $stripped = [];
        foreach (array_slice($history, -30) as $turn) {
            $parts = [];
            foreach ($turn['parts'] ?? [] as $part) {
                if (isset($part['inlineData']) || isset($part['inline_data'])) {
                    continue;
                }
                $parts[] = $part;
            }
            if ($parts === [] && isset($turn['parts'])) {
                $parts[] = ['text' => '[attachment]'];
            }
            $stripped[] = [
                'role' => $turn['role'] ?? 'user',
                'parts' => $parts,
            ];
        }

        $this->conversation->update(['history' => $stripped]);
    }

    private function resolveApiKey(): string
    {
        if ($this->apiKeyOverride !== null && $this->apiKeyOverride !== '') {
            return $this->apiKeyOverride;
        }

        if ($this->template !== null) {
            return $this->template->getApiKey();
        }

        return (string) (config('gemini.api_key') ?: env('GEMINI_API_KEY', ''));
    }

    private function createApiService(): LlmApiClient
    {
        return app(LlmApiClient::class, [
            'apiKey' => $this->resolveApiKey(),
            'timeout' => $this->timeout,
        ]);
    }

    private function billingLogAttributes(): array
    {
        $data = [];

        if ($this->billingSourceCode !== null) {
            $data['billing_source_code'] = $this->billingSourceCode;
        }
        if ($this->billingUser !== null) {
            $data['billing_user'] = $this->billingUser;
        }
        $hash = self::hashApiKey($this->resolveApiKey());
        if ($hash !== null) {
            $data['api_key_hash'] = $hash;
        }

        return $data;
    }

    private function recordBilling(array $response): void
    {
        try {
            app(UsageBillingService::class)->record(
                $this->processService->responseLog,
                $response,
                $this->model
            );
        } catch (\Throwable $e) {
            Log::warning('LG: usage billing skipped', ['error' => $e->getMessage()]);
        }
    }

    private function failProcess(float $startTime, string $message): void
    {
        $this->processService->close($message, round(microtime(true) - $startTime, 2), ProcessService::STATUS_FAILED);
        $this->processService->comment('FAILED: ' . $message);
    }

    private function extractApiError(\Exception $e): string
    {
        if ($e instanceof \GuzzleHttp\Exception\ClientException && $e->hasResponse()) {
            $body = $e->getResponse()->getBody()->getContents();
            $json = json_decode($body, true);
            if (is_array($json) && isset($json['error']['message'])) {
                return $json['error']['message'];
            }
        }

        return $e->getMessage();
    }
}
