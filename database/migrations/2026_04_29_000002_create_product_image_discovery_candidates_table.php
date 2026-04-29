<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryCandidateStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_image_discovery_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')
                ->constrained('product_image_discovery_requests')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('fingerprint', 64)->nullable();
            $table->string('source_domain');
            $table->text('source_page_url');
            $table->text('image_url');
            $table->text('local_original_path')->nullable();
            $table->text('local_processed_path')->nullable();
            $table->string('source_resolver', 100)->nullable();
            $table->string('search_provider', 100)->nullable();
            $table->unsignedTinyInteger('source_trust_score')->default(0);
            $table->unsignedTinyInteger('textual_match_score')->default(0);
            $table->unsignedTinyInteger('structured_match_score')->default(0);
            $table->unsignedTinyInteger('visual_match_score')->default(0);
            $table->unsignedTinyInteger('quality_score')->default(0);
            $table->smallInteger('risk_penalty')->default(0);
            $table->unsignedTinyInteger('final_score')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->string('phash', 128)->nullable();
            $table->string('status', 50)->default(ProductImageDiscoveryCandidateStatus::Candidate->value);
            $table->string('rejection_reason', 100)->nullable();
            $table->json('evidence')->nullable();
            $table->json('structured_data')->nullable();
            $table->json('ai_analysis')->nullable();
            $table->json('quality_analysis')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('quality_checked_at')->nullable();
            $table->timestamps();

            $table->index(['request_id', 'final_score'], 'pidc_request_score_idx');
            $table->unique(['request_id', 'fingerprint'], 'pidc_request_fingerprint_uq');
            $table->index(['client_id', 'status', 'final_score'], 'pidc_client_status_score_idx');
            $table->index('sha256', 'pidc_sha256_idx');
            $table->index('phash', 'pidc_phash_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_image_discovery_candidates');
    }
};
