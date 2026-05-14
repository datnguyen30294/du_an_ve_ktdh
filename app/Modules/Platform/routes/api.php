<?php

use App\Modules\Platform\Ticket\Controllers\TicketController;
use App\Modules\Platform\Ticket\Controllers\TicketQuoteDecisionController;
use App\Modules\Platform\Ticket\Controllers\TicketRatingController;
use App\Modules\Platform\Ticket\Controllers\TicketWarrantyController;
use Illuminate\Support\Facades\Route;

Route::get('tickets/lookup', [TicketController::class, 'lookup']);
Route::post('tickets', [TicketController::class, 'submit']);

Route::get('tickets/{code}/rating', [TicketRatingController::class, 'show']);
Route::post('tickets/{code}/rating', [TicketRatingController::class, 'submit']);

Route::post('tickets/{code}/quote-decision', [TicketQuoteDecisionController::class, 'submit'])
    ->middleware('throttle:10,1');

Route::post('tickets/{code}/warranty', [TicketWarrantyController::class, 'submit'])
    ->middleware('throttle:5,1');
