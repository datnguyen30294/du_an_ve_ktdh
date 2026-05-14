<?php

namespace Database\Seeders\Tenant;

use App\Modules\PMC\Customer\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        if (Customer::query()->exists()) {
            return;
        }

        $samples = [
            ['full_name' => 'Nguyễn Văn An', 'phone' => '0901234001', 'email' => 'an.nguyen@example.com'],
            ['full_name' => 'Trần Thị Bình', 'phone' => '0901234002', 'email' => 'binh.tran@example.com'],
            ['full_name' => 'Lê Minh Công', 'phone' => '0901234003', 'email' => null],
            ['full_name' => 'Phạm Thu Dương', 'phone' => '0901234004', 'email' => 'duong.pham@example.com'],
            ['full_name' => 'Hoàng Quốc Việt', 'phone' => '0901234005', 'email' => null],
            ['full_name' => 'Vũ Thị Hương', 'phone' => '0901234006', 'email' => 'huong.vu@example.com'],
            ['full_name' => 'Đỗ Văn Khánh', 'phone' => '0901234007', 'email' => null],
            ['full_name' => 'Bùi Thanh Loan', 'phone' => '0901234008', 'email' => 'loan.bui@example.com'],
            ['full_name' => 'Ngô Hữu Minh', 'phone' => '0901234009', 'email' => null],
            ['full_name' => 'Đặng Phương Nga', 'phone' => '0901234010', 'email' => 'nga.dang@example.com'],
        ];

        foreach ($samples as $data) {
            /** @var Customer $customer */
            $customer = Customer::query()->create([
                'full_name' => $data['full_name'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'note' => null,
            ]);
            // Observer auto-fills `code` (KH-00001, KH-00002, ...)
            $customer->refresh();
        }
    }
}
