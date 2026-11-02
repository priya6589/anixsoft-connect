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
        // Message-ID of each sent email, so replies can be matched to it.
        Schema::table('email_drafts', function (Blueprint $table) {
            $table->string('message_id')->nullable()->after('sent_at')->index();
        });

        Schema::create('email_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_draft_id')->nullable()->constrained()->nullOnDelete();
            $table->string('message_id')->unique();
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_replies');

        Schema::table('email_drafts', function (Blueprint $table) {
            $table->dropColumn('message_id');
        });
    }
};
