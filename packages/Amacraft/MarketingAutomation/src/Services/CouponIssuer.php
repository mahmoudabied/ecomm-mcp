<?php

namespace Amacraft\MarketingAutomation\Services;

use Carbon\Carbon;
use InvalidArgumentException;
use Webkul\CartRule\Models\CartRule;
use Webkul\CartRule\Models\CartRuleCoupon;
use Webkul\CartRule\Repositories\CartRuleCouponRepository;
use Amacraft\MarketingAutomation\Models\MessageLog;

class CouponIssuer
{
    /**
     * Create a new coupon issuer instance.
     */
    public function __construct(
        protected CartRuleCouponRepository $couponRepository,
    ) {}

    /**
     * The unique coupon code of a message log, issuing a fresh one-time code
     * on first call and returning the stored code on every retry (R14).
     */
    public function issue(MessageLog $log, int $cartRuleId, ?int $expiresDays): string
    {
        if ((string) $log->coupon_code !== '') {
            return (string) $log->coupon_code;
        }

        $rule = CartRule::query()->find($cartRuleId);

        if (
            $rule === null
            || ! $rule->coupon_type
            || ! $rule->use_auto_generation
        ) {
            throw new InvalidArgumentException("Cart rule [{$cartRuleId}] does not allow auto-generated coupons.");
        }

        $code = $this->uniqueCode();

        CartRuleCoupon::query()->create([
            'cart_rule_id'       => $rule->id,
            'code'               => $code,
            'usage_limit'        => 1,
            'usage_per_customer' => 1,
            'is_primary'         => 0,
            'type'               => 1,
            'expired_at'         => $this->expiresAt($rule, $expiresDays),
        ]);

        $log->update(['coupon_code' => $code]);

        return $code;
    }

    /**
     * A prefixed code built from Bagisto's own coupon charset, unique so far.
     */
    protected function uniqueCode(): string
    {
        do {
            $code = 'MA-'.$this->couponRepository->getRandomString('alphanumeric', 10);

            $exists = CartRuleCoupon::query()->where('code', $code)->exists();
        } while ($exists);

        return $code;
    }

    /**
     * The earlier of the requested lifetime and the rule's own end date.
     */
    protected function expiresAt(CartRule $rule, ?int $expiresDays): ?string
    {
        $expiry = $expiresDays !== null
            ? now()->addDays($expiresDays)
            : null;

        if (! empty($rule->ends_till)) {
            $ruleEnd = Carbon::parse($rule->ends_till);

            $expiry = $expiry === null || $expiry->greaterThan($ruleEnd)
                ? $ruleEnd
                : $expiry;
        }

        return $expiry?->toDateString();
    }
}
