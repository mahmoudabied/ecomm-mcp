<?php

namespace Webkul\MarketingAutomation\Enums;

enum SendStatus: string
{
    case Accepted = 'accepted';

    case RetryableError = 'retryable_error';

    case PermanentError = 'permanent_error';
}
