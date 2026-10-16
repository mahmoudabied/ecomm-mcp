<?php

namespace Webkul\MarketingAutomation\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\MarketingAutomation\Contracts\Contact;

class ContactRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'Webkul\MarketingAutomation\Contracts\Contact';
    }

    /**
     * The guest contact of the channel captured with this email.
     */
    public function findGuestByEmail(int $channelId, string $email): ?Contact
    {
        return $this->model
            ->where('channel_id', $channelId)
            ->whereNull('customer_id')
            ->where('email', strtolower($email))
            ->first();
    }

    /**
     * Ids of the channel's contacts matching the customer, the email or the E.164 phone.
     *
     * @return array<int, int>
     */
    public function idsMatching(int $channelId, ?int $customerId, ?string $email, ?string $phoneE164): array
    {
        if (
            $customerId === null
            && $email === null
            && $phoneE164 === null
        ) {
            return [];
        }

        return $this->model
            ->where('channel_id', $channelId)
            ->where(fn ($query) => $query
                ->when($customerId !== null, fn ($query) => $query->orWhere('customer_id', $customerId))
                ->when($email !== null, fn ($query) => $query->orWhere('email', strtolower((string) $email)))
                ->when($phoneE164 !== null, fn ($query) => $query->orWhere('phone_e164', $phoneE164)))
            ->pluck('id')
            ->all();
    }

    /**
     * Ids of the contacts in every channel that hold this customer's data.
     *
     * @return array<int, int>
     */
    public function idsOfCustomer(int $customerId, string $email): array
    {
        return $this->model
            ->where(fn ($query) => $query
                ->where('customer_id', $customerId)
                ->when($email !== '', fn ($query) => $query->orWhere('email', strtolower($email))))
            ->pluck('id')
            ->all();
    }
}
