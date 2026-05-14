<?php

use App\Modules\PMC\AcceptanceReport\Controllers\PublicAcceptanceReportController;
use App\Modules\PMC\Catalog\Controllers\PublicServiceController;
use App\Modules\PMC\OgTicket\Controllers\PublicOgTicketSurveyController;
use App\Modules\PMC\Policy\Controllers\PublicPolicyController;
use Illuminate\Support\Facades\Route;

Route::get('services', [PublicServiceController::class, 'index']);
Route::get('services/{slug}', [PublicServiceController::class, 'show']);
Route::get('policies/{type}', [PublicPolicyController::class, 'show']);

Route::get('acceptance-reports/{token}', [PublicAcceptanceReportController::class, 'show']);
Route::patch('acceptance-reports/{token}', [PublicAcceptanceReportController::class, 'update']);
Route::post('acceptance-reports/{token}/confirm', [PublicAcceptanceReportController::class, 'confirm']);

Route::get('tickets/{code}/survey', [PublicOgTicketSurveyController::class, 'show']);
Route::post('tickets/{code}/survey', [PublicOgTicketSurveyController::class, 'upsert']);
Route::delete('tickets/{code}/survey/attachments/{attachmentId}', [PublicOgTicketSurveyController::class, 'deleteAttachment']);
