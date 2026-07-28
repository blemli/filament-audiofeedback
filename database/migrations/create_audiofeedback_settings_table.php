<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audiofeedback_settings', function (Blueprint $table) {
            $table->id();
            // A string survives integer and UUID user keys alike.
            $table->string('user_id')->unique();
            $table->json('settings');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audiofeedback_settings');
    }
};
