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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_import_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('linkedin_url')->nullable()->unique();
            $table->string('email')->nullable()->index();
            $table->string('email_source', 20)->nullable();
            $table->string('company')->nullable();
            $table->string('position')->nullable();
            $table->date('connected_on')->nullable();
            $table->string('category', 50)->nullable()->index();
            $table->string('status', 30)->default('imported')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
