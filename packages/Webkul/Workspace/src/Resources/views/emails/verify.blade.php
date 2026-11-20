@component('admin::emails.layout')
    <div style="margin-bottom: 34px;">
        <p style="font-weight: bold;font-size: 20px;color: #121A26;line-height: 24px;margin-bottom: 24px">
            @lang('workspace::app.mail.greeting', ['name' => $name])
        </p>

        <p style="font-size: 16px;color: #384860;line-height: 24px;">
            @lang('workspace::app.mail.intro', ['business' => $business])
        </p>

        <p style="text-align: center;padding: 20px 0;">
            <a
                href="{{ $link }}"
                style="padding: 10px 20px;background: #0F766E;color: #ffffff;text-decoration: none;font-size: 16px"
            >
                @lang('workspace::app.mail.action')
            </a>
        </p>

        <p style="font-size: 14px;color: #5E5E5E;line-height: 22px;">
            @lang('workspace::app.mail.expiry', ['hours' => $hours])
        </p>

        <p style="font-size: 14px;color: #5E5E5E;line-height: 22px;">
            @lang('workspace::app.mail.ignore')
        </p>
    </div>
@endcomponent
