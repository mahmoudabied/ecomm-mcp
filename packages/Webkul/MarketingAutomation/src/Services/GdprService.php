<?php

namespace Webkul\MarketingAutomation\Services;

use Illuminate\Support\Facades\DB;
use Webkul\Customer\Contracts\Customer;
use Webkul\MarketingAutomation\Repositories\AbandonedCartRepository;
use Webkul\MarketingAutomation\Repositories\BackInStockSubscriptionRepository;
use Webkul\MarketingAutomation\Repositories\ConsentEventRepository;
use Webkul\MarketingAutomation\Repositories\ConsentRepository;
use Webkul\MarketingAutomation\Repositories\ContactRepository;
use Webkul\MarketingAutomation\Repositories\EnrolmentEventRepository;
use Webkul\MarketingAutomation\Repositories\EnrolmentRepository;
use Webkul\MarketingAutomation\Repositories\MessageEventRepository;
use Webkul\MarketingAutomation\Repositories\MessageLogRepository;

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
