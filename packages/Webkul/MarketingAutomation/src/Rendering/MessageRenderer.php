<?php

namespace Webkul\MarketingAutomation\Rendering;

use Webkul\MarketingAutomation\DTOs\WhatsAppTemplateCall;
use Webkul\MarketingAutomation\Models\WhatsAppTemplate;

class MessageRenderer
{
    /**
     * Create a new message renderer instance.
     */
    public function __construct(
        protected TemplateRenderer $templateRenderer,
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
        $subject = $this->localized($step['subject'] ?? [], $locale);

        $blocks = $this->localizedBlocks($step['blocks'] ?? [], $locale);

        $bodyHtml = collect($blocks)
            ->map(fn (array $block) => $this->renderBlock($block, $context))
            ->implode('');

        $unsubscribeUrl = $context['unsubscribe_url'] ?? null;

        if ($unsubscribeUrl && ! in_array('footer', array_column($blocks, 'type'), true)) {
            $bodyHtml .= view('marketing_automation::emails.blocks.footer', [
                'text'     => '',
                'link'     => $unsubscribeUrl,
                'context'  => $context,
            ])->render();
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
        /** @var ?WhatsAppTemplate $template */
        $template = WhatsAppTemplate::query()->find($step['template_id'] ?? null);

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
     * Render one email block through its Blade view.
     *
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $context
     */
    protected function renderBlock(array $block, array $context): string
    {
        $type = (string) ($block['type'] ?? '');

        $view = "marketing_automation::emails.blocks.{$type}";

        if (! view()->exists($view)) {
            return '';
        }

        $data = ['context' => $context];

        if ($type === 'footer') {
            $data['link'] = $context['unsubscribe_url'] ?? null;
        }

        foreach (['text', 'href', 'label', 'url', 'preheader'] as $key) {
            if (isset($block[$key])) {
                $data[$key] = $this->templateRenderer->render((string) $block[$key], $context);
            }
        }

        return view($view, $data)->render();
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
