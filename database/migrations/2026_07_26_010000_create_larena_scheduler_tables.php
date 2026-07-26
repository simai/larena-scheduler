<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larena_scheduler_schedules', static function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('name', 100);
            $table->string('operation_ref', 100);
            $table->string('actor_ref', 200);
            $table->string('timezone', 64);
            $table->string('frequency', 16);
            $table->char('local_time', 5)->nullable();
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->string('missed_run_strategy', 32);
            $table->unsignedSmallInteger('retention_count');
            $table->string('state', 16);
            $table->dateTime('next_run_at', 6);
            $table->dateTime('last_due_at', 6)->nullable();
            $table->dateTime('last_dispatched_at', 6)->nullable();
            $table->string('last_job_id', 32)->nullable();
            $table->string('last_job_status', 32)->nullable();
            $table->unsignedBigInteger('last_result_size_bytes')->nullable();
            $table->string('last_failure_reason', 100)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->index(['state', 'next_run_at'], 'larena_scheduler_due_index');
            $table->index('operation_ref', 'larena_scheduler_operation_index');
        });

        Schema::create('larena_scheduler_runs', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('schedule_id', 32);
            $table->dateTime('due_at', 6);
            $table->string('decision', 32);
            $table->string('tick_id', 64);
            $table->string('correlation_id', 64);
            $table->string('job_id', 32)->nullable();
            $table->string('reason_code', 100)->nullable();
            $table->dateTime('created_at', 6);
            $table->unique(['schedule_id', 'due_at'], 'larena_scheduler_run_due_unique');
            $table->index('job_id', 'larena_scheduler_run_job_index');
            $table->foreign('schedule_id', 'larena_scheduler_run_schedule_foreign')
                ->references('id')->on('larena_scheduler_schedules')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larena_scheduler_runs');
        Schema::dropIfExists('larena_scheduler_schedules');
    }
};
