# Laravel Gemini

Laravel-пакет для Gemini (`generateContent`). Снаружи те же методы, что были у `LorService`: `setModel`, `execute`, `attachLocalFile` и т.д.

## Install

Пакет: `idpromogroup/laravel-gemini`. Провайдер подхватывается сам (Laravel package discovery).

Если пакет ещё не на Packagist — из GitHub:

```bash
composer require idpromogroup/laravel-gemini:dev-main
```

в `composer.json` хоста:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/iljainc/laravel-gemini.git"
    }
  ]
}
```

`.env`:

```
GEMINI_API_KEY=
GEMINI_MODEL=gemini-2.5-flash
```

Миграции грузятся из пакета, публиковать их не обязательно:

```bash
php artisan migrate
```

Конфиг — только если хотите править у себя в `config/gemini.php`:

```bash
php artisan vendor:publish --tag=config --provider="Idpromogroup\LaravelGemini\LaravelGeminiServiceProvider"
```

## Usage

```php
use Idpromogroup\LaravelGemini\LgService;

$service = new LgService($externalKey, 'Your message here');
$result = $service->setModel('gemini-2.5-flash')
    ->setInstructions('You are a helpful assistant')
    ->execute();

if ($result->success) {
    echo $result->getMsg();
}
```

`LgService` создаёте через `new`, в контейнер он не биндится (нужен `$externalKey`). Контракт: `Idpromogroup\LaravelGemini\Contracts\LlmService`.

Файлы: JPG/PNG/WEBP (inline), PDF (Files API).

```php
$service = new LgService($externalKey, 'Analyze this document')
    ->attachLocalFile(storage_path('app/documents/report.pdf'))
    ->execute();
```

Шаблон: `->useTemplate('name_or_id')` (таблица `lg_templates`).

Диалог (история в `lg_conversations`, не OpenAI conversation id):

```php
$service = new LgService($externalKey, 'Hello')
    ->setConversation('user123')
    ->execute();
```

Function handler — `config/gemini.php` (`function_handler`) или `GEMINI_FUNCTION_HANDLER`:

```php
class MyFunctionHandler
{
    public function execute(string $functionName, array $args) { /* ... */ }
}
```

Биллинг:

```php
use Idpromogroup\LaravelGemini\Facades\Lg;

Lg::usage()->forExternalKey($externalKey)->sum('total_cost');
```

Выключить: `GEMINI_BILLING=false`.

HTTP-клиент уже забинжен на `GeminiApiService`. Менять его нужно только ради своего адаптера:

```php
$this->app->bind(
    \Idpromogroup\LaravelGemini\Contracts\LlmApiClient::class,
    \App\Services\MyGeminiClient::class
);
```

Класс должен реализовать `LlmApiClient` (`generate`, `uploadFile`, `setTimeout`, `getApiKey`).
