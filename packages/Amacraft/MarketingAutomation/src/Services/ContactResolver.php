<?php

namespace Amacraft\MarketingAutomation\Services;

use Illuminate\Support\Facades\DB;
use Webkul\Customer\Models\Customer;
use Amacraft\MarketingAutomation\Models\AbandonedCart;
use Amacraft\MarketingAutomation\Models\BackInStockSubscription;
use Amacraft\MarketingAutomation\Models\Consent;
use Amacraft\MarketingAutomation\Models\ConsentEvent;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Models\Enrolment;
use Amacraft\MarketingAutomation\Repositories\ContactRepository;

class ContactResolver
{
    /**
     * Create a new contact resolver instance.
     */
    public function __construct(
        protected ContactRepository $contactRepository,
        protected PhoneNormalizer $phoneNormalizer,
    ) {}

    /**
     * Find or create the contact for the given identity hints within a channel.
     *
     * @param  array{first?: ?string, last?: ?string}  $names
     */
    public function resolve(
        int $channelId,
        ?int $customerId,
        ?string $email = null,
        ?string $phone = null,
        ?string $country = null,
        ?string $locale = null,
        array $names = [],
    ): Contact {
        $email = $email !== null ? strtolower(trim($email)) : null;
        $phoneE164 = $this->phoneNormalizer->normalize($phone, $country);
        $locale = $locale ?? app()->getLocale();

        if ($customerId !== null) {
            $customer = Customer::query()->find($customerId);

            if ($customer !== null) {
                $email ??= strtolower((string) $customer->email);
                $phone ??= $customer->phone;
                $phoneE164 = $phoneE164 ?? $this->phoneNormalizer->normalize($customer->phone, $country);
                $names['first'] ??= $customer->first_name;
                $names['last'] ??= $customer->last_name;
            }
        }

        $attributes = [
            'channel_id'  => $channelId,
            'customer_id' => $customerId,
            'email'       => $email,
            'phone_e164'  => $phoneE164,
            'phone_raw'   => $phone,
            'first_name'  => $names['first'] ?? null,
            'last_name'   => $names['last'] ?? null,
            'locale'      => $locale,
            'country'     => $country,
        ];

        $contact = $this->findExisting($channelId, $customerId, $email, $phoneE164);

        if ($contact === null) {
            return $this->contactRepository->create($attributes);
        }

        return $this->fillMissingAttributes($contact, $attributes);
    }

    /**
     * Get the contact for a registered customer, creating it when needed.
     */
    public function forCustomer(Customer $customer, int $channelId): Contact
    {
        return $this->resolve(
            channelId: $channelId,
            customerId: $customer->id,
            email: $customer->email,
            phone: $customer->phone,
            locale: $customer->locale ?? null,
        );
    }

    /**
     * Merge a guest contact into the customer contact and delete the guest.
     */
    public function merge(Contact $guest, Contact $customerContact): Contact
    {
        DB::transaction(function () use ($guest, $customerContact) {
            $this->mergeConsents($guest, $customerContact);

            ConsentEvent::where('contact_id', $guest->id)->update(['contact_id' => $customerContact->id]);

            $this->mergeEnrolments($guest, $customerContact);

            AbandonedCart::where('contact_id', $guest->id)->update(['contact_id' => $customerContact->id]);

            BackInStockSubscription::where('contact_id', $guest->id)->update(['contact_id' => $customerContact->id]);

            $guest->delete();
        });

        return $customerContact;
    }

    /**
     * Look up an existing contact by customer id, then email, then phone.
     */
    protected function findExisting(int $channelId, ?int $customerId, ?string $email, ?string $phoneE164): ?Contact
    {
        $find = fn (array $conditions): ?Contact => Contact::query()
            ->where('channel_id', $channelId)
            ->where($conditions)
            ->first();

        if ($customerId !== null) {
            $contact = $find(['customer_id' => $customerId]);

            if ($contact !== null) {
                return $contact;
            }
        }

        if ($email !== null) {
            $contact = $find(['email' => $email]);

            if ($contact !== null) {
                return $contact;
            }
        }

        if ($phoneE164 !== null) {
            return $find(['phone_e164' => $phoneE164]);
        }

        return null;
    }

    /**
     * Complete any identity fields the matched contact is still missing.
     */
    protected function fillMissingAttributes(Contact $contact, array $attributes): Contact
    {
        $fill = [];

        foreach (['customer_id', 'email', 'phone_e164', 'phone_raw', 'first_name', 'last_name', 'locale', 'country'] as $field) {
            if (empty($contact->{$field}) && ! empty($attributes[$field])) {
                $fill[$field] = $attributes[$field];
            }
        }

        if ($fill !== []) {
            $contact->fill($fill)->save();
        }

        return $contact;
    }

    /**
     * Keep, per message channel, the consent row with the later change date.
     */
    protected function mergeConsents(Contact $guest, Contact $customerContact): void
    {
        $guestConsents = Consent::query()->where('contact_id', $guest->id)->get();

        foreach ($guestConsents as $guestConsent) {
            $customerConsent = Consent::query()
                ->where('contact_id', $customerContact->id)
                ->where('channel', $guestConsent->channel)
                ->first();

            if ($customerConsent === null || $guestConsent->changed_at->greaterThan($customerConsent->changed_at)) {
                $customerConsent?->delete();

                $guestConsent->update(['contact_id' => $customerContact->id]);

                continue;
            }

            $guestConsent->delete();
        }
    }

    /**
     * Move enrolments over, cancelling duplicates of the same journey.
     */
    protected function mergeEnrolments(Contact $guest, Contact $customerContact): void
    {
        $guestEnrolments = Enrolment::query()->where('contact_id', $guest->id)->get();

        foreach ($guestEnrolments as $guestEnrolment) {
            $duplicate = Enrolment::query()
                ->where('contact_id', $customerContact->id)
                ->where('flow_id', $guestEnrolment->flow_id)
                ->where('trigger_key', $guestEnrolment->trigger_key)
                ->exists();

            if ($duplicate) {
                $guestEnrolment->update([
                    'status'      => 'cancelled',
                    'exit_reason' => 'merged',
                ]);

                continue;
            }

            $guestEnrolment->update(['contact_id' => $customerContact->id]);
        }
    }
}
