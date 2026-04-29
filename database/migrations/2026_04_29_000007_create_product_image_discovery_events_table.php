<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_image_discovery_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')
                ->constrained('product_image_discovery_requests')
                ->cascadeOnDelete();
            $table->foreignId('candidate_id')
                ->nullable()
                ->constrained('product_image_discovery_candidates')
                ->nullOnDelete();
            $table->string('event_type', 100);
            $table->string('level', 30)->default('info');
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['request_id', 'created_at'], 'pide_request_created_idx');
            $table->index(['candidate_id', 'created_at'], 'pide_candidate_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_image_discovery_events');
    }
};
