<?php

use App\Modules\PMC\ExternalApi\Controllers\ExtAccountController;
use App\Modules\PMC\ExternalApi\Controllers\ExtDepartmentController;
use App\Modules\PMC\ExternalApi\Controllers\ExtJobTitleController;
use App\Modules\PMC\ExternalApi\Controllers\ExtProjectController;
use App\Modules\PMC\ExternalApi\Controllers\ExtShiftController;
use App\Modules\PMC\ExternalApi\Controllers\ExtWorkScheduleController;
use Illuminate\Support\Facades\Route;

Route::apiResource('departments', ExtDepartmentController::class)->names('ext.departments');
Route::apiResource('accounts', ExtAccountController::class)->names('ext.accounts');
Route::apiResource('job-titles', ExtJobTitleController::class)->names('ext.job-titles');
Route::apiResource('projects', ExtProjectController::class)->names('ext.projects');

Route::apiResource('shifts', ExtShiftController::class)
    ->parameters(['shifts' => 'id'])
    ->names('ext.shifts');

Route::post('work-schedules/bulk-upsert', [ExtWorkScheduleController::class, 'bulkUpsert'])->name('ext.work-schedules.bulk-upsert');
Route::apiResource('work-schedules', ExtWorkScheduleController::class)
    ->parameters(['work-schedules' => 'id'])
    ->names('ext.work-schedules');
