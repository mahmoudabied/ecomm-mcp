<?php

namespace Webkul\MarketingAutomation\Exceptions;

use RuntimeException;
use Webkul\MarketingAutomation\DTOs\SendResult;

class RetryableSendException extends RuntimeException
{
    /**
     * Create a new exception instance.
     */
    public function __construct(
        public readonly SendResult $result,
    ) {
        parent::__construct($result->errorMessage ?? 'The provider asked us to retry.');
    }
}
