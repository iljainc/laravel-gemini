<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lg_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('instructions')->nullable();
            $table->string('model')->default('gemini-3.8-flash');
            $table->json('tools')->nullable();
            $table->decimal('temperature', 3, 2)->default(1.0);
            $table->string('response_format')->default('text');
            $table->json('json_schema')->nullable();
            $table->string('api_key')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('lg_template_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('template_id');
            $table->string('file_url');
            $table->string('file_name')->nullable();
            $table->string('file_type');
            $table->bigInteger('file_size')->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->string('mime_type')->nullable();
            $table->string('gemini_file_uri')->nullable();
            $table->enum('upload_status', ['pending', 'uploading', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('file_hash');
            $table->foreign('template_id')->references('id')->on('lg_templates')->onDelete('cascade');
        });

        Schema::create('lg_template_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->text('instructions')->nullable();
            $table->string('model')->default('gemini-3.8-flash');
            $table->json('tools')->nullable();
            $table->decimal('temperature', 3, 2)->default(1.0);
            $table->string('response_format')->default('text');
            $table->json('json_schema')->nullable();
            $table->string('api_key')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('lg_templates')->onDelete('cascade');
        });

        Schema::create('lg_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_id')->unique();
            $table->string('user');
            $table->enum('status', ['active', 'closed'])->default('active');
            $table->json('history')->nullable();
            $table->timestamps();

            $table->index(['user', 'status']);
        });

        Schema::create('lg_request_logs', function (Blueprint $table) {
            $table->id();
            $table->string('external_key');
            $table->string('model')->nullable();
            $table->text('request_text');
            $table->text('response_text')->nullable();
            $table->integer('pid')->nullable();
            $table->bigInteger('process_start_time')->nullable();
            $table->string('conversation_id')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed'])->default('pending');
            $table->text('comments')->nullable();
            $table->decimal('execution_time', 8, 2)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('cached_input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('reasoning_tokens')->nullable();
            $table->decimal('input_cost', 14, 8)->nullable();
            $table->decimal('cached_input_cost', 14, 8)->nullable();
            $table->decimal('output_cost', 14, 8)->nullable();
            $table->decimal('total_cost', 14, 8)->nullable();
            $table->unsignedTinyInteger('billing_source_code')->nullable();
            $table->unsignedBigInteger('billing_user')->nullable();
            $table->char('api_key_hash', 64)->nullable();
            $table->timestamps();

            $table->index('external_key');
            $table->index(['pid', 'process_start_time']);
            $table->index('conversation_id');
            $table->index(['model', 'created_at']);
            $table->index(['billing_source_code', 'created_at']);
            $table->index(['billing_user', 'created_at']);
            $table->index('api_key_hash');
        });

        Schema::create('lg_function_calls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_log_id');
            $table->string('external_key');
            $table->string('function_name');
            $table->json('arguments');
            $table->json('output')->nullable();
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->decimal('execution_time', 8, 2)->nullable();
            $table->timestamps();

            $table->index('external_key');
            $table->index('function_name');
            $table->foreign('request_log_id')->references('id')->on('lg_request_logs')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lg_function_calls');
        Schema::dropIfExists('lg_template_logs');
        Schema::dropIfExists('lg_request_logs');
        Schema::dropIfExists('lg_conversations');
        Schema::dropIfExists('lg_template_files');
        Schema::dropIfExists('lg_templates');
    }
};
