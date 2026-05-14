<?php

namespace App\Modules\PMC\OgTicket\ExternalServices;

use App\Modules\Platform\Customer\Models\Customer;
use App\Modules\Platform\Ticket\Enums\TicketStatus;
use App\Modules\Platform\Ticket\Models\Ticket;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TicketExternalService implements TicketExternalServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function getAvailableTickets(array $filters): LengthAwarePaginator
    {
        $query = Ticket::query()->with('attachments')->available();

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        $perPage = $filters['per_page'] ?? 10;

        return $query->paginate($perPage);
    }

    /**
     * Claim a ticket atomically using DB lock.
     * Returns snapshot data (read inside lock) on success.
     * Returns null if ticket not found, false if already claimed or wrong status.
     *
     * @return array{id: int, requester_name: string, requester_phone: string, apartment_name: string|null, project_id: int, subject: string, description: string|null, address: string|null, latitude: string|null, longitude: string|null, channel: string}|false|null
     */
    public function claimTicket(int $ticketId, string $orgId): array|false|null
    {
        return DB::transaction(function () use ($ticketId, $orgId): array|false|null {
            $ticket = Ticket::lockForUpdate()->find($ticketId);

            if (! $ticket) {
                return null;
            }

            if ($ticket->claimed_by_org_id !== null || $ticket->status !== TicketStatus::Pending) {
                return false;
            }

            $ticket->update([
                'status' => TicketStatus::Received->value,
                'claimed_by_org_id' => $orgId,
                'claimed_at' => now(),
            ]);

            return [
                'id' => $ticket->id,
                'requester_name' => $ticket->requester_name,
                'requester_phone' => $ticket->requester_phone,
                'apartment_name' => $ticket->apartment_name,
                'project_id' => $ticket->project_id,
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'address' => $ticket->address,
                'latitude' => $ticket->latitude,
                'longitude' => $ticket->longitude,
                'channel' => $ticket->channel->value,
            ];
        });
    }

    public function updateTicketStatus(int $ticketId, string $status): void
    {
        Ticket::where('id', $ticketId)->update(['status' => $status]);
    }

    /**
     * @param  array{
     *     requester_name: string,
     *     requester_phone: string,
     *     subject: string,
     *     description?: ?string,
     *     address?: ?string,
     *     apartment_name?: ?string,
     *     latitude?: float|string|null,
     *     longitude?: float|string|null,
     *     channel: string,
     *     project_id?: ?int,
     * }  $data
     */
    public function createTicketForOrg(array $data, string $orgId): Ticket
    {
        return DB::connection((new Ticket)->getConnectionName())
            ->transaction(function () use ($data, $orgId): Ticket {
                $customer = $this->resolvePlatformCustomer(
                    $data['requester_phone'],
                    $data['requester_name'],
                    $data['address'] ?? null,
                );

                $code = $this->generateTicketCode();

                /** @var Ticket */
                return Ticket::create([
                    'code' => $code,
                    'customer_id' => $customer->id,
                    'requester_name' => $data['requester_name'],
                    'requester_phone' => $data['requester_phone'],
                    'apartment_name' => $data['apartment_name'] ?? null,
                    'project_id' => $data['project_id'] ?? null,
                    'subject' => $data['subject'],
                    'description' => $data['description'] ?? null,
                    'address' => $data['address'] ?? null,
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                    'channel' => $data['channel'],
                    'status' => TicketStatus::Received->value,
                    'claimed_by_org_id' => $orgId,
                    'claimed_at' => now(),
                    'is_from_pool' => false,
                ]);
            });
    }

    public function deleteTicket(int $ticketId): void
    {
        Ticket::where('id', $ticketId)->delete();
    }

    /**
     * Find customer by phone; update name/address if already exists, otherwise create.
     * Does NOT overwrite an existing email with null.
     */
    private function resolvePlatformCustomer(string $phone, string $name, ?string $address): Customer
    {
        $customer = Customer::query()->where('phone', $phone)->first();

        if ($customer) {
            $customer->update([
                'name' => $name,
                'address' => $address ?? $customer->address,
            ]);

            return $customer;
        }

        /** @var Customer */
        return Customer::create([
            'name' => $name,
            'phone' => $phone,
            'address' => $address,
        ]);
    }

    private function generateTicketCode(): string
    {
        $year = (int) date('Y');
        $prefix = "TK-{$year}-";

        $last = Ticket::query()
            ->where('code', 'like', $prefix.'%')
            ->orderByDesc('code')
            ->value('code');

        $sequence = 1;
        if ($last) {
            $parts = explode('-', $last);
            $sequence = (int) end($parts) + 1;
        }

        return sprintf('TK-%d-%03d', $year, $sequence);
    }

    public function syncTicketStatus(int $ticketId, OgTicketStatus $newStatus): void
    {
        $inProgressStatuses = [
            OgTicketStatus::Assigned,
            OgTicketStatus::Surveying,
            OgTicketStatus::Quoted,
            OgTicketStatus::Approved,
            OgTicketStatus::Rejected,
            OgTicketStatus::Ordered,
            OgTicketStatus::InProgress,
            OgTicketStatus::Accepted,
        ];

        if (in_array($newStatus, $inProgressStatuses, true)) {
            $this->updateTicketStatus($ticketId, TicketStatus::InProgress->value);
        } elseif ($newStatus === OgTicketStatus::Completed) {
            $this->updateTicketStatus($ticketId, TicketStatus::Completed->value);
        }
    }

    public function releaseTicket(int $ticketId): void
    {
        $ticket = Ticket::find($ticketId);

        if (! $ticket) {
            return;
        }

        if ($ticket->is_from_pool) {
            $ticket->update([
                'status' => TicketStatus::Pending->value,
                'claimed_by_org_id' => null,
                'claimed_at' => null,
            ]);
        } else {
            $ticket->update([
                'status' => TicketStatus::Cancelled->value,
            ]);
        }
    }

    /**
     * @param  array<int>  $ticketIds
     * @return Collection<int|string, string>
     */
    public function getTicketCodes(array $ticketIds): Collection
    {
        if (empty($ticketIds)) {
            return collect();
        }

        return Ticket::query()
            ->whereIn('id', $ticketIds)
            ->pluck('code', 'id');
    }

    /**
     * @return array{customer_id: int, customer_name: string, ticket_code: string, ticket_subject: string}|null
     */
    public function getNotificationInfo(int $ticketId): ?array
    {
        $ticket = Ticket::query()->with('customer')->find($ticketId);

        if (! $ticket || ! $ticket->customer) {
            return null;
        }

        return [
            'customer_id' => $ticket->customer->id,
            'customer_name' => $ticket->customer->name,
            'ticket_code' => $ticket->code,
            'ticket_subject' => $ticket->subject,
        ];
    }
}
