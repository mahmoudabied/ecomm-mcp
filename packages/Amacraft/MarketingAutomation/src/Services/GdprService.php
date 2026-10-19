<?php

namespace Amacraft\MarketingAutomation\Services;

use Illuminate\Support\Facades\DB;
use Webkul\Customer\Contracts\Customer;
use Amacraft\MarketingAutomation\Repositories\AbandonedCartRepository;
use Amacraft\MarketingAutomation\Repositories\BackInStockSubscriptionRepository;
use Amacraft\MarketingAutomation\Repositories\ConsentEventRepository;
use Amacraft\MarketingAutomation\Repositories\ConsentRepository;
use Amacraft\MarketingAutomation\Repositories\ContactRepository;
use Amacraft\MarketingAutomation\Repositories\EnrolmentEventRepository;
use Amacraft\MarketingAutomation\Repositories\EnrolmentRepository;
use Amacraft\MarketingAutomation\Repositories\MessageEventRepository;
use Amacraft\MarketingAutomation\Repositories\MessageLogRepository;

class GdprService
{
    /**
     * Create a new GDPR service instance.
     */
    public function __construct(
        protected ContactRepository $contactRepository,
        protected ConsentRepository $consentRepository,
        protected ConsentEventRepository $consentEventRepository,
        protected EnrolmentRepository $enrolmentRepository,
        protected EnrolmentEventRepository $enrolmentEventRepository,
        protected MessageLogRepository $messageLogRepository,
        protected MessageEventRepository $messageEventRepository,
        protected AbandonedCartRepository $abandonedCartRepository,
        protected BackInStockSubscriptionRepository $backInStockSubscriptionRepository,
    ) {}

    /**
     * Every automation row of the customer, grouped for the export file.
     *
     * @return array{consents: array, consent_events: array, enrolments: array, messages: array}
     */
    public function export(Customer $customer): array
    {
        $contactIds = $this->contactIdsOf($customer);

        return [
            'consents'       => $this->consentRepository->findWhereIn('contact_id', $contactIds)->toArray(),
            'consent_events' => $this->consentEventRepository->findWhereIn('contact_id', $contactIds)->toArray(),
            'enrolments'     => $this->enrolmentRepository->findWhereIn('contact_id', $contactIds)->toArray(),
            'messages'       => $this->messageLogRepository->findWhereIn('contact_id', $contactIds)->toArray(),
        ];
    }

    /**
     * Remove every automation trace of the customer; step stats and attributions hold no personal data.
     */
    public function erase(Customer $customer): void
    {
        $contactIds = $this->contactIdsOf($customer);

        if ($contactIds === []) {
            return;
        }

        DB::transaction(function () use ($contactIds) {
            $enrolmentIds = $this->enrolmentRepository->findWhereIn('contact_id', $contactIds, ['id'])->pluck('id')->all();

            $messageLogIds = $this->messageLogRepository->findWhereIn('contact_id', $contactIds, ['id'])->pluck('id')->all();

            $this->messageEventRepository->deleteWhere([['message_log_id', 'IN', $messageLogIds]]);

            $this->messageLogRepository->deleteWhere([['id', 'IN', $messageLogIds]]);

            $this->enrolmentEventRepository->deleteWhere([['enrolment_id', 'IN', $enrolmentIds]]);

            $this->enrolmentRepository->deleteWhere([['id', 'IN', $enrolmentIds]]);

            $this->consentEventRepository->deleteWhere([['contact_id', 'IN', $contactIds]]);

            $this->consentRepository->deleteWhere([['contact_id', 'IN', $contactIds]]);

            $this->abandonedCartRepository->deleteWhere([['contact_id', 'IN', $contactIds]]);

            $this->backInStockSubscriptionRepository->deleteWhere([['contact_id', 'IN', $contactIds]]);

            $this->contactRepository->deleteWhere([['id', 'IN', $contactIds]]);
        });
    }

    /**
     * The contacts that carry this customer's data, including those captured as a guest.
     *
     * @return array<int, int>
     */
    protected function contactIdsOf(Customer $customer): array
    {
        return $this->contactRepository->idsOfCustomer($customer->id, (string) $customer->email);
    }
}
