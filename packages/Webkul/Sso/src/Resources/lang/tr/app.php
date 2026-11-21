<?php

return [
    'link' => 'Çoklu oturum açma ile giriş yap',
    'start' => [
        'title' => 'Çoklu oturum açma',
        'intro' => 'İş e-postanızı girin. Sizi kuruluşunuzun giriş sayfasına yönlendireceğiz.',
        'email' => 'İş e-postası',
        'submit' => 'Devam',
        'password' => 'Bunun yerine parolayla giriş yap',
    ],
    'redirect' => [
        'title' => 'Giriş sağlayıcınıza yönlendiriliyorsunuz…',
        'continue' => 'Devam',
    ],
    'errors' => [
        'not-configured' => 'Bu e-posta adresi için çoklu oturum açma ayarlanmamış. Parolanızla giriş yapın veya yöneticinize başvurun.',
        'expired' => 'Bu giriş süresi doldu veya zaten kullanıldı. Lütfen yeniden başlayın.',
        'cancelled' => 'Giriş, sağlayıcınızda iptal edildi.',
        'provider' => 'Giriş sağlayıcınıza ulaşılamadı. Tekrar deneyin veya yöneticinizden ayarları kontrol etmesini isteyin.',
        'token' => 'Giriş sağlayıcınızın yanıtı doğrulanamadı. Lütfen tekrar deneyin.',
        'unknown-user' => 'Bu hesap burada giriş yapamaz. Yöneticinizden sizi eklemesini isteyin.',
        'unavailable' => 'Bu çalışma alanı kullanılamıyor.',
        'use-sso' => 'Kuruluşunuz çoklu oturum açma kullanıyor. Aşağıdan devam edin.',
    ],
    'configuration' => [
        'title' => 'Çoklu oturum açma',
        'info' => 'Ekibinizin kuruluş hesaplarıyla giriş yapmasını sağlayın.',
        'oidc' => [
            'title' => 'OpenID Connect',
            'info' => 'Google Workspace, Microsoft Entra ID, Okta ve diğer OpenID Connect sağlayıcılarıyla çalışır. Bu yönlendirme adresini sağlayıcınıza kaydedin: <CRM adresiniz>/admin/sso/callback',
            'enabled' => 'Çoklu oturum açmayı etkinleştir',
            'issuer' => 'Sağlayıcı adresi (issuer)',
            'issuer-info' => 'Google Workspace için: https://accounts.google.com',
            'client-id' => 'İstemci kimliği',
            'client-secret' => 'İstemci gizli anahtarı',
            'domains' => 'E-posta alan adları',
            'domains-info' => 'Virgülle ayırın, örneğin example.com. Yalnızca bu alan adlarındaki adresler çoklu oturum açmayı kullanabilir.',
            'required' => 'Bu alan adları için çoklu oturum açmayı zorunlu kıl',
            'required-info' => 'Bu adresler için parolayla giriş reddedilir. Sağlayıcı kullanılamazsa diye bu alan adlarının dışında bir yönetici hesabı tutun.',
            'trust-mfa' => 'Sağlayıcı iki adımlı doğrulamayı zorunlu kılıyor',
            'trust-mfa-info' => 'Çoklu oturum açmadan sonra CRM\'in kendi iki adımlı doğrulamasını atlar. Sağlayıcınız herkes için ikinci adımı zorunlu kılmıyorsa kapalı bırakın.',
        ],
    ],
];
