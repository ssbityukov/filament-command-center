<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('command_center_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('command_key')->index();
            $table->string('label');
            // Nullable and unconstrained: a run outlives the user who started
            // it, and the audit trail must not disappear with the account.
            $table->string('user_id')->nullable()->index();
            $table->json('input');
            $table->json('argv');
            $table->string('state')->index();
            // Microsecond precision: two runs started in the same second are
            // otherwise indistinguishable, and history reads newest first.
            $table->timestamp('started_at', 6)->nullable()->index();
            $table->timestamp('finished_at', 6)->nullable()->index();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->integer('exit_code')->nullable();
            $table->longText('output')->nullable();
            $table->unsignedTinyInteger('progress')->nullable();
            $table->text('error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('command_center_runs');
    }
};
