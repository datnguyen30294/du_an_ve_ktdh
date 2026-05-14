<?php

namespace Database\Seeders\Tenant\Data;

use App\Modules\PMC\Account\Enums\RoleType;
use App\Modules\PMC\Project\Enums\ProjectStatus;

class OrganizationSeedData
{
    public const TNP_SERVICES = 'tnp';

    public const E2E_TESTING = 'e2e';

    /** @return list<string> */
    public static function orgCodes(): array
    {
        return [self::TNP_SERVICES];
    }

    /** @return array<string, array{id: string, name: string}> */
    public static function organizations(): array
    {
        return [
            self::TNP_SERVICES => ['id' => self::TNP_SERVICES, 'name' => 'Công ty Thần Nông'],
            self::E2E_TESTING => ['id' => self::E2E_TESTING, 'name' => 'E2E Test Organization'],
        ];
    }

    /** @return list<array{code: string, name: string, description: string}> */
    public static function departments(string $orgCode): array
    {
        return match ($orgCode) {
            self::TNP_SERVICES, self::E2E_TESTING => [
                ['code' => 'BGD', 'name' => 'Ban Giám đốc', 'description' => 'Ban lãnh đạo công ty'],
                ['code' => 'HC', 'name' => 'Phòng Hành chính', 'description' => 'Hành chính - Nhân sự'],
                ['code' => 'KT', 'name' => 'Phòng Kỹ thuật', 'description' => 'Kỹ thuật vận hành'],
            ],
            default => [],
        };
    }

    /** @return list<array{code: string, name: string, description: string}> */
    public static function jobTitles(string $orgCode): array
    {
        return match ($orgCode) {
            self::TNP_SERVICES, self::E2E_TESTING => [
                ['code' => 'TP', 'name' => 'Trưởng phòng', 'description' => 'Trưởng phòng ban'],
                ['code' => 'NV', 'name' => 'Nhân viên', 'description' => 'Nhân viên nghiệp vụ'],
            ],
            default => [],
        };
    }

    /** @return list<array{name: string, description: string, is_active: bool, type: RoleType}> */
    public static function customRoles(): array
    {
        return [
            ['name' => 'Admin', 'description' => 'Quản trị viên hệ thống', 'is_active' => true, 'type' => RoleType::Custom],
            ['name' => 'Staff', 'description' => 'Nhân viên', 'is_active' => true, 'type' => RoleType::Custom],
        ];
    }

    /** @return list<array{code: string, name: string, address: string|null, status: ProjectStatus}> */
    public static function projects(string $orgCode): array
    {
        return match ($orgCode) {
            self::TNP_SERVICES => [
                ['code' => 'DA-CC-A', 'name' => 'Dự án Chung cư A', 'address' => '123 Đường Nguyễn Văn Linh, Quận 7, TP.HCM', 'status' => ProjectStatus::Managing],
                ['code' => 'DA-CC-B', 'name' => 'Dự án Chung cư B', 'address' => '45 Đường Phạm Văn Đồng, Quận Bình Thạnh, TP.HCM', 'status' => ProjectStatus::Managing],
            ],
            self::E2E_TESTING => [
                ['code' => 'DA-CC-A', 'name' => 'Dự án Chung cư A', 'address' => '123 Đường Nguyễn Văn Linh, Quận 7, TP.HCM', 'status' => ProjectStatus::Managing],
            ],
            default => [],
        };
    }

    /** @return list<array{code: string, name: string, contact: string|null, phone: string|null, address: string|null, email: string|null, commission_rate: string|null}> */
    public static function catalogSuppliers(string $orgCode): array
    {
        return match ($orgCode) {
            self::TNP_SERVICES, self::E2E_TESTING => [
                ['code' => 'NCC-VL01', 'name' => 'Công ty TNHH Vật liệu xây dựng Minh Phát', 'contact' => 'Nguyễn Minh Phát', 'phone' => '0901234567', 'address' => '12 Đường Trần Hưng Đạo, Quận 1, TP.HCM', 'email' => 'minhphat@vlxd.vn', 'commission_rate' => '5.00'],
                ['code' => 'NCC-DT01', 'name' => 'Công ty CP Thiết bị điện Tân Phong', 'contact' => 'Trần Tân Phong', 'phone' => '0912345678', 'address' => '88 Đường Nguyễn Thị Minh Khai, Quận 3, TP.HCM', 'email' => 'tanphong@tbdien.vn', 'commission_rate' => '3.50'],
                ['code' => 'NCC-NS01', 'name' => 'Công ty TNHH Nội thất Hoàng Gia', 'contact' => 'Lê Hoàng Gia', 'phone' => '0923456789', 'address' => '56 Đường Lý Thường Kiệt, Quận 10, TP.HCM', 'email' => 'hoanggia@noithat.vn', 'commission_rate' => '7.00'],
                ['code' => 'NCC-VS01', 'name' => 'Công ty TNHH Vệ sinh Sạch Đẹp', 'contact' => 'Phạm Văn Sạch', 'phone' => '0934567890', 'address' => '34 Đường Cách Mạng Tháng 8, Quận Tân Bình, TP.HCM', 'email' => 'sachdep@vesinh.vn', 'commission_rate' => '2.00'],
                ['code' => 'NCC-PC01', 'name' => 'Công ty CP PCCC An Toàn', 'contact' => 'Võ An Toàn', 'phone' => '0945678901', 'address' => '78 Đường Điện Biên Phủ, Quận Bình Thạnh, TP.HCM', 'email' => 'antoan@pccc.vn', 'commission_rate' => '4.00'],
            ],
            default => [],
        };
    }

    /** @return list<array{code: string, name: string, description: string|null, sort_order: int, image_path: string|null}> */
    public static function serviceCategories(string $orgCode): array
    {
        return match ($orgCode) {
            self::TNP_SERVICES, self::E2E_TESTING => [
                ['code' => 'SC-SC', 'name' => 'Sửa chữa', 'description' => 'Dịch vụ sửa chữa điện, nước, thiết bị', 'sort_order' => 1, 'image_path' => 'https://images.unsplash.com/photo-1581094271901-8022df4466f9?auto=format&fit=crop&w=1200&q=80'],
                ['code' => 'SC-VS', 'name' => 'Vệ sinh', 'description' => 'Dịch vụ vệ sinh, dọn dẹp', 'sort_order' => 2, 'image_path' => 'https://images.unsplash.com/photo-1527515637462-cff94eecc1ac?auto=format&fit=crop&w=1200&q=80'],
                ['code' => 'SC-BT', 'name' => 'Bảo trì', 'description' => 'Dịch vụ bảo trì, bảo dưỡng định kỳ', 'sort_order' => 3, 'image_path' => 'https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&fit=crop&w=1200&q=80'],
                ['code' => 'SC-PCCC', 'name' => 'PCCC', 'description' => 'Dịch vụ phòng cháy chữa cháy', 'sort_order' => 4, 'image_path' => 'https://images.unsplash.com/photo-1599566150163-29194dcaad36?auto=format&fit=crop&w=1200&q=80'],
            ],
            default => [],
        };
    }

    /** @return list<array{type: string, code: string, name: string, unit: string, unit_price: float, purchase_price: float|null, commission_rate: string|null, supplier_code: string|null, description: string|null, category_code: string|null, image_path?: string|null, price_note?: string|null, is_featured?: bool, content?: string|null, gallery?: list<string>}> */
    public static function catalogItems(string $orgCode): array
    {
        return match ($orgCode) {
            self::TNP_SERVICES, self::E2E_TESTING => [
                // Vật tư
                ['type' => 'material', 'code' => 'VT-001', 'name' => 'Ống nước PVC D21', 'unit' => 'm', 'unit_price' => 25000, 'purchase_price' => 18000, 'commission_rate' => '5.00', 'supplier_code' => 'NCC-VL01', 'description' => 'Ống nước PVC phi 21 chất lượng cao', 'category_code' => null],
                ['type' => 'material', 'code' => 'VT-002', 'name' => 'Dây điện Cadivi 2.5mm', 'unit' => 'm', 'unit_price' => 12000, 'purchase_price' => 8500, 'commission_rate' => '3.50', 'supplier_code' => 'NCC-DT01', 'description' => 'Dây điện đơn lõi đồng 2.5mm²', 'category_code' => null],
                ['type' => 'material', 'code' => 'VT-003', 'name' => 'Sơn Dulux nội thất', 'unit' => 'lít', 'unit_price' => 180000, 'purchase_price' => 140000, 'commission_rate' => '7.00', 'supplier_code' => 'NCC-VL01', 'description' => 'Sơn nội thất cao cấp Dulux', 'category_code' => null],
                ['type' => 'material', 'code' => 'VT-004', 'name' => 'Xi măng Holcim PCB40', 'unit' => 'bao', 'unit_price' => 95000, 'purchase_price' => 75000, 'commission_rate' => '4.00', 'supplier_code' => 'NCC-VL01', 'description' => 'Xi măng Holcim bao 50kg', 'category_code' => null],
                ['type' => 'material', 'code' => 'VT-005', 'name' => 'Bóng đèn LED Rạng Đông 12W', 'unit' => 'cái', 'unit_price' => 45000, 'purchase_price' => 32000, 'commission_rate' => '3.00', 'supplier_code' => 'NCC-DT01', 'description' => 'Bóng đèn LED tiết kiệm điện 12W', 'category_code' => null],
                ['type' => 'material', 'code' => 'VT-006', 'name' => 'Khóa cửa tay gạt Huy Hoàng', 'unit' => 'bộ', 'unit_price' => 350000, 'purchase_price' => 260000, 'commission_rate' => '5.50', 'supplier_code' => 'NCC-NS01', 'description' => 'Khóa cửa tay gạt inox 304', 'category_code' => null],
                ['type' => 'material', 'code' => 'VT-007', 'name' => 'Gạch lát nền 60x60', 'unit' => 'viên', 'unit_price' => 85000, 'purchase_price' => 62000, 'commission_rate' => '6.00', 'supplier_code' => 'NCC-VL01', 'description' => 'Gạch men bóng kính 60x60cm', 'category_code' => null],
                ['type' => 'material', 'code' => 'VT-008', 'name' => 'Bình chữa cháy ABC 4kg', 'unit' => 'bình', 'unit_price' => 280000, 'purchase_price' => 210000, 'commission_rate' => '4.00', 'supplier_code' => 'NCC-PC01', 'description' => 'Bình chữa cháy bột ABC 4kg', 'category_code' => null],
                // Dịch vụ
                [
                    'type' => 'service', 'code' => 'DV-001', 'name' => 'Dịch vụ sơn tường',
                    'unit' => 'm²', 'unit_price' => 35000, 'purchase_price' => null, 'commission_rate' => null, 'supplier_code' => null,
                    'description' => 'Làm mới không gian sống với sơn Dulux, Mykolor chính hãng. Bả matit phẳng mịn, 2 lớp sơn phủ, bảo hành 24 tháng.',
                    'category_code' => 'SC-SC',
                    'image_path' => 'https://images.unsplash.com/photo-1562259949-e8e7689d7828?auto=format&fit=crop&w=1600&q=80',
                    'price_note' => 'Chưa bao gồm vật tư sơn',
                    'is_featured' => true,
                    'content' => self::articleContent('DV-001'),
                    'gallery' => [
                        'https://images.unsplash.com/photo-1589939705384-5185137a7f0f?auto=format&fit=crop&w=1200&q=80',
                        'https://images.unsplash.com/photo-1572025442646-866d16c84a54?auto=format&fit=crop&w=1200&q=80',
                        'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=1200&q=80',
                    ],
                ],
                [
                    'type' => 'service', 'code' => 'DV-002', 'name' => 'Sửa chữa điện',
                    'unit' => 'lần', 'unit_price' => 200000, 'purchase_price' => null, 'commission_rate' => null, 'supplier_code' => null,
                    'description' => 'Xử lý sự cố điện 24/7 bởi kỹ thuật viên có chứng chỉ. Chẩn đoán nhanh, khắc phục triệt để, báo giá minh bạch trước khi thi công.',
                    'category_code' => 'SC-SC',
                    'image_path' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?auto=format&fit=crop&w=1600&q=80',
                    'price_note' => 'Miễn phí kiểm tra, khảo sát',
                    'is_featured' => true,
                    'content' => self::articleContent('DV-002'),
                    'gallery' => [
                        'https://images.unsplash.com/photo-1621905252507-b35492cc74b4?auto=format&fit=crop&w=1200&q=80',
                        'https://images.unsplash.com/photo-1615529182904-14819c35db37?auto=format&fit=crop&w=1200&q=80',
                        'https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&fit=crop&w=1200&q=80',
                    ],
                ],
                [
                    'type' => 'service', 'code' => 'DV-003', 'name' => 'Sửa chữa nước',
                    'unit' => 'lần', 'unit_price' => 150000, 'purchase_price' => null, 'commission_rate' => null, 'supplier_code' => null,
                    'description' => 'Khắc phục rò rỉ, tắc nghẽn, vỡ ống nước nhanh chóng. Thiết bị dò rò chuyên nghiệp, hạn chế đục phá tường tối đa.',
                    'category_code' => 'SC-SC',
                    'image_path' => 'https://images.unsplash.com/photo-1607472586893-edb57bdc0e39?auto=format&fit=crop&w=1600&q=80',
                    'price_note' => 'Bảo hành 6 tháng sau sửa chữa',
                    'is_featured' => false,
                    'content' => self::articleContent('DV-003'),
                    'gallery' => [
                        'https://images.unsplash.com/photo-1585704032915-c3400ca199e7?auto=format&fit=crop&w=1200&q=80',
                        'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?auto=format&fit=crop&w=1200&q=80',
                        'https://images.unsplash.com/photo-1621905252507-b35492cc74b4?auto=format&fit=crop&w=1200&q=80',
                    ],
                ],
                [
                    'type' => 'service', 'code' => 'DV-004', 'name' => 'Vệ sinh chung cư',
                    'unit' => 'lần', 'unit_price' => 500000, 'purchase_price' => null, 'commission_rate' => null, 'supplier_code' => null,
                    'description' => 'Vệ sinh toàn diện khu vực chung chung cư: hành lang, sảnh, cầu thang, thang máy, hầm để xe. Đội ngũ 5-8 nhân viên chuyên nghiệp.',
                    'category_code' => 'SC-VS',
                    'image_path' => 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?auto=format&fit=crop&w=1600&q=80',
                    'price_note' => 'Giá cho diện tích sàn dưới 1.000m²',
                    'is_featured' => true,
                    'content' => self::articleContent('DV-004'),
                    'gallery' => [
                        'https://images.unsplash.com/photo-1527515637462-cff94eecc1ac?auto=format&fit=crop&w=1200&q=80',
                        'https://images.unsplash.com/photo-1600566753376-12c8ab7fb75b?auto=format&fit=crop&w=1200&q=80',
                        'https://images.unsplash.com/photo-1530533718754-001d2668365a?auto=format&fit=crop&w=1200&q=80',
                    ],
                ],
                [
                    'type' => 'service', 'code' => 'DV-005', 'name' => 'Bảo trì thang máy',
                    'unit' => 'lần', 'unit_price' => 2500000, 'purchase_price' => null, 'commission_rate' => null, 'supplier_code' => null,
                    'description' => 'Bảo trì thang máy định kỳ theo tiêu chuẩn nhà sản xuất. Kiểm tra 37 hạng mục, thay dầu, căn chỉnh, hiệu chuẩn hệ thống an toàn.',
                    'category_code' => 'SC-BT',
                    'image_path' => 'https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&fit=crop&w=1600&q=80',
                    'price_note' => 'Áp dụng cho thang máy tải trọng ≤ 1.000kg',
                    'is_featured' => false,
                    'content' => self::articleContent('DV-005'),
                    'gallery' => [
                        'https://images.unsplash.com/photo-1519682337058-a94d519337bc?auto=format&fit=crop&w=1200&q=80',
                        'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=1200&q=80',
                        'https://images.unsplash.com/photo-1516937941344-00b4e0337589?auto=format&fit=crop&w=1200&q=80',
                    ],
                ],
                [
                    'type' => 'service', 'code' => 'DV-006', 'name' => 'Kiểm tra PCCC',
                    'unit' => 'lần', 'unit_price' => 1500000, 'purchase_price' => null, 'commission_rate' => null, 'supplier_code' => null,
                    'description' => 'Kiểm tra toàn bộ hệ thống PCCC tòa nhà theo Thông tư 149/2020/TT-BCA. Lập biên bản, xuất báo cáo kỹ thuật nghiệm thu định kỳ.',
                    'category_code' => 'SC-PCCC',
                    'image_path' => 'https://images.unsplash.com/photo-1582139329536-e7284fece509?auto=format&fit=crop&w=1600&q=80',
                    'price_note' => 'Đã bao gồm báo cáo kỹ thuật bằng văn bản',
                    'is_featured' => false,
                    'content' => self::articleContent('DV-006'),
                    'gallery' => [
                        'https://images.unsplash.com/photo-1599566150163-29194dcaad36?auto=format&fit=crop&w=1200&q=80',
                        'https://images.unsplash.com/photo-1617791160505-6f00504e3519?auto=format&fit=crop&w=1200&q=80',
                        'https://images.unsplash.com/photo-1521540216272-a50305cd4421?auto=format&fit=crop&w=1200&q=80',
                    ],
                ],
            ],
            default => [],
        };
    }

    /**
     * Rich HTML article body for catalog service items. Rendered via Tailwind `prose` on FE detail page.
     */
    private static function articleContent(string $code): string
    {
        return match ($code) {
            'DV-001' => <<<'HTML'
                <p class="lead">Không gian sống tươi mới bắt đầu từ một lớp sơn hoàn hảo. Dịch vụ sơn tường của Thần Nông mang đến cho căn hộ của bạn vẻ đẹp bền bỉ, màu sắc chuẩn và bề mặt phẳng mịn như mới. Đội ngũ thợ lành nghề kết hợp sơn chính hãng <strong>Dulux, Mykolor, Jotun</strong> giúp công trình hoàn thiện nhanh chóng, ít bụi và sạch sẽ sau thi công.</p>

                <h2>Dịch vụ bao gồm</h2>
                <ul>
                    <li>Khảo sát hiện trạng, đánh giá tường và tư vấn màu sắc tại nhà</li>
                    <li>Xử lý ẩm mốc, bong tróc, trám vá các vết nứt chân chim</li>
                    <li>Bả matit 1-2 lớp để bề mặt phẳng mịn, lăn sơn đều màu</li>
                    <li>1 lớp sơn lót chống kiềm + 2 lớp sơn phủ hoàn thiện</li>
                    <li>Che chắn nội thất, dọn dẹp và hoàn trả mặt bằng sau thi công</li>
                </ul>

                <h2>Quy trình 5 bước</h2>
                <ol>
                    <li><strong>Khảo sát &amp; tư vấn màu</strong> — Kỹ thuật viên đến tận nhà trong 24 giờ để đo diện tích và tư vấn bảng màu.</li>
                    <li><strong>Báo giá chi tiết</strong> — Gửi báo giá trong 2 giờ làm việc, minh bạch từng hạng mục vật tư và nhân công.</li>
                    <li><strong>Chuẩn bị mặt bằng</strong> — Di chuyển, che phủ nội thất, trải bạt sàn chống bẩn.</li>
                    <li><strong>Thi công</strong> — Thực hiện theo quy trình kỹ thuật chuẩn của hãng sơn, 3-5 ngày tùy diện tích.</li>
                    <li><strong>Nghiệm thu</strong> — Kiểm tra cùng khách hàng, dọn dẹp sạch sẽ và bàn giao biên bản bảo hành.</li>
                </ol>

                <h2>Cam kết chất lượng</h2>
                <ul>
                    <li>Sơn <strong>chính hãng</strong> — có tem chống giả, hóa đơn đỏ đầy đủ</li>
                    <li><strong>Bảo hành 24 tháng</strong> với sơn Dulux Weathershield</li>
                    <li>Hoàn tiền 100% nếu sơn sai màu hoặc bong tróc trong 6 tháng đầu</li>
                    <li>Thi công gọn gàng, không ảnh hưởng sinh hoạt của gia đình</li>
                </ul>

                <blockquote>Mỗi mét vuông tường là một cam kết. Chúng tôi không dừng lại khi bề mặt đã khô — chúng tôi dừng lại khi bạn thật sự hài lòng.</blockquote>

                <h2>Câu hỏi thường gặp</h2>
                <p><strong>Sơn xong có mùi không?</strong> — Tất cả sơn đều thuộc dòng gốc nước, ít mùi, có thể ở nhà bình thường sau 4-6 giờ.</p>
                <p><strong>Có cần chuyển đồ ra ngoài không?</strong> — Đội thi công sẽ che chắn và dồn gọn nội thất, bạn không cần chuyển đi đâu.</p>
                HTML,
            'DV-002' => <<<'HTML'
                <p class="lead">Hệ thống điện trong căn hộ là huyết mạch của mọi sinh hoạt. Một sự cố nhỏ cũng có thể gây chập cháy, hư hỏng thiết bị giá trị. Dịch vụ sửa chữa điện của Thần Nông <strong>ứng trực 24/7</strong>, thời gian đến hiện trường trung bình <strong>dưới 45 phút</strong> trong nội thành.</p>

                <h2>Dịch vụ bao gồm</h2>
                <ul>
                    <li>Chẩn đoán và xử lý sự cố mất điện, chập cháy, rò điện</li>
                    <li>Thay thế aptomat, ổ cắm, công tắc, đèn chiếu sáng</li>
                    <li>Đi lại dây điện âm tường, âm trần theo tiêu chuẩn an toàn</li>
                    <li>Lắp đặt tủ điện, chống sét lan truyền, ổn áp</li>
                    <li>Kiểm tra định kỳ, đo dòng rò, đo điện trở cách điện</li>
                </ul>

                <h2>Quy trình xử lý sự cố</h2>
                <ol>
                    <li><strong>Tiếp nhận yêu cầu</strong> — Hotline hoạt động 24/7, phân loại mức độ khẩn cấp.</li>
                    <li><strong>Điều kỹ thuật viên</strong> — Kỹ thuật viên gần nhất đến hiện trường trong 30-60 phút.</li>
                    <li><strong>Chẩn đoán &amp; báo giá</strong> — Dùng đồng hồ đo chuyên dụng, xác định nguyên nhân gốc, báo giá minh bạch trước khi sửa.</li>
                    <li><strong>Thi công</strong> — Ngắt điện an toàn, thay thế linh kiện chính hãng, kiểm tra hoạt động.</li>
                    <li><strong>Nghiệm thu</strong> — Đo lại các thông số an toàn, ký biên bản và hóa đơn.</li>
                </ol>

                <h2>Cam kết chất lượng</h2>
                <ul>
                    <li>Kỹ thuật viên có <strong>chứng chỉ hành nghề điện</strong> do Sở Công Thương cấp</li>
                    <li>Linh kiện sử dụng đều là <strong>hàng chính hãng</strong> có tem bảo hành nhà sản xuất</li>
                    <li><strong>Bảo hành 12 tháng</strong> cho mọi hạng mục sửa chữa</li>
                    <li>Miễn phí khảo sát và báo giá nếu bạn không quyết định sửa</li>
                </ul>

                <blockquote>An toàn điện là thứ không thể "làm tạm". Chúng tôi sửa cho đến khi đồng hồ đo nói là chuẩn, không phải đến khi thiết bị chạy được.</blockquote>
                HTML,
            'DV-003' => <<<'HTML'
                <p class="lead">Rò rỉ nước, tắc nghẽn ống thoát, vỡ ống âm tường — tất cả đều có thể xử lý <em>nhanh gọn và ít phá hoại</em> nếu được thực hiện bởi đội thợ có thiết bị chuyên dụng. Dịch vụ sửa chữa nước của Thần Nông sử dụng camera nội soi và máy dò rò rỉ siêu âm để định vị chính xác sự cố trước khi đục phá.</p>

                <h2>Dịch vụ bao gồm</h2>
                <ul>
                    <li>Dò và xử lý rò rỉ đường ống cấp nước âm tường, âm sàn</li>
                    <li>Thông tắc bồn cầu, lavabo, ống thoát sàn bằng máy lò xo</li>
                    <li>Thay thế, sửa chữa vòi nước, van khóa, bồn cầu, lavabo</li>
                    <li>Lắp đặt máy lọc nước, máy nước nóng, bơm tăng áp</li>
                    <li>Kiểm tra áp lực nước, chống thấm nhà vệ sinh</li>
                </ul>

                <h2>Quy trình xử lý</h2>
                <ol>
                    <li><strong>Khảo sát</strong> — Xác định vị trí sự cố bằng thiết bị dò chuyên dụng.</li>
                    <li><strong>Báo giá</strong> — Minh bạch vật tư, nhân công, phạm vi đục phá (nếu có).</li>
                    <li><strong>Thi công</strong> — Cố gắng hạn chế tối đa việc đục tường, ưu tiên phương án ít xâm lấn.</li>
                    <li><strong>Kiểm thử áp lực</strong> — Thử áp 48 giờ để bảo đảm không còn rò rỉ.</li>
                    <li><strong>Hoàn trả mặt bằng</strong> — Trát lại tường, lau dọn khu vực thi công.</li>
                </ol>

                <h2>Cam kết chất lượng</h2>
                <ul>
                    <li>Thiết bị dò rò hiện đại: camera nội soi, máy dò siêu âm, máy đo áp</li>
                    <li>Phụ kiện, ống nước <strong>Bình Minh, Dismy, Đệ Nhất</strong> chính hãng</li>
                    <li><strong>Bảo hành 6 tháng</strong> cho mọi hạng mục sửa chữa, thay thế</li>
                    <li>Cam kết hoàn tiền nếu sự cố tái phát tại đúng vị trí đã sửa</li>
                </ul>

                <h2>Lưu ý từ chuyên gia</h2>
                <p>80% rò rỉ âm tường không nhìn thấy mắt thường. Nếu hóa đơn nước tăng bất thường, hãy gọi kiểm tra sớm — chi phí sửa rò giai đoạn đầu thường chỉ bằng 1/5 chi phí khi tường đã thấm mốc nghiêm trọng.</p>
                HTML,
            'DV-004' => <<<'HTML'
                <p class="lead">Một tòa chung cư sạch sẽ là "bộ mặt" của cả cộng đồng cư dân. Gói vệ sinh chung cư của Thần Nông được thiết kế theo tiêu chuẩn khách sạn 4 sao, sử dụng <strong>máy chà sàn công nghiệp</strong> và hóa chất chuyên dụng <strong>Klenco, Goodmaid</strong> để làm sạch sâu mà không gây hại bề mặt.</p>

                <h2>Phạm vi vệ sinh</h2>
                <ul>
                    <li>Sảnh chính, hành lang các tầng, cầu thang bộ</li>
                    <li>Hệ thống thang máy: nội thất cabin, cửa, gương, inox</li>
                    <li>Hầm để xe: sàn, tường, khu vực rác</li>
                    <li>Khu vực kỹ thuật chung, phòng rác, khu đổ rác</li>
                    <li>Cửa kính, mặt dựng alu tầng trệt</li>
                </ul>

                <h2>Quy trình thực hiện</h2>
                <ol>
                    <li><strong>Khảo sát</strong> — Đo diện tích, đánh giá chất liệu sàn/tường để chọn hóa chất phù hợp.</li>
                    <li><strong>Lên kế hoạch</strong> — Chia ca làm, chọn khung giờ ít ảnh hưởng cư dân (thường 22h-6h).</li>
                    <li><strong>Thi công</strong> — Đội 5-8 nhân viên đồng bộ, có giám sát viên chịu trách nhiệm từng khu.</li>
                    <li><strong>Kiểm tra chéo</strong> — Giám sát kiểm tra theo checklist 42 mục trước khi bàn giao.</li>
                    <li><strong>Báo cáo ảnh</strong> — Gửi bộ ảnh trước/sau qua email cho Ban quản lý.</li>
                </ol>

                <h2>Cam kết chất lượng</h2>
                <ul>
                    <li>Nhân viên <strong>mặc đồng phục, đeo thẻ, bọc giày</strong> — lịch sự, nhận diện rõ</li>
                    <li>Hóa chất <strong>thân thiện môi trường</strong>, an toàn cho trẻ em và thú cưng</li>
                    <li>Có bảo hiểm trách nhiệm nghề nghiệp cho toàn bộ đội thi công</li>
                    <li>Sẵn sàng làm bù miễn phí nếu khu vực nào chưa đạt chuẩn</li>
                </ul>

                <blockquote>Chúng tôi coi mỗi tòa nhà như ngôi nhà thứ hai của mình. Sạch ở đây không phải sạch để nhìn — là sạch đủ để hít thở sâu.</blockquote>
                HTML,
            'DV-005' => <<<'HTML'
                <p class="lead">Thang máy là thiết bị mang tính <strong>sống còn</strong> trong tòa nhà cao tầng — không được phép dừng hoạt động vì lý do bảo trì kém. Gói bảo trì của Thần Nông tuân thủ quy trình <strong>Mitsubishi / Hitachi / Schindler</strong>, kiểm tra đủ 37 hạng mục theo TCVN 6395:2008.</p>

                <h2>37 hạng mục kiểm tra</h2>
                <ul>
                    <li><strong>Phòng máy:</strong> động cơ, hộp số, phanh từ, encoder, thông gió</li>
                    <li><strong>Cabin:</strong> cửa, cảm biến an toàn, nút bấm, đèn chiếu sáng, quạt, intercom</li>
                    <li><strong>Giếng thang:</strong> ray dẫn hướng, cáp tải, governor, limit switch</li>
                    <li><strong>Hố pit:</strong> giảm chấn, bơm thoát nước, công tắc cứu hộ</li>
                    <li><strong>Hệ thống điện:</strong> tủ điều khiển, pin lưu điện ARD, hiệu chuẩn dừng tầng</li>
                </ul>

                <h2>Quy trình bảo trì</h2>
                <ol>
                    <li><strong>Lập lịch</strong> — Bảo trì định kỳ hàng tháng, thông báo trước 3 ngày cho Ban quản lý.</li>
                    <li><strong>Thi công</strong> — Thực hiện vào giờ thấp điểm, có biển báo và nhân viên hướng dẫn cư dân.</li>
                    <li><strong>Thay dầu &amp; vệ sinh</strong> — Thay dầu hộp số 6 tháng/lần, vệ sinh ray và cáp mỗi kỳ.</li>
                    <li><strong>Hiệu chuẩn an toàn</strong> — Kiểm tra governor, phanh cứu hộ bằng tải thử.</li>
                    <li><strong>Biên bản nghiệm thu</strong> — Lập biên bản có ký xác nhận, lưu vào hồ sơ thiết bị.</li>
                </ol>

                <h2>Cam kết chất lượng</h2>
                <ul>
                    <li>Kỹ thuật viên có <strong>chứng chỉ huấn luyện an toàn vận hành thang máy</strong></li>
                    <li>Dầu mỡ, phụ kiện thay thế đều là <strong>chính hãng</strong> theo khuyến cáo nhà sản xuất</li>
                    <li>Cam kết <strong>ứng trực 24/7</strong>, xử lý sự cố trong 60 phút khi có cuộc gọi</li>
                    <li>Báo cáo tình trạng thiết bị hàng tháng, cảnh báo sớm linh kiện cần thay</li>
                </ul>

                <blockquote>Một cabin thang máy kẹt giữa tầng khiến cả tòa nhà mất niềm tin. Chúng tôi bảo trì để điều đó không bao giờ xảy ra.</blockquote>
                HTML,
            'DV-006' => <<<'HTML'
                <p class="lead">Hệ thống PCCC không được kiểm tra đúng chuẩn có thể bị <strong>đình chỉ hoạt động</strong> hoặc thậm chí trở thành thảm họa khi sự cố xảy ra. Dịch vụ kiểm tra PCCC của Thần Nông tuân thủ <strong>Thông tư 149/2020/TT-BCA</strong>, do kỹ thuật viên có chứng chỉ hành nghề PCCC thực hiện.</p>

                <h2>Các hạng mục kiểm tra</h2>
                <ul>
                    <li>Hệ thống báo cháy tự động: đầu báo khói, báo nhiệt, trung tâm, nút ấn</li>
                    <li>Hệ thống chữa cháy vách tường: họng nước, cuộn vòi, lăng phun, van</li>
                    <li>Hệ thống Sprinkler tự động: đầu phun, van báo động, bình khí</li>
                    <li>Máy bơm PCCC: bơm điện, bơm diesel, bơm bù áp</li>
                    <li>Bình chữa cháy xách tay: áp suất, niêm phong, hạn sử dụng</li>
                    <li>Đèn thoát hiểm, đèn chỉ dẫn, sơ đồ thoát nạn</li>
                </ul>

                <h2>Quy trình kiểm tra</h2>
                <ol>
                    <li><strong>Khảo sát hồ sơ</strong> — Rà soát bản vẽ hoàn công, biên bản nghiệm thu gần nhất.</li>
                    <li><strong>Kiểm tra hiện trường</strong> — Đo áp suất, test đầu báo, thử vận hành bơm.</li>
                    <li><strong>Lập biên bản</strong> — Ghi chi tiết hạng mục đạt/không đạt, đề xuất khắc phục.</li>
                    <li><strong>Báo cáo kỹ thuật</strong> — Văn bản có dấu đỏ phục vụ làm việc với cơ quan quản lý.</li>
                    <li><strong>Hỗ trợ khắc phục</strong> — Tư vấn giải pháp, báo giá thay thế linh kiện nếu cần.</li>
                </ol>

                <h2>Cam kết chất lượng</h2>
                <ul>
                    <li>Kỹ thuật viên có <strong>chứng chỉ huấn luyện PCCC</strong> do Cục Cảnh sát PCCC cấp</li>
                    <li>Thiết bị kiểm tra đạt chuẩn, có tem kiểm định định kỳ</li>
                    <li>Báo cáo <strong>đầy đủ pháp lý</strong>, đủ điều kiện làm hồ sơ nghiệm thu định kỳ</li>
                    <li>Tư vấn miễn phí quy trình thoát nạn, huấn luyện tình huống cho cư dân</li>
                </ul>

                <blockquote>Thiết bị PCCC không dùng đến là điều may mắn. Nhưng nếu thiếu may mắn một lần, thiết bị phải hoạt động — không có lần thứ hai.</blockquote>
                HTML,
            default => '',
        };
    }

    /** @return list<array{employee_code: string, name: string, email: string, project_codes: list<string>}> */
    public static function accounts(string $orgCode): array
    {
        return match ($orgCode) {
            self::TNP_SERVICES => [
                ['employee_code' => 'admin', 'name' => 'Administrator', 'email' => 'admin@tnp.com', 'project_codes' => ['DA-CC-A', 'DA-CC-B']],
                ['employee_code' => 'NV001', 'name' => 'Nguyễn Văn Hùng', 'email' => 'hung.nv@tnp.com', 'project_codes' => ['DA-CC-A']],
                ['employee_code' => 'NV002', 'name' => 'Trần Thị Mai', 'email' => 'mai.tt@tnp.com', 'project_codes' => ['DA-CC-A']],
                ['employee_code' => 'NV003', 'name' => 'Lê Hoàng Nam', 'email' => 'nam.lh@tnp.com', 'project_codes' => ['DA-CC-A', 'DA-CC-B']],
                ['employee_code' => 'NV004', 'name' => 'Phạm Đức Minh', 'email' => 'minh.pd@tnp.com', 'project_codes' => ['DA-CC-A']],
                ['employee_code' => 'NV005', 'name' => 'Võ Thị Lan', 'email' => 'lan.vt@tnp.com', 'project_codes' => ['DA-CC-A']],
                ['employee_code' => 'NV006', 'name' => 'Hoàng Minh Tú', 'email' => 'tu.hm@tnp.com', 'project_codes' => ['DA-CC-B']],
                ['employee_code' => 'NV007', 'name' => 'Đinh Thảo Vy', 'email' => 'vy.dt@tnp.com', 'project_codes' => ['DA-CC-B']],
            ],
            self::E2E_TESTING => [
                ['employee_code' => 'admin', 'name' => 'E2E Admin', 'email' => 'admin@e2e.com', 'project_codes' => ['DA-CC-A']],
                ['employee_code' => 'NV001', 'name' => 'E2E Staff', 'email' => 'staff@e2e.com', 'project_codes' => ['DA-CC-A']],
            ],
            default => [],
        };
    }
}
