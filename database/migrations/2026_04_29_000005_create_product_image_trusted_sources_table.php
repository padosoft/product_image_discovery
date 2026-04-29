<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_image_trusted_sources', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('domain');
            $table->string('source_name')->nullable();
            $table->string('source_type', 80)->default('website');
            $table->unsignedTinyInteger('trust_score')->default(50);
            $table->boolean('allow_search')->default(true);
            $table->boolean('allow_scraping')->default(true);
            $table->boolean('allow_download')->default(true);
            $table->boolean('allow_auto_publish')->default(false);
            $table->boolean('allow_description_import')->default(false);
            $table->boolean('respect_robots_txt')->nullable();
            $table->boolean('requires_manual_review')->default(true);
            $table->unsignedInteger('rate_limit_per_minute')->nullable();
            $table->json('brand_scope')->nullable();
            $table->json('supplier_scope')->nullable();
            $table->json('url_patterns')->nullable();
            $table->text('permission_reference')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['client_id', 'domain'], 'pits_client_domain_uq');
            $table->index(['client_id', 'is_active', 'trust_score'], 'pits_active_trust_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_image_trusted_sources');
    }
};
