<?php

namespace Database\Seeders\Platform;

use App\Modules\Platform\Tenant\Models\Organization;
use App\Modules\Platform\Ticket\Enums\TicketChannel;
use App\Modules\Platform\Ticket\Enums\TicketStatus;
use App\Modules\Platform\Ticket\Models\Ticket;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TicketSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Organization::first();
        if (! $tenant) {
            return;
        }

        $project = DB::connection('central')
            ->table("{$tenant->database()->getName()}.projects")
            ->whereNull('deleted_at')
            ->first();

        if (! $project) {
            return;
        }

        $tickets = [
            [
                'code' => 'TK-2026-001',
                'requester_name' => 'Nguyễn Văn An',
                'requester_phone' => '0901234567',
                'subject' => 'Hỏng máy lạnh phòng khách',
                'description' => 'Máy lạnh phòng khách không lạnh, đã thử tắt mở nhiều lần nhưng vẫn không hoạt động. Yêu cầu kỹ thuật kiểm tra sớm.',
                'address' => 'A-1201, 123 Đường Nguyễn Văn Linh, Phường Tân Phong, Quận 7, TP.HCM',
                'latitude' => 10.7321456,
                'longitude' => 106.7198234,
                'channel' => TicketChannel::App,
            ],
            [
                'code' => 'TK-2026-002',
                'requester_name' => 'Trần Thị Bích Ngọc',
                'requester_phone' => '0912345678',
                'subject' => 'Rò rỉ nước nhà vệ sinh',
                'description' => 'Nhà vệ sinh phụ bị rò rỉ nước từ đường ống dưới bồn rửa tay, nước chảy ra sàn liên tục.',
                'address' => 'B-0805, 456 Đường Phạm Hùng, Phường Bình Thuận, Quận 7, TP.HCM',
                'latitude' => 10.7285123,
                'longitude' => 106.7156789,
                'channel' => TicketChannel::Phone,
            ],
            [
                'code' => 'TK-2026-003',
                'requester_name' => 'Lê Hoàng Minh',
                'requester_phone' => '0978654321',
                'subject' => 'Đèn hành lang tầng 15 không sáng',
                'description' => 'Đèn hành lang khu vực tầng 15 tòa C bị tắt từ tối qua, gây bất tiện cho cư dân di chuyển.',
                'address' => 'C-1502, 789 Đường Lê Văn Lương, Phường Tân Kiểng, Quận 7, TP.HCM',
                'latitude' => 10.7345678,
                'longitude' => 106.7212345,
                'channel' => TicketChannel::Website,
            ],
            [
                'code' => 'TK-2026-004',
                'requester_name' => 'Phạm Đức Huy',
                'requester_phone' => '0865432109',
                'subject' => 'Cửa kính ban công bị nứt',
                'description' => 'Cửa kính ban công phòng ngủ chính bị nứt vỡ do gió mạnh, cần thay thế gấp để đảm bảo an toàn.',
                'address' => 'A-0302, 123 Đường Nguyễn Văn Linh, Phường Tân Phong, Quận 7, TP.HCM',
                'latitude' => 10.7321456,
                'longitude' => 106.7198234,
                'channel' => TicketChannel::Direct,
            ],
            [
                'code' => 'TK-2026-005',
                'requester_name' => 'Võ Thị Mai Hương',
                'requester_phone' => '0934567890',
                'subject' => 'Thang máy tòa B bị kẹt tại tầng 10',
                'description' => 'Thang máy số 2 tòa B bị kẹt tại tầng 10 vào lúc 8h sáng, bên trong có 3 người. Đã liên hệ bảo vệ.',
                'address' => 'B-2001, 456 Đường Phạm Hùng, Phường Bình Thuận, Quận 7, TP.HCM',
                'latitude' => 10.7285123,
                'longitude' => 106.7156789,
                'channel' => TicketChannel::Phone,
            ],
            [
                'code' => 'TK-2026-006',
                'requester_name' => 'Đặng Quốc Tuấn',
                'requester_phone' => '0923456781',
                'subject' => 'Sơn tường bong tróc phòng ngủ',
                'description' => 'Tường phòng ngủ chính bị bong tróc sơn trên diện tích khoảng 2m², cần được xử lý và sơn lại.',
                'address' => 'C-0904, 789 Đường Lê Văn Lương, Phường Tân Kiểng, Quận 7, TP.HCM',
                'latitude' => 10.7345678,
                'longitude' => 106.7212345,
                'channel' => TicketChannel::App,
            ],
        ];

        foreach ($tickets as $data) {
            Ticket::firstOrCreate(
                ['code' => $data['code']],
                array_merge($data, [
                    'project_id' => $project->id,
                    'status' => TicketStatus::Pending,
                    'is_from_pool' => true,
                ]),
            );
        }
    }
}
