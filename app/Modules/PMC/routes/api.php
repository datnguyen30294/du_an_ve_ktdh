<?php

use App\Modules\PMC\AcceptanceReport\Controllers\AcceptanceReportController;
use App\Modules\PMC\Account\Controllers\PermissionController;
use App\Modules\PMC\Account\Controllers\RoleController;
use App\Modules\PMC\Catalog\Controllers\CatalogItemController;
use App\Modules\PMC\Catalog\Controllers\CatalogSupplierController;
use App\Modules\PMC\Catalog\Controllers\ServiceCategoryController;
use App\Modules\PMC\ClosingPeriod\Controllers\ClosingPeriodController;
use App\Modules\PMC\ClosingPeriod\Controllers\CommissionSummaryController;
use App\Modules\PMC\Commission\Controllers\CommissionConfigController;
use App\Modules\PMC\Customer\Controllers\CustomerController;
use App\Modules\PMC\Department\Controllers\DepartmentController;
use App\Modules\PMC\JobTitle\Controllers\JobTitleController;
use App\Modules\PMC\OgTicket\Controllers\OgTicketController;
use App\Modules\PMC\OgTicket\Controllers\OgTicketSurveyController;
use App\Modules\PMC\OgTicketCategory\Controllers\OgTicketCategoryController;
use App\Modules\PMC\Order\AdvancePayment\Controllers\AdvancePaymentController;
use App\Modules\PMC\Order\Controllers\OrderCommissionOverrideController;
use App\Modules\PMC\Order\Controllers\OrderController;
use App\Modules\PMC\Policy\Controllers\PolicyController;
use App\Modules\PMC\Project\Controllers\ProjectController;
use App\Modules\PMC\Quote\Controllers\QuoteController;
use App\Modules\PMC\Receivable\Controllers\ReceivableController;
use App\Modules\PMC\Reconciliation\Controllers\ReconciliationController;
use App\Modules\PMC\Report\CashFlow\Controllers\CashFlowReportController;
use App\Modules\PMC\Report\Commission\Controllers\CommissionReportController;
use App\Modules\PMC\Report\Csat\Controllers\CsatReportController;
use App\Modules\PMC\Report\OperatingProfit\Controllers\OperatingProfitReportController;
use App\Modules\PMC\Report\Overview\Controllers\OverviewReportController;
use App\Modules\PMC\Report\RevenueProfit\Controllers\RevenueProfitReportController;
use App\Modules\PMC\Report\RevenueTicket\Controllers\RevenueTicketReportController;
use App\Modules\PMC\Report\Sla\Controllers\SlaReportController;
use App\Modules\PMC\Setting\Controllers\SystemSettingController;
use App\Modules\PMC\Shift\Controllers\ShiftController;
use App\Modules\PMC\Treasury\Controllers\CashAccountController;
use App\Modules\PMC\Treasury\Controllers\CashTransactionController;
use App\Modules\PMC\Treasury\Controllers\TreasurySummaryController;
use App\Modules\PMC\WorkforceCapacity\Controllers\WorkforceCapacityController;
use App\Modules\PMC\WorkSchedule\Controllers\ScheduleSlotController;
use App\Modules\PMC\WorkSchedule\Controllers\WorkScheduleController;
use Illuminate\Support\Facades\Route;

Route::get('customers/{id}/check-delete', [CustomerController::class, 'checkDelete']);
Route::get('customers/{id}/tickets', [CustomerController::class, 'tickets']);
Route::get('customers/{id}/orders', [CustomerController::class, 'orders']);
Route::get('customers/{id}/payments', [CustomerController::class, 'payments']);
Route::apiResource('customers', CustomerController::class)->parameters(['customers' => 'id']);

Route::apiResource('departments', DepartmentController::class)->parameters(['departments' => 'id']);
Route::get('departments/{id}/descendant-ids', [DepartmentController::class, 'descendantIds']);
Route::get('job-titles/{id}/check-delete', [JobTitleController::class, 'checkDelete']);
Route::apiResource('job-titles', JobTitleController::class)->parameters(['job-titles' => 'id']);
Route::apiResource('projects', ProjectController::class)->parameters(['projects' => 'id']);
Route::put('projects/{id}/sync-members', [ProjectController::class, 'syncMembers']);
Route::get('shifts/stats', [ShiftController::class, 'stats']);
Route::apiResource('shifts', ShiftController::class)->parameters(['shifts' => 'id']);
Route::apiResource('work-schedules', WorkScheduleController::class)
    ->parameters(['work-schedules' => 'id'])
    ->only(['index', 'show']);

Route::get('schedule-slots/personal', [ScheduleSlotController::class, 'personal']);
Route::get('schedule-slots/team', [ScheduleSlotController::class, 'team']);
Route::get('schedule-slots/detail', [ScheduleSlotController::class, 'detail']);
Route::get('workforce/capacity', [WorkforceCapacityController::class, 'index']);
Route::apiResource('roles', RoleController::class)->parameters(['roles' => 'id']);
Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');

Route::prefix('og-tickets')->group(function (): void {
    Route::get('/pool', [OgTicketController::class, 'pool']);
    Route::post('/claim', [OgTicketController::class, 'claim']);
    Route::post('/', [OgTicketController::class, 'store']);
    Route::get('/', [OgTicketController::class, 'index']);
    Route::get('/{id}', [OgTicketController::class, 'show']);
    Route::put('/{id}', [OgTicketController::class, 'update']);
    Route::get('/{id}/check-delete', [OgTicketController::class, 'checkDelete']);
    Route::delete('/{id}', [OgTicketController::class, 'destroy']);
    Route::put('/{id}/transition', [OgTicketController::class, 'transition']);
    Route::put('/{id}/release', [OgTicketController::class, 'release']);
    Route::get('/{id}/audits', [OgTicketController::class, 'audits']);
    Route::put('/{id}/categories', [OgTicketController::class, 'syncCategories']);
    Route::get('/{id}/survey', [OgTicketSurveyController::class, 'show']);
    Route::post('/{id}/survey', [OgTicketSurveyController::class, 'upsert']);
    Route::delete('/{id}/survey/attachments/{attachmentId}', [OgTicketSurveyController::class, 'deleteAttachment']);
});

Route::get('og-ticket-categories/{id}/check-delete', [OgTicketCategoryController::class, 'checkDelete']);
Route::apiResource('og-ticket-categories', OgTicketCategoryController::class)->parameters(['og-ticket-categories' => 'id']);

Route::prefix('catalog')->group(function (): void {
    Route::get('suppliers/{id}/check-delete', [CatalogSupplierController::class, 'checkDelete']);
    Route::apiResource('suppliers', CatalogSupplierController::class)->parameters(['suppliers' => 'id']);
    Route::get('service-categories/{id}/check-delete', [ServiceCategoryController::class, 'checkDelete']);
    Route::post('service-categories/{id}/image', [ServiceCategoryController::class, 'uploadImage']);
    Route::delete('service-categories/{id}/image', [ServiceCategoryController::class, 'deleteImage']);
    Route::apiResource('service-categories', ServiceCategoryController::class)->parameters(['service-categories' => 'id']);
    Route::post('items/{id}/image', [CatalogItemController::class, 'uploadImage']);
    Route::delete('items/{id}/image', [CatalogItemController::class, 'deleteImage']);
    Route::post('items/{id}/gallery', [CatalogItemController::class, 'uploadGallery']);
    Route::delete('items/{id}/gallery/{imageId}', [CatalogItemController::class, 'deleteGalleryImage']);
    Route::apiResource('items', CatalogItemController::class)->parameters(['items' => 'id']);
});

Route::get('quotes/check-active', [QuoteController::class, 'checkActive']);
Route::get('quotes/versions/{ogTicketId}', [QuoteController::class, 'versions']);
Route::get('quotes/{id}/check-delete', [QuoteController::class, 'checkDelete']);
Route::get('quotes/{id}/audits', [QuoteController::class, 'audits']);
Route::post('quotes/{id}/transition', [QuoteController::class, 'transition']);
Route::apiResource('quotes', QuoteController::class)->parameters(['quotes' => 'id']);

Route::get('orders/available-quotes', [OrderController::class, 'availableQuotes']);
Route::get('orders/{id}/check-delete', [OrderController::class, 'checkDelete']);
Route::post('orders/{id}/transition', [OrderController::class, 'transition']);
Route::get('orders/active-accounts', [OrderController::class, 'activeAccounts']);
Route::patch('orders/{id}/lines/{lineId}/advance-payer', [OrderController::class, 'setAdvancePayer']);
Route::patch('orders/{id}/lines/{lineId}/prices', [OrderController::class, 'updateLinePrices']);
Route::get('orders/{id}/commission-override', [OrderCommissionOverrideController::class, 'show']);
Route::put('orders/{id}/commission-override', [OrderCommissionOverrideController::class, 'save']);
Route::delete('orders/{id}/commission-override', [OrderCommissionOverrideController::class, 'destroy']);
Route::get('orders/{id}/commission-snapshot', [\App\Modules\PMC\ClosingPeriod\Controllers\OrderCommissionSnapshotController::class, 'show']);
Route::get('orders/{id}/acceptance-report', [AcceptanceReportController::class, 'show']);
Route::put('orders/{id}/acceptance-report', [AcceptanceReportController::class, 'update']);
Route::post('orders/{id}/acceptance-report/regenerate', [AcceptanceReportController::class, 'regenerate']);
Route::post('orders/{id}/acceptance-report/signed', [AcceptanceReportController::class, 'uploadSigned']);
Route::delete('orders/{id}/acceptance-report/signed', [AcceptanceReportController::class, 'deleteSigned']);
Route::delete('orders/{id}/acceptance-report', [AcceptanceReportController::class, 'destroy']);
Route::apiResource('orders', OrderController::class)->parameters(['orders' => 'id']);

Route::prefix('advance-payments')->group(function (): void {
    Route::get('/', [AdvancePaymentController::class, 'index']);
    Route::get('/stats', [AdvancePaymentController::class, 'stats']);
    Route::get('/history', [AdvancePaymentController::class, 'history']);
    Route::post('/', [AdvancePaymentController::class, 'store']);
    Route::post('/batch', [AdvancePaymentController::class, 'storeBatch']);
    Route::delete('/{id}', [AdvancePaymentController::class, 'destroy']);
});

Route::get('receivables/summary', [ReceivableController::class, 'summary']);
Route::get('receivables/{id}/audits', [ReceivableController::class, 'audits']);
Route::post('receivables/{id}/payments', [ReceivableController::class, 'recordPayment']);
Route::put('receivables/{id}/payments/{paymentId}', [ReceivableController::class, 'updatePayment']);
Route::post('receivables/{id}/refund', [ReceivableController::class, 'recordRefund']);
Route::post('receivables/{id}/complete', [ReceivableController::class, 'markCompleted']);
Route::post('receivables/{id}/write-off', [ReceivableController::class, 'writeOff']);
Route::apiResource('receivables', ReceivableController::class)
    ->parameters(['receivables' => 'id'])
    ->only(['index', 'show']);

Route::prefix('reconciliations')->group(function (): void {
    Route::get('/summary', [ReconciliationController::class, 'summary']);
    Route::post('/batch-reconcile', [ReconciliationController::class, 'batchReconcile']);
    Route::post('/{id}/reconcile', [ReconciliationController::class, 'reconcile']);
    Route::post('/{id}/reject', [ReconciliationController::class, 'reject']);
    Route::get('/', [ReconciliationController::class, 'index']);
    Route::get('/{id}', [ReconciliationController::class, 'show']);
});

Route::prefix('settings')->group(function (): void {
    Route::get('/{group}', [SystemSettingController::class, 'show']);
    Route::put('/{group}', [SystemSettingController::class, 'update']);
});

Route::prefix('policies')->group(function (): void {
    Route::get('/', [PolicyController::class, 'index']);
    Route::post('/upload-image', [PolicyController::class, 'uploadImage']);
    Route::get('/{type}', [PolicyController::class, 'show']);
    Route::put('/{type}', [PolicyController::class, 'update']);
});

Route::prefix('closing-periods')->group(function (): void {
    Route::get('/', [ClosingPeriodController::class, 'index']);
    Route::post('/', [ClosingPeriodController::class, 'store']);
    Route::get('/{id}', [ClosingPeriodController::class, 'show']);
    Route::delete('/{id}', [ClosingPeriodController::class, 'destroy']);
    Route::get('/{id}/eligible-orders', [ClosingPeriodController::class, 'eligibleOrders']);
    Route::post('/{id}/add-orders', [ClosingPeriodController::class, 'addOrders']);
    Route::delete('/{id}/orders/{orderId}', [ClosingPeriodController::class, 'removeOrder']);
    Route::post('/{id}/close', [ClosingPeriodController::class, 'close']);
    Route::post('/{id}/reopen', [ClosingPeriodController::class, 'reopen']);
});

Route::get('commission-summary', [CommissionSummaryController::class, 'index']);
Route::patch('commission-summary/payout', [CommissionSummaryController::class, 'updatePayout']);

Route::prefix('commission')->group(function (): void {
    Route::get('projects', [CommissionConfigController::class, 'listProjects']);
    Route::get('projects/{projectId}', [CommissionConfigController::class, 'showConfig']);
    Route::put('projects/{projectId}', [CommissionConfigController::class, 'saveConfig']);
    Route::get('projects/{projectId}/adjusters', [CommissionConfigController::class, 'getAdjusters']);
    Route::put('projects/{projectId}/adjusters', [CommissionConfigController::class, 'saveAdjusters']);
    Route::get('projects/{projectId}/available-departments', [CommissionConfigController::class, 'availableDepartments']);
});

Route::prefix('treasury')->group(function (): void {
    Route::get('cash-accounts/default', [CashAccountController::class, 'default']);
    Route::get('cash-accounts', [CashAccountController::class, 'index']);
    Route::get('cash-accounts/{id}', [CashAccountController::class, 'show']);

    Route::post('transactions/manual-topup', [CashTransactionController::class, 'manualTopup']);
    Route::post('transactions/manual-withdraw', [CashTransactionController::class, 'manualWithdraw']);
    Route::get('transactions', [CashTransactionController::class, 'index']);
    Route::get('transactions/{id}', [CashTransactionController::class, 'show']);
    Route::delete('transactions/{id}', [CashTransactionController::class, 'destroy']);

    Route::get('summary', [TreasurySummaryController::class, 'index']);
});

Route::prefix('reports')->group(function (): void {
    Route::prefix('cashflow')->group(function (): void {
        Route::get('/summary', [CashFlowReportController::class, 'summary']);
        Route::get('/daily', [CashFlowReportController::class, 'daily']);
        Route::get('/transactions', [CashFlowReportController::class, 'transactions']);
    });

    Route::prefix('sla')->group(function (): void {
        Route::get('/summary', [SlaReportController::class, 'summary']);
        Route::get('/trend', [SlaReportController::class, 'trend']);
        Route::get('/by-project', [SlaReportController::class, 'byProject']);
        Route::get('/by-staff', [SlaReportController::class, 'byStaff']);
        Route::get('/by-ticket', [SlaReportController::class, 'byTicket']);
    });

    Route::prefix('csat')->group(function (): void {
        Route::get('/summary', [CsatReportController::class, 'summary']);
        Route::get('/trend', [CsatReportController::class, 'trend']);
        Route::get('/by-project', [CsatReportController::class, 'byProject']);
    });

    Route::prefix('revenue-ticket')->group(function (): void {
        Route::get('/summary', [RevenueTicketReportController::class, 'summary']);
        Route::get('/by-category', [RevenueTicketReportController::class, 'byCategory']);
        Route::get('/by-staff', [RevenueTicketReportController::class, 'byStaff']);
        Route::get('/daily', [RevenueTicketReportController::class, 'daily']);
        Route::get('/details', [RevenueTicketReportController::class, 'details']);
    });

    Route::prefix('commission')->group(function (): void {
        Route::get('/summary', [CommissionReportController::class, 'summary']);
        Route::get('/by-staff', [CommissionReportController::class, 'byStaff']);
    });

    Route::prefix('revenue-profit')->group(function (): void {
        Route::get('/summary', [RevenueProfitReportController::class, 'summary']);
        Route::get('/monthly', [RevenueProfitReportController::class, 'monthly']);
        Route::get('/by-project', [RevenueProfitReportController::class, 'byProject']);
        Route::get('/by-service-category', [RevenueProfitReportController::class, 'byServiceCategory']);
    });

    Route::prefix('operating-profit')->group(function (): void {
        Route::get('/summary', [OperatingProfitReportController::class, 'summary']);
        Route::get('/monthly', [OperatingProfitReportController::class, 'monthly']);
        Route::get('/by-project', [OperatingProfitReportController::class, 'byProject']);
    });

    Route::prefix('overview')->group(function (): void {
        Route::get('/summary', [OverviewReportController::class, 'summary']);
    });
});
