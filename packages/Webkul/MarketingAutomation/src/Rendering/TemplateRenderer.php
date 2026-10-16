<?php

namespace Webkul\MarketingAutomation\Rendering;

class TemplateRenderer
{
    /**
     * Token pattern: `{{ path.to.value | default: "text" }}`.
     */
    protected const TOKEN_PATTERN = '/\{\{\s*([a-z_]+(?:\.[a-z_]+)*)\s*(?:\|\s*default:\s*"([^"]*)")?\s*\}\}/i';

    /**
     * Replace every token with its context value, honoring optional defaults.
     */
    public function render(string $template, array $context): string
    {
        return (string) preg_replace_callback(
            self::TOKEN_PATTERN,
            function (array $matches) use ($context): string {
                $value = data_get($context, $matches[1]);

                if (is_array($value) || is_object($value)) {
                    return '';
                }

                if ($value === null || $value === '') {
                    return $matches[2] ?? '';
                }

                return (string) $value;
            },
            $template
        );
    }

    /**
     * Get the whitelist of documented variable paths.
     */
    public function variables(): array
    {
        return [
            'customer.first_name',
            'customer.last_name',
            'customer.email',
            'store.name',
            'store.url',
            'cart.total',
            'cart.items_count',
            'cart.restore_url',
            'order.increment_id',
            'order.total',
            'order.tracking_url',
            'order.review_url',
            'coupon.code',
            'unsubscribe_url',
        ];
    }
}
