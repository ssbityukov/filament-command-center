<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('command_center_commands', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->boolean('is_enabled')->default(true)->index();
            // The same array shape config uses, parsed by the same parser.
            $table->json('definition');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('command_center_commands');
    }
};
