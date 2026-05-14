<?php

namespace App\Modules\PMC\Quote\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuoteCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 180, 600];

    /**
     * @param  array{
     *   customer_name: string,
     *   ticket_code: string,
     *   ticket_subject: string,
     *   quote_code: string,
     *   quote_total_amount: float,
     *   quote_lines: array<int, array{name: string, quantity: int, unit: string|null, line_amount: float}>,
     *   public_url: string|null
     * }  $payload
     */
    public function __construct(public array $payload) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticketCode = $this->payload['ticket_code'];
        $quoteCode = $this->payload['quote_code'];
        $total = number_format($this->payload['quote_total_amount'], 0, ',', '.');

        $mail = (new MailMessage)
            ->subject("[#{$ticketCode}] Báo giá mới đã được gửi cho bạn")
            ->greeting("Chào {$this->payload['customer_name']},")
            ->line("Yêu cầu \"{$this->payload['ticket_subject']}\" đã có báo giá mới (mã báo giá: {$quoteCode}).")
            ->line("**Tổng tiền: {$total} đ**");

        foreach ($this->payload['quote_lines'] as $line) {
            $qty = $line['quantity'];
            $unit = $line['unit'] ? " {$line['unit']}" : '';
            $amount = number_format((float) $line['line_amount'], 0, ',', '.');
            $mail->line("- {$line['name']} (x{$qty}{$unit}): {$amount} đ");
        }

        if (! empty($this->payload['public_url'])) {
            $mail->action('Xem và xác nhận báo giá', $this->payload['public_url']);
        }

        return $mail
            ->line('Báo giá này có hiệu lực cho đến khi có bản mới thay thế.')
            ->line('Vui lòng xác nhận trên trang theo dõi để chúng tôi tiến hành thi công.')
            ->salutation('Trân trọng, TNP Services');
    }
}
