<?php

namespace Idpromogroup\LaravelGemini\Services;

use Idpromogroup\LaravelGemini\Models\LgRequestLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessService
{
    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    public $responseLog;
    private $lockFp = null;

    public function init(string $externalKey, $requestText, array $logAttributes = []): bool
    {
        $pid = getmypid();

        $requestTextJson = is_array($requestText)
            ? json_encode($requestText, JSON_UNESCAPED_UNICODE)
            : $requestText;

        $this->responseLog = LgRequestLog::create(array_merge([
            'external_key' => $externalKey,
            'request_text' => $requestTextJson,
            'status' => self::STATUS_PENDING,
            'pid' => $pid,
            'process_start_time' => null,
        ], $logAttributes));

        $lockFile = storage_path('app/temp/gemini_lock_' . md5($externalKey . '|' . $requestTextJson));
        $lockDir = dirname($lockFile);
        if (!is_dir($lockDir)) {
            mkdir($lockDir, 0755, true);
        }

        $this->lockFp = fopen($lockFile, 'c');
        if (!$this->lockFp) {
            $this->responseLog->update(['status' => self::STATUS_FAILED]);
            $this->comment('FAILED: Cannot create lock file');
            return false;
        }

        if (!flock($this->lockFp, LOCK_EX | LOCK_NB)) {
            fclose($this->lockFp);
            $this->lockFp = null;
            $this->responseLog->update(['status' => self::STATUS_FAILED]);
            $this->comment('REJECTED: Another process is actively working (file lock held)');
            return false;
        }

        touch($lockFile);
        $this->responseLog->update(['status' => self::STATUS_IN_PROGRESS]);
        $this->comment('SUCCESS: Process started, ready to work');
        lg_debug("ProcessService::init() log ID: {$this->responseLog->id}");

        return true;
    }

    public function comment(string $text): void
    {
        if (!$this->responseLog) {
            lg_debug_error("ProcessService::comment() - No active response log: {$text}");
            return;
        }

        try {
            DB::transaction(function () use ($text) {
                $currentLog = LgRequestLog::find($this->responseLog->id);
                if ($currentLog) {
                    $timestamp = Carbon::now()->format('m-d H:i:s.v');
                    $currentLog->update([
                        'comments' => ($currentLog->comments ?? '') . "[{$timestamp}] {$text}\n",
                    ]);
                    $this->responseLog = $currentLog;
                }
            });
        } catch (\Exception $e) {
            lg_debug_error("Failed to add comment - Log ID: {$this->responseLog->id}, Error: {$e->getMessage()}");
            Log::error('LG: ProcessService comment failed', [
                'response_log_id' => $this->responseLog->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function close(?string $responseText = null, ?float $executionTime = null, string $status = self::STATUS_COMPLETED): void
    {
        if ($this->responseLog) {
            $updateData = ['status' => $status];
            if ($responseText !== null) {
                $updateData['response_text'] = $responseText;
            }
            if ($executionTime !== null) {
                $updateData['execution_time'] = $executionTime;
            }
            $this->responseLog->update($updateData);
            $this->comment($status === self::STATUS_FAILED ? 'Process failed' : 'Process completed');
        }

        if ($this->lockFp) {
            fclose($this->lockFp);
            $this->lockFp = null;
        }
    }
}
