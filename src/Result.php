<?php

namespace Idpromogroup\LaravelGemini;

class Result
{
    public function __construct(
        public bool $success,
        public mixed $data = null,
        public ?string $error = null,
        public ?string $status = null
    ) {}

    public function successful(): bool
    {
        return $this->success;
    }

    public function failed(): bool
    {
        return !$this->success;
    }

    public static function success(mixed $data, ?string $status = null): self
    {
        return new self(true, $data, null, $status);
    }

    public static function failure(string $error, ?string $status = null): self
    {
        return new self(false, null, $error, $status);
    }

    public static function status(string $status, mixed $data = null): self
    {
        return new self(false, $data, null, $status);
    }

    public function getMsg(): ?string
    {
        if (!$this->success || !$this->data) {
            return null;
        }

        if (isset($this->data['output']) && is_array($this->data['output'])) {
            foreach ($this->data['output'] as $item) {
                if (($item['type'] ?? null) === 'message' && isset($item['content'][0]['text'])) {
                    return $item['content'][0]['text'];
                }
            }
        }

        return null;
    }

    public function getAssistantJSON(): ?array
    {
        if (!$this->success || !$this->data) {
            return null;
        }

        if (isset($this->data['output']) && is_array($this->data['output'])) {
            foreach ($this->data['output'] as $item) {
                if (($item['type'] ?? null) === 'message') {
                    if (isset($item['content'][0]['parsed']) && is_array($item['content'][0]['parsed'])) {
                        return $item['content'][0]['parsed'];
                    }
                    if (isset($item['content'][0]['text'])) {
                        $decoded = json_decode($item['content'][0]['text'], true);
                        return is_array($decoded) ? $decoded : null;
                    }
                }
            }
        }

        return null;
    }

    public function getMessages(): array
    {
        if (!$this->success || !$this->data) {
            return [];
        }

        return $this->data['output'] ?? [];
    }

    public function getUsage(): ?array
    {
        if (!$this->success || !$this->data) {
            return null;
        }

        return $this->data['usage'] ?? null;
    }

    public function getModel(): ?string
    {
        if (!$this->success || !$this->data) {
            return null;
        }

        return $this->data['model'] ?? null;
    }

    public function hasFunctionCalls(): bool
    {
        if (!$this->success || !$this->data) {
            return false;
        }

        if (isset($this->data['output']) && is_array($this->data['output'])) {
            foreach ($this->data['output'] as $item) {
                if (($item['type'] ?? null) === 'function_call') {
                    return true;
                }
            }
        }

        return false;
    }

    public function getFunctionCalls(): array
    {
        if (!$this->hasFunctionCalls()) {
            return [];
        }

        $functionCalls = [];
        foreach ($this->data['output'] as $item) {
            if (($item['type'] ?? null) === 'function_call') {
                $functionCalls[] = $item;
            }
        }

        return $functionCalls;
    }
}
