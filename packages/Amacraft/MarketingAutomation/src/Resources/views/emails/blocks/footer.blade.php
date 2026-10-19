<p style="margin: 24px 0 0; font-size: 12px; color: #888888; text-align: center;">
    @if (! empty($text))
        {!! nl2br(e($text)) !!}<br>
    @endif

    @if (! empty($link))
        <a href="{{ $link }}" style="color: #888888;">{{ trans('marketing_automation::app.emails.unsubscribe') }}</a>
    @endif
</p>
