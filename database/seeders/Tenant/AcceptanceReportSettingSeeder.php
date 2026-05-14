<?php

namespace Database\Seeders\Tenant;

use App\Modules\PMC\AcceptanceReport\Services\AcceptanceReportService;
use App\Modules\PMC\Setting\Models\SystemSetting;
use Illuminate\Database\Seeder;

class AcceptanceReportSettingSeeder extends Seeder
{
    public function run(): void
    {
        $group = AcceptanceReportService::SETTING_GROUP;

        $defaults = [
            AcceptanceReportService::SETTING_KEY_TITLE => 'Biên bản nghiệm thu',
            AcceptanceReportService::SETTING_KEY_TEMPLATE => $this->defaultHtml(),
        ];

        foreach ($defaults as $key => $value) {
            SystemSetting::updateOrCreate(
                ['group' => $group, 'key' => $key],
                ['value' => $value],
            );
        }
    }

    private function defaultHtml(): string
    {
        return <<<'HTML'
<h2 style="text-align:center;margin:0 0 4px">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</h2>
<p style="text-align:center;margin:0"><strong>Độc lập – Tự do – Hạnh phúc</strong></p>
<p style="text-align:center;margin:4px 0 16px">—— o0o ——</p>

<h2 style="text-align:center;margin:0 0 4px">BIÊN BẢN NGHIỆM THU</h2>
<p style="text-align:center;margin:0 0 16px"><em>Ngày {{today}}</em></p>

<p><strong>Đơn vị thi công:</strong> {{organization_name}}</p>
<p><strong>Dự án:</strong> {{project_name}}</p>
<p><strong>Khách hàng:</strong> {{customer_name}} — {{customer_phone}}</p>
<p><strong>Địa chỉ:</strong> {{customer_address}}</p>
<p><strong>Mã đơn hàng:</strong> {{order_code}} &nbsp;·&nbsp; <strong>Ngày tạo:</strong> {{order_date}}</p>
<p><strong>Nội dung yêu cầu:</strong> {{ticket_subject}}</p>

<h3 style="margin-top:20px">I. Chi tiết hạng mục nghiệm thu</h3>
{{order_lines_table}}

<p style="margin-top:12px"><strong>Tổng giá trị:</strong> {{order_total}}</p>

<h3 style="margin-top:20px">II. Kết luận</h3>
<p>Hai bên thống nhất xác nhận các hạng mục trên đã được thi công và hoàn thành theo đúng yêu cầu.</p>
<p><strong>Ghi chú:</strong> {{note}}</p>

<p style="margin-top:16px"><em>Biên bản được lập thành 02 bản, mỗi bên giữ 01 bản có giá trị pháp lý như nhau.</em></p>

<table style="width:100%;margin-top:24px">
  <tr>
    <td style="width:50%;text-align:center;vertical-align:top">
      <strong>ĐẠI DIỆN KHÁCH HÀNG</strong><br>
      <em>(Ký, ghi rõ họ tên)</em>
      <div style="height:80px"></div>
      {{customer_name}}
    </td>
    <td style="width:50%;text-align:center;vertical-align:top">
      <strong>ĐẠI DIỆN ĐƠN VỊ THI CÔNG</strong><br>
      <em>(Ký, ghi rõ họ tên)</em>
      <div style="height:80px"></div>
      {{organization_name}}
    </td>
  </tr>
</table>
HTML;
    }
}
