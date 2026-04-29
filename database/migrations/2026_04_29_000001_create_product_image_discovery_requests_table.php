<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Padosoft\ProductImageDiscovery\Enums\ProductImageDiscoveryRequestStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_image_discovery_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('erp_model_id', 128);
            $table->string('erp_model_color_id', 128);
            $table->string('erp_model_color_size_id', 128)->nullable();
            $table->char('identity_hash', 64)->nullable();
            $table->string('brand')->nullable();
            $table->string('supplier')->nullable();
            $table->string('sku')->nullable();
            $table->string('supplier_sku')->nullable();
            $table->string('model_code')->nullable();
            $table->string('color_code')->nullable();
            $table->string('color_name')->nullable();
            $table->string('ean', 64)->nullable();
            $table->string('season', 64)->nullable();
            $table->string('category')->nullable();
            $table->string('material')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->json('raw_payload');
            $table->string('status', 50)->default(ProductImageDiscoveryRequestStatus::Pending->value);
            $table->unsignedBigInteger('best_candidate_id')->nullable();
            $table->unsignedBigInteger('selected_candidate_id')->nullable();
            $table->unsignedTinyInteger('final_score')->nullable();
            $table->string('rejection_reason', 100)->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('search_started_at')->nullable();
            $table->timestamp('search_completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'erp_model_color_id'], 'pidr_client_model_color_uq');
            $table->index(['client_id', 'status', 'created_at'], 'pidr_client_status_created_idx');
            $table->index(['client_id', 'brand', 'model_code'], 'pidr_brand_model_idx');
            $table->index(['client_id', 'ean'], 'pidr_ean_idx');
            $table->index(['client_id', 'supplier_sku'], 'pidr_supplier_sku_idx');
            $table->index('best_candidate_id', 'pidr_best_candidate_idx');
            $table->index('selected_candidate_id', 'pidr_selected_candidate_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_image_discovery_requests');
    }
};
