<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->unsignedTinyInteger('ai_score')->nullable()->after('category')->index();
            $table->string('ai_reason', 500)->nullable()->after('ai_score');
            $table->timestamp('classified_at')->nullable()->after('ai_reason')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['ai_score', 'ai_reason', 'classified_at']);
        });
    }
};
