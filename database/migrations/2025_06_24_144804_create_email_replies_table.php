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
        Schema::create('email_replies', function (Blueprint $table) {
            $table->id();
            $table->string('email_id')->index()->comment('Unique identifier for the email from IMAP');
            $table->json('chat_history')->nullable()->comment('JSON encoded chat history between user and AI');
            $table->text('latest_ai_reply')->nullable()->comment('Latest AI generated reply');
            $table->timestamp('sent_at')->nullable()->comment('When the reply was sent');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_replies');
    }
};
