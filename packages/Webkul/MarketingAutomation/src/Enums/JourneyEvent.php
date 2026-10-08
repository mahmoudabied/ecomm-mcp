<?php

namespace Webkul\MarketingAutomation\Enums;

enum JourneyEvent: string
{
    case Entered = 'entered';

    case Waited = 'waited';

    case ConditionYes = 'condition_yes';

    case ConditionNo = 'condition_no';

    case Sent = 'sent';

    case Skipped = 'skipped';

    case Deferred = 'deferred';

    case Failed = 'failed';

    case FallbackUsed = 'fallback_used';

    case Exited = 'exited';
}
