<?php

namespace App\Listeners;

use App\Events\TicketReceivedByOrganization;
use App\Modules\Platform\Customer\Models\Customer;
use App\Modules\Platform\Ticket\Notifications\TicketReceivedNotification;
use App\Support\PublicTicketUrlBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendTicketReceivedEmail implements ShouldQueue
{
    public function handle(TicketReceivedByOrganization $event): void
    {
        $customer = Customer::query()->find($event->customerId);

        if (! $customer || ! $customer->email) {
            Log::info('Skipping TicketReceived email: customer has no email', [
                'customer_id' => $event->customerId,
                'ticket_code' => $event->payload['ticket_code'] ?? null,
            ]);

            return;
        }

        $customer->notify(new TicketReceivedNotification([
            'customer_name' => $event->payload['customer_name'],
            'ticket_code' => $event->payload['ticket_code'],
            'ticket_subject' => $event->payload['ticket_subject'],
            'organization_name' => $event->payload['organization_name'] ?? null,
            'public_url' => PublicTicketUrlBuilder::build(
                $event->payload['tenant_subdomain'] ?? null,
                $event->payload['ticket_code'],
            ),
        ]));
    }
}
