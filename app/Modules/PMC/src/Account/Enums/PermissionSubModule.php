<?php

namespace App\Modules\PMC\Account\Enums;

enum PermissionSubModule: string
{
    case Accounts = 'accounts';
    case Customers = 'customers';
    case Departments = 'departments';
    case JobTitles = 'job-titles';
    case Projects = 'projects';
    case Roles = 'roles';
    case TicketPool = 'ticket-pool';
    case OgTickets = 'og-tickets';
    case CatalogSuppliers = 'catalog-suppliers';
    case CatalogItems = 'catalog-items';
    case ServiceCategories = 'service-categories';
    case Quotes = 'quotes';
    case Orders = 'orders';
    case Commission = 'commission';
    case SettingsSla = 'settings-sla';
    case SettingsBankAccount = 'settings-bank-account';
    case SettingsAcceptanceReport = 'settings-acceptance-report';
    case Receivables = 'receivables';
    case Reconciliations = 'reconciliations';
    case ClosingPeriods = 'closing-periods';
    case Treasury = 'treasury';
    case Policies = 'policies';
    case ReportOverview = 'report-overview';
    case ReportRevenueTicket = 'report-revenue-ticket';
    case ReportRevenueProfit = 'report-revenue-profit';
    case ReportOperatingProfit = 'report-operating-profit';
    case ReportCommission = 'report-commission';
    case ReportCashFlow = 'report-cashflow';
    case ReportSla = 'report-sla';
    case ReportCsat = 'report-csat';
    case WorkSchedules = 'work-schedules';
    case ScheduleSlots = 'schedule-slots';
    case WorkforceCapacity = 'workforce-capacity';
    case Shifts = 'shifts';

    public function label(): string
    {
        return match ($this) {
            self::Accounts => 'Tài khoản',
            self::Customers => 'Khách hàng',
            self::Departments => 'Phòng ban',
            self::JobTitles => 'Chức danh',
            self::Projects => 'Dự án',
            self::Roles => 'Vai trò',
            self::TicketPool => 'Ticket Pool',
            self::OgTickets => 'Danh sách ticket',
            self::CatalogSuppliers => 'Nhà cung cấp',
            self::CatalogItems => 'Danh mục hàng',
            self::ServiceCategories => 'Danh mục dịch vụ',
            self::Quotes => 'Báo giá',
            self::Orders => 'Đơn hàng',
            self::Commission => 'Hoa hồng',
            self::SettingsSla => 'SLA',
            self::SettingsBankAccount => 'Tài khoản nhận CK',
            self::SettingsAcceptanceReport => 'Template biên bản nghiệm thu',
            self::Receivables => 'Công nợ phải thu',
            self::Reconciliations => 'Đối soát tài chính',
            self::ClosingPeriods => 'Kỳ chốt phí',
            self::Treasury => 'Quản lý quỹ',
            self::Policies => 'Chính sách',
            self::ReportOverview => 'Tổng quan',
            self::ReportRevenueTicket => 'Doanh thu (ticket)',
            self::ReportRevenueProfit => 'Doanh thu & lợi nhuận',
            self::ReportOperatingProfit => 'Lợi nhuận VH (Vật tư + HH)',
            self::ReportCommission => 'Phân bổ hoa hồng',
            self::ReportCashFlow => 'Dòng tiền',
            self::ReportSla => 'SLA',
            self::ReportCsat => 'Hài lòng KH',
            self::WorkSchedules => 'Đăng ký ca làm việc',
            self::ScheduleSlots => 'Lịch làm việc (tổng hợp)',
            self::WorkforceCapacity => 'Năng lực nhân sự',
            self::Shifts => 'Ca làm việc',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
