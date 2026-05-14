<?php

namespace App\Listeners;

use App\Events\QuoteCreatedForTicket;
use App\Modules\Platform\Customer\Models\Customer;
use App\Modules\PMC\Quote\Notifications\QuoteCreatedNotification;
use App\Support\PublicTicketUrlBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendQuoteCreatedEmail implements ShouldQueue
{
    public function handle(QuoteCreatedForTicket $event): void
    {
        $customer = Customer::query()->find($event->customerId);

        if (! $customer || ! $customer->email) {
            Log::info('Skipping QuoteCreated email: customer has no email', [
                'customer_id' => $event->customerId,
                'quote_code' => $event->payload['quote_code'] ?? null,
            ]);

            return;
        }

        $customer->notify(new QuoteCreatedNotification([
            'customer_name' => $event->payload['customer_name'],
            'ticket_code' => $event->payload['ticket_code'],
            'ticket_subject' => $event->payload['ticket_subject'],
            'quote_code' => $event->payload['quote_code'],
            'quote_total_amount' => $event->payload['quote_total_amount'],
            'quote_lines' => $event->payload['quote_lines'],
            'public_url' => PublicTicketUrlBuilder::build(
                $event->payload['tenant_subdomain'] ?? null,
                $event->payload['ticket_code'],
            ),
        ]));
    }
}
