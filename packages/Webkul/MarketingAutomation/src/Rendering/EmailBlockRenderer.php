<?php

namespace Webkul\MarketingAutomation\Rendering;

class EmailBlockRenderer
{
    /**
     * Blocks whose token values must be rendered before the view echoes them.
     *
     * @var array<int, string>
     */
    protected const TOKEN_KEYS = ['title', 'content', 'text', 'src', 'alt', 'href', 'label', 'logo_url'];

    /**
     * Create a new email block renderer instance.
     */
    public function __construct(
        protected TemplateRenderer $templateRenderer,
        protected Recommendations $recommendations,
    ) {}

    /**
     * Render the blocks of one locale into HTML. Every value passed to a view
     * is token-rendered and auto-escaped by `{{ }}`, except the text block's
     * `$html`, which this class builds from escaped input.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<string, mixed>  $context
     */
    public function render(array $blocks, array $context, string $direction): string
    {
        return collect($blocks)
            ->map(fn (array $block) => $this->renderBlock($block, $context, $direction))
            ->implode('');
    }

    /**
     * Render one block through its Blade view; blocks without a view are
     * skipped so a bad draft can never crash a send or a preview.
     *
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $context
     */
    protected function renderBlock(array $block, array $context, string $direction): string
    {
        $view = $this->viewFor($block);

        if (! view()->exists($view)) {
            return '';
        }

        $data = $this->dataFor($block, $context, $direction);

        return $data === null ? '' : view($view, $data)->render();
    }

    /**
     * Assemble the pre-rendered view data of a block; null skips the block.
     *
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    protected function dataFor(array $block, array $context, string $direction): ?array
    {
        $data = [
            'context'   => $context,
            'direction' => $direction,
            'align'     => $direction === 'rtl' ? 'right' : 'left',
        ];

        foreach (self::TOKEN_KEYS as $key) {
            if (isset($block[$key])) {
                $data[$key] = $this->templateRenderer->render((string) $block[$key], $context);
            }
        }

        if ($block['type'] === 'text') {
            $data['html'] = nl2br(e($data['content'] ?? $data['text'] ?? ''));
        }

        if ($block['type'] === 'button') {
            $data['href'] = $this->safeUrl($data['href'] ?? '');

            $data['color'] = $this->safeColor($block['color'] ?? '');
        }

        if ($block['type'] === 'header') {
            $data['logo_url'] = $this->safeUrl($data['logo_url'] ?? '');
        }

        if ($block['type'] === 'image') {
            if ($this->safeUrl($data['src'] ?? '') === '#') {
                return null;
            }

            $data['href'] = $this->safeUrl($data['href'] ?? '#');
        }

        if ($block['type'] === 'footer') {
            $data['link'] = $context['unsubscribe_url'] ?? null;
        }

        if ($block['type'] === 'coupon') {
            $data['code'] = (string) ($context['coupon']['code'] ?? '');
        }

        if ($block['type'] === 'product_grid') {
            $data['items'] = $this->recommendations->forContext(
                $context,
                (string) ($block['source'] ?? 'bestsellers'),
                max(1, min(6, (int) ($block['count'] ?? 3))),
                (int) ($context['channel_id'] ?? 0),
            );
        }

        return $data;
    }

    /**
     * The Blade view of a block type.
     *
     * @param  array<string, mixed>  $block
     */
    protected function viewFor(array $block): string
    {
        return 'marketing_automation::emails.blocks.'.($block['type'] ?? '');
    }

    /**
     * Keep only http(s) URLs and already rendered links; anything else is inert.
     */
    protected function safeUrl(?string $url): string
    {
        $url = (string) $url;

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://') ? $url : '#';
    }

    /**
     * Keep only hex colors, falling back to the default button colour.
     */
    protected function safeColor(mixed $color): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) ? (string) $color : '#0f172a';
    }
}
