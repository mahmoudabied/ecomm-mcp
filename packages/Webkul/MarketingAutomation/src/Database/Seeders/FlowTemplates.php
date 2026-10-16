<?php

namespace Webkul\MarketingAutomation\Database\Seeders;

class FlowTemplates
{
    /**
     * Every prebuilt template, keyed by its template key.
     *
     * @return array<string, array{key: string, name: string, description: string, trigger_type: string, trigger_config: array, type: string, reentry_window_hours: int, exit_goals: array, draft_definition: array}>
     */
    public static function all(): array
    {
        return [
            'welcome' => [
                'key'                  => 'welcome',
                'name'                 => 'marketing_automation::app.flows.templates.welcome.name',
                'description'          => 'marketing_automation::app.flows.templates.welcome.description',
                'trigger_type'         => 'customer_registered',
                'trigger_config'       => [],
                'type'                 => 'marketing',
                'reentry_window_hours' => 8760,
                'exit_goals'           => [['type' => 'order_placed']],
                'draft_definition'     => [
                    'entry' => 's1',
                    'steps' => [
                        's1' => [
                            'type'    => 'send_email',
                            'subject' => ['en' => 'Welcome to {{ store.name }}!', 'ar' => 'مرحبًا بك في {{ store.name }}!'],
                            'blocks'  => [
                                'en' => [
                                    ['type' => 'text', 'text' => 'Hi {{ customer.first_name | default: "there" }}, thanks for joining us.'],
                                    ['type' => 'button', 'label' => 'Start shopping', 'href' => '{{ store.url }}'],
                                ],
                                'ar' => [
                                    ['type' => 'text', 'text' => 'مرحبًا {{ customer.first_name | default: "بك" }}، شكرًا لانضمامك إلينا.'],
                                    ['type' => 'button', 'label' => 'ابدأ التسوق', 'href' => '{{ store.url }}'],
                                ],
                            ],
                            'validity_hours' => 24,
                            'next'           => 's2',
                        ],
                        's2' => ['type' => 'wait', 'mode' => 'duration', 'minutes' => 2880, 'next' => 's3'],
                        's3' => [
                            'type'   => 'condition',
                            'rule'   => ['all' => [['field' => 'has_ordered_since_enrolment', 'op' => 'is_false']]],
                            'yes'    => 's4',
                            'no'     => 's6',
                        ],
                        's4' => [
                            'type'             => 'send_whatsapp',
                            'template_id'      => null,
                            'variables'        => [1 => '{{ customer.first_name | default: "Hi" }}'],
                            'language'         => ['en' => 'en_US', 'ar' => 'ar'],
                            'validity_hours'   => 24,
                            'fallback_channel' => 'sms',
                            'fallback_step'    => [
                                'body' => [
                                    'en' => 'Hi {{ customer.first_name | default: "there" }}, there is still time for your first order!',
                                    'ar' => 'مرحبًا {{ customer.first_name | default: "بك" }}، ما زال هناك وقت لطلبك الأول!',
                                ],
                                'validity_hours' => 24,
                            ],
                            'next' => 's6',
                        ],
                        's6' => ['type' => 'exit'],
                    ],
                ],
            ],

            'post_purchase' => [
                'key'                  => 'post_purchase',
                'name'                 => 'marketing_automation::app.flows.templates.post_purchase.name',
                'description'          => 'marketing_automation::app.flows.templates.post_purchase.description',
                'trigger_type'         => 'order_completed',
                'trigger_config'       => [],
                'type'                 => 'marketing',
                'reentry_window_hours' => 168,
                'exit_goals'           => [],
                'draft_definition'     => [
                    'entry' => 's1',
                    'steps' => [
                        's1' => ['type' => 'wait', 'mode' => 'duration', 'minutes' => 10080, 'next' => 's2'],
                        's2' => [
                            'type'    => 'send_email',
                            'subject' => ['en' => 'How is your order {{ order.increment_id }}?', 'ar' => 'كيف كان طلبك {{ order.increment_id }}؟'],
                            'blocks'  => [
                                'en' => [
                                    ['type' => 'text', 'text' => 'Hi {{ customer.first_name | default: "there" }}, a review helps other shoppers.'],
                                    ['type' => 'button', 'label' => 'Leave a review', 'href' => '{{ order.review_url }}'],
                                ],
                                'ar' => [
                                    ['type' => 'text', 'text' => 'مرحبًا {{ customer.first_name | default: "بك" }}، تقييمك يساعد المتسوقين الآخرين.'],
                                    ['type' => 'button', 'label' => 'اكتب تقييمًا', 'href' => '{{ order.review_url }}'],
                                ],
                            ],
                            'validity_hours' => 48,
                            'next'           => 's3',
                        ],
                        's3' => ['type' => 'exit'],
                    ],
                ],
            ],

            'win_back' => [
                'key'                  => 'win_back',
                'name'                 => 'marketing_automation::app.flows.templates.win_back.name',
                'description'          => 'marketing_automation::app.flows.templates.win_back.description',
                'trigger_type'         => 'customer_inactive',
                'trigger_config'       => ['inactive_days' => 90],
                'type'                 => 'marketing',
                'reentry_window_hours' => 8760,
                'exit_goals'           => [['type' => 'order_placed']],
                'draft_definition'     => [
                    'entry' => 's1',
                    'steps' => [
                        's1' => [
                            'type'    => 'send_email',
                            'subject' => ['en' => 'We miss you, {{ customer.first_name | default: "friend" }}', 'ar' => 'اشتقنا إليك'],
                            'blocks'  => [
                                'en' => [['type' => 'text', 'text' => 'It has been a while since your last visit.']],
                                'ar' => [['type' => 'text', 'text' => 'مر وقت طويل منذ آخر زيارة لك.']],
                            ],
                            'validity_hours' => 24,
                            'next'           => 's2',
                        ],
                        's2' => ['type' => 'wait', 'mode' => 'duration', 'minutes' => 4320, 'next' => 's3'],
                        's3' => [
                            'type' => 'condition',
                            'rule' => ['all' => [['field' => 'has_ordered_since_enrolment', 'op' => 'is_false']]],
                            'yes'  => 's4',
                            'no'   => 's5',
                        ],
                        's4' => [
                            'type'           => 'send_sms',
                            'body'           => ['en' => '{{ customer.first_name | default: "Hi" }}, come back — your favorites are waiting!', 'ar' => 'عُد إلينا — منتجاتك المفضلة بانتظارك!'],
                            'validity_hours' => 24,
                            'next'           => 's5',
                        ],
                        's5' => ['type' => 'exit'],
                    ],
                ],
            ],

            'order_status' => [
                'key'                  => 'order_status',
                'name'                 => 'marketing_automation::app.flows.templates.order_status.name',
                'description'          => 'marketing_automation::app.flows.templates.order_status.description',
                'trigger_type'         => 'order_status_changed',
                'trigger_config'       => [],
                'type'                 => 'transactional',
                'reentry_window_hours' => 0,
                'exit_goals'           => [],
                'draft_definition'     => [
                    'entry' => 's1',
                    'steps' => [
                        's1' => [
                            'type'             => 'send_sms',
                            'body'             => ['en' => 'Order {{ order.increment_id }} status update from {{ store.name }}.', 'ar' => 'تحديث حالة الطلب {{ order.increment_id }} من {{ store.name }}.'],
                            'validity_hours'   => 6,
                            'fallback_channel' => 'email',
                            'fallback_step'    => [
                                'subject' => ['en' => 'Update on order {{ order.increment_id }}', 'ar' => 'تحديث بشأن الطلب {{ order.increment_id }}'],
                                'blocks'  => [
                                    'en' => [['type' => 'text', 'text' => 'Your order status changed.']],
                                    'ar' => [['type' => 'text', 'text' => 'تم تغيير حالة طلبك.']],
                                ],
                                'validity_hours' => 6,
                            ],
                            'next' => 's2',
                        ],
                        's2' => ['type' => 'exit'],
                    ],
                ],
            ],

            'back_in_stock' => [
                'key'                  => 'back_in_stock',
                'name'                 => 'marketing_automation::app.flows.templates.back_in_stock.name',
                'description'          => 'marketing_automation::app.flows.templates.back_in_stock.description',
                'trigger_type'         => 'back_in_stock',
                'trigger_config'       => [],
                'type'                 => 'marketing',
                'reentry_window_hours' => 720,
                'exit_goals'           => [],
                'draft_definition'     => [
                    'entry' => 's1',
                    'steps' => [
                        's1' => [
                            'type'    => 'send_email',
                            'subject' => ['en' => 'Good news — it is back!', 'ar' => 'خبر سار — عاد للمخزون!'],
                            'blocks'  => [
                                'en' => [
                                    ['type' => 'text', 'text' => 'Hi {{ customer.first_name | default: "there" }}, the product you wanted is available again.'],
                                    ['type' => 'button', 'label' => 'Buy now', 'href' => '{{ store.url }}'],
                                ],
                                'ar' => [
                                    ['type' => 'text', 'text' => 'مرحبًا {{ customer.first_name | default: "بك" }}، المنتج الذي تريده متاح الآن.'],
                                    ['type' => 'button', 'label' => 'اشترِ الآن', 'href' => '{{ store.url }}'],
                                ],
                            ],
                            'validity_hours'   => 12,
                            'fallback_channel' => 'sms',
                            'fallback_step'    => [
                                'body'           => ['en' => 'Back in stock! Shop it now: {{ store.url }}', 'ar' => 'عاد للمخزون! تسوق الآن: {{ store.url }}'],
                                'validity_hours' => 12,
                            ],
                            'next' => 's2',
                        ],
                        's2' => ['type' => 'exit'],
                    ],
                ],
            ],

            'abandoned_cart' => [
                'key'                  => 'abandoned_cart',
                'name'                 => 'marketing_automation::app.flows.templates.abandoned_cart.name',
                'description'          => 'marketing_automation::app.flows.templates.abandoned_cart.description',
                'trigger_type'         => 'cart_abandoned',
                'trigger_config'       => ['abandon_delay_minutes' => 60],
                'type'                 => 'marketing',
                'reentry_window_hours' => 168,
                'exit_goals'           => [['type' => 'order_placed']],
                'draft_definition'     => [
                    'entry' => 's1',
                    'steps' => [
                        's1' => [
                            'type'    => 'send_email',
                            'subject' => ['en' => 'You left something behind', 'ar' => 'نسيت شيئاً في سلتك'],
                            'blocks'  => [
                                'en' => [
                                    ['type' => 'text', 'text' => 'Hi {{ customer.first_name | default: "there" }}, your cart is waiting for you.'],
                                    ['type' => 'cart_items'],
                                    ['type' => 'button', 'label' => 'Restore my cart', 'href' => '{{ cart.restore_url }}'],
                                ],
                                'ar' => [
                                    ['type' => 'text', 'text' => 'مرحبًا {{ customer.first_name | default: "بك" }}، سلتك ما زالت بانتظارك.'],
                                    ['type' => 'cart_items'],
                                    ['type' => 'button', 'label' => 'استعادة سلتي', 'href' => '{{ cart.restore_url }}'],
                                ],
                            ],
                            'validity_hours' => 24,
                            'next'           => 's2',
                        ],
                        's2' => ['type' => 'wait', 'mode' => 'duration', 'minutes' => 1380, 'next' => 's3'],
                        's3' => [
                            'type'             => 'send_whatsapp',
                            'template_id'      => null,
                            'variables'        => [1 => '{{ customer.first_name | default: "Hi" }}'],
                            'language'         => ['en' => 'en_US', 'ar' => 'ar'],
                            'validity_hours'   => 24,
                            'fallback_channel' => 'sms',
                            'fallback_step'    => [
                                'body' => [
                                    'en' => '{{ customer.first_name | default: "Hi" }}, your cart is waiting: {{ cart.restore_url }}',
                                    'ar' => '{{ customer.first_name | default: "مرحبًا" }}، سلتك بانتظارك: {{ cart.restore_url }}',
                                ],
                                'validity_hours' => 24,
                            ],
                            'next' => 's4',
                        ],
                        's4' => ['type' => 'wait', 'mode' => 'duration', 'minutes' => 2880, 'next' => 's5'],
                        's5' => [
                            'type'    => 'send_email',
                            'subject' => ['en' => 'Still thinking it over?', 'ar' => 'هل ما زلت تفكر؟'],
                            'blocks'  => [
                                'en' => [
                                    ['type' => 'text', 'text' => 'Your cart is still here — these items are waiting for you.'],
                                    ['type' => 'cart_items'],
                                    ['type' => 'button', 'label' => 'Restore my cart', 'href' => '{{ cart.restore_url }}'],
                                ],
                                'ar' => [
                                    ['type' => 'text', 'text' => 'سلتك ما زالت هنا — هذه المنتجات بانتظارك.'],
                                    ['type' => 'cart_items'],
                                    ['type' => 'button', 'label' => 'استعادة سلتي', 'href' => '{{ cart.restore_url }}'],
                                ],
                            ],
                            'validity_hours' => 24,
                            'next'           => 's6',
                        ],
                        's6' => ['type' => 'exit'],
                    ],
                ],
            ],
        ];
    }

    /**
     * One template by key.
     */
    public static function find(string $key): ?array
    {
        return static::all()[$key] ?? null;
    }
}
