<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The company website Claude looked at while writing the email, and what it is built with.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('website_url', 500)->nullable()->after('company');
            $table->string('website_tech', 100)->nullable()->after('website_url');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['website_url', 'website_tech']);
        });
    }
};
