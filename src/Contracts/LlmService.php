<?php

namespace Idpromogroup\LaravelGemini\Contracts;

use Idpromogroup\LaravelGemini\Result;

interface LlmService
{
    public function setMessage(string $message): static;

    public function setModel(string $model): static;

    public function setInstructions(string $instructions): static;

    public function setTools(array $tools): static;

    public function setResponseFormat(string $format): static;

    public function setJSONSchema(array $schema): static;

    public function setTemperature(float $temperature): static;

    public function setTimeout(int $timeout): static;

    public function setConversation(string $user): static;

    public function setBillingContext(?int $billingSourceCode = null, ?int $billingUser = null): static;

    public function setApiKey(string $apiKey): static;

    public function attachLocalFile(string $absolutePath): static;

    public function useTemplate(int|string $template): static;

    public function execute(): Result;
}
