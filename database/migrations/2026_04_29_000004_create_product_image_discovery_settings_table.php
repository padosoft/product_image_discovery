<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_image_discovery_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('setting_key', 150);
            $table->json('setting_value');
            $table->string('value_type', 50)->default('json');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['client_id', 'setting_key'], 'pids_client_setting_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_image_discovery_settings');
    }
};
