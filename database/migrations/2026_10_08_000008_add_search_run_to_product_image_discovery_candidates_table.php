<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_image_discovery_candidates', function (Blueprint $table): void {
            // Search run (request attempt) that produced the candidate; null for candidates found
            // before runs were tracked.
            $table->unsignedInteger('search_run')->nullable()->after('request_id');
            $table->index(['request_id', 'search_run'], 'pidc_request_run_idx');
        });
    }

    public function down(): void
    {
        Schema::table('product_image_discovery_candidates', function (Blueprint $table): void {
            $table->dropIndex('pidc_request_run_idx');
            $table->dropColumn('search_run');
        });
    }
};
