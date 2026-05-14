<?php

namespace App\Modules\Platform\Ticket\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketReceivedNotification extends Notification implements ShouldQueue
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
     *   organization_name: string|null,
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
        $code = $this->payload['ticket_code'];
        $orgName = $this->payload['organization_name'] ?? null;
        $receivedBy = $orgName ? "bởi {$orgName}" : 'bởi đội xử lý';

        $mail = (new MailMessage)
            ->subject("[#{$code}] Yêu cầu của bạn đã được tiếp nhận")
            ->greeting("Chào {$this->payload['customer_name']},")
            ->line("Yêu cầu \"{$this->payload['ticket_subject']}\" (mã #{$code}) đã được tiếp nhận {$receivedBy}.")
            ->line('Chúng tôi sẽ sớm liên hệ với bạn để khảo sát và gửi báo giá.');

        if (! empty($this->payload['public_url'])) {
            $mail->action('Theo dõi yêu cầu', $this->payload['public_url']);
        }

        return $mail
            ->line('Cảm ơn bạn đã sử dụng dịch vụ.')
            ->salutation('Trân trọng, TNP Services');
    }
}
