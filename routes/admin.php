<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Larena\Scheduler\Http\Controllers\BackupScheduleController;

Route::get('/backup-schedules', [BackupScheduleController::class, 'index'])
    ->middleware('access:scheduler.schedule.list');
Route::post('/backup-schedules', [BackupScheduleController::class, 'create'])
    ->middleware('access:scheduler.schedule.create');
Route::get('/backup-schedules/{scheduleId}', [BackupScheduleController::class, 'show'])
    ->middleware('access:scheduler.schedule.view');
Route::put('/backup-schedules/{scheduleId}', [BackupScheduleController::class, 'update'])
    ->middleware('access:scheduler.schedule.update');
Route::post('/backup-schedules/{scheduleId}/enable', [BackupScheduleController::class, 'enable'])
    ->middleware('access:scheduler.schedule.enable');
Route::post('/backup-schedules/{scheduleId}/disable', [BackupScheduleController::class, 'disable'])
    ->middleware('access:scheduler.schedule.disable');
