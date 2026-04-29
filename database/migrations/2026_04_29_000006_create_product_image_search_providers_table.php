<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_image_search_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100);
            $table->string('name');
            $table->string('driver', 100);
            $table->text('base_url')->nullable();
            $table->text('api_key_encrypted')->nullable();
            $table->text('api_secret_encrypted')->nullable();
            $table->json('config')->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->unsignedSmallInteger('timeout_seconds')->default(15);
            $table->unsignedInteger('rate_limit_per_minute')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('code', 'pisp_code_uq');
            $table->index(['is_active', 'priority'], 'pisp_active_priority_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_image_search_providers');
    }
};
