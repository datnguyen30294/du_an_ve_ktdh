<?php

namespace App\Modules\PMC\Providers;

use App\Modules\Platform\Ticket\Models\Ticket;
use App\Modules\PMC\AcceptanceReport\Contracts\AcceptanceReportServiceInterface;
use App\Modules\PMC\AcceptanceReport\Services\AcceptanceReportService;
use App\Modules\PMC\Account\Contracts\AccountServiceInterface;
use App\Modules\PMC\Account\Contracts\AuthServiceInterface;
use App\Modules\PMC\Account\Contracts\DefaultRoleServiceInterface;
use App\Modules\PMC\Account\Contracts\RoleServiceInterface;
use App\Modules\PMC\Account\Services\AccountService;
use App\Modules\PMC\Account\Services\AuthService;
use App\Modules\PMC\Account\Services\DefaultRoleService;
use App\Modules\PMC\Account\Services\RoleService;
use App\Modules\PMC\Catalog\Contracts\CatalogItemServiceInterface;
use App\Modules\PMC\Catalog\Contracts\CatalogSupplierServiceInterface;
use App\Modules\PMC\Catalog\Contracts\ServiceCategoryServiceInterface;
use App\Modules\PMC\Catalog\Services\CatalogItemService;
use App\Modules\PMC\Catalog\Services\CatalogSupplierService;
use App\Modules\PMC\Catalog\Services\ServiceCategoryService;
use App\Modules\PMC\ClosingPeriod\Contracts\ClosingPeriodServiceInterface;
use App\Modules\PMC\ClosingPeriod\Contracts\CommissionSnapshotServiceInterface;
use App\Modules\PMC\ClosingPeriod\Services\ClosingPeriodService;
use App\Modules\PMC\ClosingPeriod\Services\CommissionSnapshotService;
use App\Modules\PMC\Commission\Contracts\CommissionConfigServiceInterface;
use App\Modules\PMC\Commission\Services\CommissionConfigService;
use App\Modules\PMC\Customer\Contracts\CustomerServiceInterface;
use App\Modules\PMC\Customer\Services\CustomerService;
use App\Modules\PMC\Department\Contracts\DepartmentServiceInterface;
use App\Modules\PMC\Department\Services\DepartmentService;
use App\Modules\PMC\JobTitle\Contracts\JobTitleServiceInterface;
use App\Modules\PMC\JobTitle\Services\JobTitleService;
use App\Modules\PMC\OgTicket\Contracts\OgTicketLifecycleServiceInterface;
use App\Modules\PMC\OgTicket\Contracts\OgTicketServiceInterface;
use App\Modules\PMC\OgTicket\Contracts\OgTicketSurveyServiceInterface;
use App\Modules\PMC\OgTicket\Contracts\OgTicketWarrantyRequestServiceInterface;
use App\Modules\PMC\OgTicket\ExternalServices\TicketExternalService;
use App\Modules\PMC\OgTicket\ExternalServices\TicketExternalServiceInterface;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\OgTicket\Services\OgTicketLifecycleService;
use App\Modules\PMC\OgTicket\Services\OgTicketService;
use App\Modules\PMC\OgTicket\Services\OgTicketSurveyService;
use App\Modules\PMC\OgTicket\Services\OgTicketWarrantyRequestService;
use App\Modules\PMC\OgTicketCategory\Contracts\OgTicketCategoryServiceInterface;
use App\Modules\PMC\OgTicketCategory\Services\OgTicketCategoryService;
use App\Modules\PMC\Order\Contracts\OrderCommissionOverrideServiceInterface;
use App\Modules\PMC\Order\Contracts\OrderServiceInterface;
use App\Modules\PMC\Order\Services\OrderCommissionOverrideService;
use App\Modules\PMC\Order\Services\OrderService;
use App\Modules\PMC\Policy\Contracts\PolicyServiceInterface;
use App\Modules\PMC\Policy\Services\PolicyService;
use App\Modules\PMC\Project\Contracts\ProjectServiceInterface;
use App\Modules\PMC\Project\Services\ProjectService;
use App\Modules\PMC\Quote\Contracts\QuoteServiceInterface;
use App\Modules\PMC\Quote\Services\QuoteService;
use App\Modules\PMC\Receivable\Contracts\ReceivableServiceInterface;
use App\Modules\PMC\Receivable\Listeners\AutoCompleteReceivableOnReconciliation;
use App\Modules\PMC\Receivable\Services\ReceivableService;
use App\Modules\PMC\Reconciliation\Contracts\ReconciliationServiceInterface;
use App\Modules\PMC\Reconciliation\Services\ReconciliationService;
use App\Modules\PMC\Report\CashFlow\Contracts\CashFlowReportServiceInterface;
use App\Modules\PMC\Report\CashFlow\Services\CashFlowReportService;
use App\Modules\PMC\Report\Commission\Contracts\CommissionReportServiceInterface;
use App\Modules\PMC\Report\Commission\Services\CommissionReportService;
use App\Modules\PMC\Report\Csat\Contracts\CsatReportServiceInterface;
use App\Modules\PMC\Report\Csat\Services\CsatReportService;
use App\Modules\PMC\Report\OperatingProfit\Contracts\OperatingProfitReportServiceInterface;
use App\Modules\PMC\Report\OperatingProfit\Services\OperatingProfitReportService;
use App\Modules\PMC\Report\Overview\Contracts\OverviewReportServiceInterface;
use App\Modules\PMC\Report\Overview\Services\OverviewReportService;
use App\Modules\PMC\Report\RevenueProfit\Contracts\RevenueProfitReportServiceInterface;
use App\Modules\PMC\Report\RevenueProfit\Services\RevenueProfitReportService;
use App\Modules\PMC\Report\RevenueTicket\Contracts\RevenueTicketReportServiceInterface;
use App\Modules\PMC\Report\RevenueTicket\Services\RevenueTicketReportService;
use App\Modules\PMC\Report\Sla\Contracts\SlaReportServiceInterface;
use App\Modules\PMC\Report\Sla\Services\SlaReportService;
use App\Modules\PMC\Setting\Contracts\SystemSettingServiceInterface;
use App\Modules\PMC\Setting\Services\SystemSettingService;
use App\Modules\PMC\Shift\Contracts\ShiftServiceInterface;
use App\Modules\PMC\Shift\Services\ShiftService;
use App\Modules\PMC\Treasury\Contracts\TreasuryServiceInterface;
use App\Modules\PMC\Treasury\Events\AdvancePaymentDeleted;
use App\Modules\PMC\Treasury\Events\AdvancePaymentRecorded;
use App\Modules\PMC\Treasury\Events\CommissionSnapshotPaid;
use App\Modules\PMC\Treasury\Events\CommissionSnapshotUnpaid;
use App\Modules\PMC\Treasury\Events\FinancialReconciliationApproved;
use App\Modules\PMC\Treasury\Events\FinancialReconciliationReset;
use App\Modules\PMC\Treasury\Listeners\CreateCashTransactionFromAdvancePayment;
use App\Modules\PMC\Treasury\Listeners\CreateCashTransactionFromCommission;
use App\Modules\PMC\Treasury\Listeners\CreateCashTransactionFromReconciliation;
use App\Modules\PMC\Treasury\Listeners\SoftDeleteCashTransactionOnAdvancePaymentDeleted;
use App\Modules\PMC\Treasury\Listeners\SoftDeleteCashTransactionOnCommissionUnpaid;
use App\Modules\PMC\Treasury\Listeners\SoftDeleteCashTransactionOnReconciliationReset;
use App\Modules\PMC\Treasury\Services\TreasuryService;
use App\Modules\PMC\WorkforceCapacity\Contracts\WorkforceCapacityServiceInterface;
use App\Modules\PMC\WorkforceCapacity\Services\WorkforceCapacityService;
use App\Modules\PMC\WorkSchedule\Contracts\ScheduleSlotServiceInterface;
use App\Modules\PMC\WorkSchedule\Contracts\WorkScheduleServiceInterface;
use App\Modules\PMC\WorkSchedule\Services\ScheduleSlotService;
use App\Modules\PMC\WorkSchedule\Services\WorkScheduleService;
use App\Modules\PMC\WorkSnapshot\Contracts\WorkSlotSnapshotServiceInterface;
use App\Modules\PMC\WorkSnapshot\Services\WorkSlotSnapshotService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PMCServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CatalogSupplierServiceInterface::class, CatalogSupplierService::class);
        $this->app->bind(CatalogItemServiceInterface::class, CatalogItemService::class);
        $this->app->bind(ServiceCategoryServiceInterface::class, ServiceCategoryService::class);
        $this->app->bind(DepartmentServiceInterface::class, DepartmentService::class);
        $this->app->bind(JobTitleServiceInterface::class, JobTitleService::class);
        $this->app->bind(ShiftServiceInterface::class, ShiftService::class);
        $this->app->bind(WorkScheduleServiceInterface::class, WorkScheduleService::class);
        $this->app->bind(ScheduleSlotServiceInterface::class, ScheduleSlotService::class);
        $this->app->bind(WorkSlotSnapshotServiceInterface::class, WorkSlotSnapshotService::class);
        $this->app->bind(WorkforceCapacityServiceInterface::class, WorkforceCapacityService::class);
        $this->app->bind(ProjectServiceInterface::class, ProjectService::class);
        $this->app->bind(DefaultRoleServiceInterface::class, DefaultRoleService::class);
        $this->app->bind(RoleServiceInterface::class, RoleService::class);
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(AccountServiceInterface::class, AccountService::class);
        $this->app->bind(OgTicketServiceInterface::class, OgTicketService::class);
        $this->app->bind(OgTicketLifecycleServiceInterface::class, OgTicketLifecycleService::class);
        $this->app->bind(OgTicketWarrantyRequestServiceInterface::class, OgTicketWarrantyRequestService::class);
        $this->app->bind(OgTicketSurveyServiceInterface::class, OgTicketSurveyService::class);
        $this->app->bind(OgTicketCategoryServiceInterface::class, OgTicketCategoryService::class);
        $this->app->bind(TicketExternalServiceInterface::class, TicketExternalService::class);
        $this->app->bind(QuoteServiceInterface::class, QuoteService::class);
        $this->app->bind(OrderServiceInterface::class, OrderService::class);
        $this->app->bind(OrderCommissionOverrideServiceInterface::class, OrderCommissionOverrideService::class);
        $this->app->bind(CommissionConfigServiceInterface::class, CommissionConfigService::class);
        $this->app->bind(SystemSettingServiceInterface::class, SystemSettingService::class);
        $this->app->bind(ReceivableServiceInterface::class, ReceivableService::class);
        $this->app->bind(ReconciliationServiceInterface::class, ReconciliationService::class);
        $this->app->bind(PolicyServiceInterface::class, PolicyService::class);
        $this->app->bind(ClosingPeriodServiceInterface::class, ClosingPeriodService::class);
        $this->app->bind(CommissionSnapshotServiceInterface::class, CommissionSnapshotService::class);
        $this->app->bind(TreasuryServiceInterface::class, TreasuryService::class);
        $this->app->bind(SlaReportServiceInterface::class, SlaReportService::class);
        $this->app->bind(CashFlowReportServiceInterface::class, CashFlowReportService::class);
        $this->app->bind(CsatReportServiceInterface::class, CsatReportService::class);
        $this->app->bind(RevenueTicketReportServiceInterface::class, RevenueTicketReportService::class);
        $this->app->bind(CommissionReportServiceInterface::class, CommissionReportService::class);
        $this->app->bind(RevenueProfitReportServiceInterface::class, RevenueProfitReportService::class);
        $this->app->bind(OperatingProfitReportServiceInterface::class, OperatingProfitReportService::class);
        $this->app->bind(OverviewReportServiceInterface::class, OverviewReportService::class);
        $this->app->bind(AcceptanceReportServiceInterface::class, AcceptanceReportService::class);
        $this->app->bind(CustomerServiceInterface::class, CustomerService::class);

        OgTicket::resolveRelationUsing('ticket', function (OgTicket $ogTicket) {
            return $ogTicket->belongsTo(Ticket::class, 'ticket_id');
        });
    }

    public function boot(): void
    {
        // In testing (SQLite), load tenant migrations as central migrations.
        // In production, stancl/tenancy handles tenant migrations via tenants:migrate.
        if ($this->app->runningUnitTests()) {
            $this->loadMigrationsFrom(database_path('migrations/tenant'));
        }

        $this->loadRoutes();
        $this->registerTreasuryListeners();
        $this->loadCommands();
    }

    protected function loadCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Modules\PMC\WorkSnapshot\Commands\CaptureShiftBoundariesCommand::class,
                \App\Modules\PMC\WorkSnapshot\Commands\SweepUnfinalizedSnapshotsCommand::class,
            ]);
        }
    }

    private function registerTreasuryListeners(): void
    {
        Event::listen(FinancialReconciliationApproved::class, CreateCashTransactionFromReconciliation::class);
        Event::listen(FinancialReconciliationApproved::class, AutoCompleteReceivableOnReconciliation::class);
        Event::listen(FinancialReconciliationReset::class, SoftDeleteCashTransactionOnReconciliationReset::class);
        Event::listen(CommissionSnapshotPaid::class, CreateCashTransactionFromCommission::class);
        Event::listen(CommissionSnapshotUnpaid::class, SoftDeleteCashTransactionOnCommissionUnpaid::class);
        Event::listen(AdvancePaymentRecorded::class, CreateCashTransactionFromAdvancePayment::class);
        Event::listen(AdvancePaymentDeleted::class, SoftDeleteCashTransactionOnAdvancePaymentDeleted::class);
    }

    protected function loadRoutes(): void
    {
        Route::prefix('api/v1/pmc')
            ->middleware(['api', 'tenant', 'auth:sanctum'])
            ->group(base_path('app/Modules/PMC/routes/api.php'));

        Route::prefix('api/v1/auth')
            ->middleware(['api', 'tenant'])
            ->group(base_path('app/Modules/PMC/routes/auth.php'));

        Route::prefix('api/v1/pmc')
            ->middleware(['api', 'tenant', 'auth:sanctum'])
            ->group(base_path('app/Modules/PMC/routes/accounts.php'));

        Route::prefix('api/v1/public')
            ->middleware(['api', 'tenant'])
            ->group(base_path('app/Modules/PMC/routes/public.php'));

        Route::prefix('api/v1/ext')
            ->middleware(['api', 'tenant', 'auth.api-client'])
            ->group(base_path('app/Modules/PMC/routes/external.php'));
    }
}
