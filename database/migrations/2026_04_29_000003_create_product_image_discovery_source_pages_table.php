<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_image_discovery_source_pages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->char('url_hash', 64);
            $table->text('url');
            $table->string('domain');
            $table->string('fetch_strategy', 80)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('title')->nullable();
            $table->text('canonical_url')->nullable();
            $table->json('structured_data')->nullable();
            $table->mediumText('extracted_text')->nullable();
            $table->json('extracted_images')->nullable();
            $table->boolean('robots_allowed')->nullable();
            $table->text('crawl_error')->nullable();
            $table->timestamp('last_crawled_at')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'url_hash'], 'pidsp_client_url_hash_uq');
            $table->index(['client_id', 'domain', 'last_crawled_at'], 'pidsp_domain_crawled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_image_discovery_source_pages');
    }
};
