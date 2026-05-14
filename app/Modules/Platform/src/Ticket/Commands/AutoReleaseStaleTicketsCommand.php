<?php

namespace App\Modules\Platform\Ticket\Commands;

use App\Modules\Platform\Ticket\Contracts\TicketServiceInterface;
use Illuminate\Console\Command;

class AutoReleaseStaleTicketsCommand extends Command
{
    protected $signature = 'app:auto-release-stale-tickets';

    protected $description = 'Auto-release tickets back to pool when OG ticket status unchanged for configured timeout';

    public function handle(TicketServiceInterface $ticketService): int
    {
        config(['audit.console' => true]);

        $result = $ticketService->autoReleaseStaleTickets();

        if ($result['checked'] === 0) {
            $this->info('No stale tickets found.');
        } else {
            $this->info("Done. Released {$result['released']}/{$result['checked']} stale ticket(s).");
        }

        return self::SUCCESS;
    }
}
