<table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
    <tr>
        <td>
            @if (! empty($href) && $href !== '#')
                <a href="{{ $href }}">
                    <img src="{{ $src ?? '' }}" alt="{{ $alt ?? '' }}" width="536" style="display: block; width: 100%; max-width: 536px; border-radius: 6px;">
                </a>
            @else
                <img src="{{ $src ?? '' }}" alt="{{ $alt ?? '' }}" width="536" style="display: block; width: 100%; max-width: 536px; border-radius: 6px;">
            @endif
        </td>
    </tr>
</table>
