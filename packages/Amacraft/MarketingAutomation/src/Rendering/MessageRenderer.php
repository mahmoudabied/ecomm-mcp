<?php

namespace Amacraft\MarketingAutomation\Rendering;

use Amacraft\MarketingAutomation\DTOs\WhatsAppTemplateCall;
use Amacraft\MarketingAutomation\Repositories\WhatsAppTemplateRepository;

class MessageRenderer
{
    /**
     * Create a new message renderer instance.
     */
    public function __construct(
        protected TemplateRenderer $templateRenderer,
        protected EmailBlockRenderer $emailBlockRenderer,
        protected WhatsAppTemplateRepository $whatsAppTemplateRepository,
    ) {}

    /**
     * Render an email step into a subject and an HTML body.
     *
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $context
     * @return array{subject: string, html: string}
     */
    public function renderEmail(array $step, array $context, string $locale): array
    {
        if (! empty($context['transactional'])) {
            $step['blocks'] = $this->withoutRichBlocks($step['blocks'] ?? []);
        }

        $subject = $this->localized($step['subject'] ?? [], $locale);

        $blocks = $this->localizedBlocks($step['blocks'] ?? [], $locale);

        $bodyHtml = $this->emailBlockRenderer->render($blocks, $context, $context['direction'] ?? 'ltr');

        $unsubscribeUrl = $context['unsubscribe_url'] ?? null;

        if (
            $unsubscribeUrl
            && ! in_array('footer', array_column($blocks, 'type'), true)
        ) {
            $bodyHtml .= $this->emailBlockRenderer->render(
                [['type' => 'footer', 'text' => '']],
                $context,
                $context['direction'] ?? 'ltr',
            );
        }

        return [
            'subject' => $this->templateRenderer->render($subject, $context),
            'html'    => view('marketing_automation::emails.layout', [
                'direction' => $context['direction'] ?? 'ltr',
                'locale'    => $locale,
                'bodyHtml'  => $bodyHtml,
            ])->render(),
        ];
    }

    /**
     * Render an SMS step body.
     *
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $context
     */
    public function renderText(array $step, array $context, string $locale): string
    {
        return $this->templateRenderer->render(
            $this->localized($step['body'] ?? [], $locale),
            $context,
        );
    }

    /**
     * Build the WhatsApp template call for a step.
     *
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $context
     */
    public function renderWhatsApp(array $step, array $context, string $locale): WhatsAppTemplateCall
    {
        $template = $this->whatsAppTemplateRepository->find($step['template_id'] ?? null);

        $languageMap = $step['language'] ?? [];

        $language = $languageMap[$locale]
            ?? $template?->language
            ?? 'en_US';

        $bodyParams = [];

        $variables = $step['variables'] ?? [];

        ksort($variables);

        foreach ($variables as $position => $expression) {
            $bodyParams[(int) $position] = $this->templateRenderer->render((string) $expression, $context);
        }

        return new WhatsAppTemplateCall(
            name: $template?->name ?? '',
            language: $language,
            bodyParams: $bodyParams,
        );
    }

    /**
     * Remove the rich blocks a transactional email may never carry; the validator blocks them too.
     *
     * @param  array<string, mixed>  $map
     * @return array<string, mixed>
     */
    protected function withoutRichBlocks(array $map): array
    {
        foreach ($map as $locale => $blocks) {
            if (! is_array($blocks)) {
                continue;
            }

            $map[$locale] = array_values(array_filter(
                $blocks,
                fn ($block) => ! in_array($block['type'] ?? '', ['coupon', 'product_grid'], true),
            ));
        }

        return $map;
    }

    /**
     * Pick the value for the locale, falling back to the default then the first.
     *
     * @param  array<string, mixed>  $map
     */
    protected function localized(array $map, string $locale): mixed
    {
        $defaultLocale = core()->getDefaultLocaleCodeFromDefaultChannel();

        return $map[$locale]
            ?? $map[$defaultLocale]
            ?? (reset($map) === false ? '' : reset($map));
    }

    /**
     * Pick the block list for the locale, falling back to the default locale,
     * then the first locale; empty or scalar values become an empty list.
     *
     * @param  array<string, mixed>  $map
     * @return array<int, array<string, mixed>>
     */
    protected function localizedBlocks(array $map, string $locale): array
    {
        $defaultLocale = core()->getDefaultLocaleCodeFromDefaultChannel();

        $blocks = $map[$locale]
            ?? $map[$defaultLocale]
            ?? (reset($map) === false ? [] : reset($map));

        return array_values(array_filter(
            is_array($blocks) ? $blocks : [],
            fn ($block) => is_array($block),
        ));
    }
}
