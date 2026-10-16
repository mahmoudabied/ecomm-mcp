<?php

namespace Webkul\MarketingAutomation\Listeners;

use Webkul\Customer\Contracts\Customer;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\GDPR\Contracts\GDPRDataRequest;
use Webkul\MarketingAutomation\Services\GdprService;

class GdprListener
{
    /**
     * Create a new GDPR listener instance.
     */
    public function __construct(
        protected CustomerRepository $customerRepository,
        protected GdprService $gdprService,
    ) {}

    /**
     * Register the listeners; erasure runs before deletion, while the customer link still exists.
     */
    public function subscribe($events): void
    {
        $events->listen('customer.delete.before', [self::class, 'customerDeleted']);

        $events->listen('customer.account.gdpr-request.update.after', [self::class, 'requestUpdated']);
    }

    /**
     * The admin removed the customer: erase their automation data.
     */
    public function customerDeleted(Customer $customer): void
    {
        $this->gdprService->erase($customer);
    }

    /**
     * A GDPR request changed state: a completed delete request erases the customer's data.
     */
    public function requestUpdated(GDPRDataRequest $request): void
    {
        if (
            $request->type !== 'delete'
            || $request->status !== 'completed'
        ) {
            return;
        }

        $customer = $this->customerRepository->find($request->customer_id);

        if ($customer !== null) {
            $this->gdprService->erase($customer);
        }
    }
}
