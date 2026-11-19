<?php

return [
    'menu' => [
        'title' => 'A/B Deneyleri',
        'title-info' => 'Kontrollü A/B bölünmüş deneyler yürütün ve CRM hunilerinde dönüşüm artışını izleyin.',
    ],

    'acl' => [
        'title' => 'A/B Deneyleri',
        'create' => 'Oluştur',
        'edit' => 'Düzenle',
        'delete' => 'Sil',
    ],

    'statuses' => [
        'draft' => 'Taslak',
        'running' => 'Çalışıyor',
        'paused' => 'Duraklatıldı',
        'concluded' => 'Tamamlandı',
    ],

    'index' => [
        'title' => 'A/B Deneyleri',
        'subtitle' => 'Gizlilik korumalı dönüşüm ilişkilendirmesi ile deterministik trafik dağıtımı.',
        'create-btn' => 'Deney Oluştur',
        'edit-btn' => 'Düzenle',
        'delete-btn' => 'Sil',
        'retry-btn' => 'Tekrar Dene',
        'traffic' => 'Trafik',
        'sample-ready' => 'Örneklem yeterli (n ≥ 100)',
        'sample-low' => 'Örneklem toplanıyor (n < 100)',
        'srm-warning' => 'SRM Uyarısı (trafik sapması)',
        'empty-title' => 'Henüz yapılandırılmış A/B deneyi yok',
        'empty-description' => 'Web formları ve müşteri adayı hunilerindeki dönüşüm artışını ölçmek için ilk deneyinizi oluşturun.',
        'load-error' => 'Deneyler yüklenemedi. Lütfen tekrar deneyin.',
        'delete-confirm' => 'Bu deney ve kaydedilen tüm telemetri verileri silinsin mi?',
        'create-success' => 'Deney başarıyla oluşturuldu.',
        'update-success' => 'Deney başarıyla güncellendi.',
        'delete-success' => 'Deney başarıyla silindi.',
    ],

    'table' => [
        'variant' => 'Varyant',
        'split' => 'Dağılım',
        'exposures' => 'Gösterimler',
        'conversions' => 'Dönüşümler',
        'conv-rate' => 'Dönüşüm Oranı',
        'lift' => 'Kontrole Göre Artış',
        'p-value' => 'Düz. p-Değeri',
        'ci-95' => '%95 Güven Aralığı (yp)',
        'prob-beat' => 'P(Kontrolü Geçme)',
        'value' => 'Aday Değeri',
        'baseline' => 'Temel',
    ],

    'form' => [
        'create-title' => 'A/B Deneyi Oluştur',
        'edit-title' => 'A/B Deneyini Düzenle',
        'name' => 'Deney Adı',
        'code' => 'Deney Anahtarı',
        'status' => 'Durum',
        'traffic-bp' => 'Trafik Tahsisi (Baz Puan)',
        'winner' => 'Kazanan Varyant',
        'winner-none' => 'Temel Kontrole Dön',
        'description' => 'Hipotez / Notlar',
        'variants' => 'Varyantlar',
        'add-variant' => 'Varyant Ekle',
        'cancel' => 'İptal',
        'save' => 'Deneyi Kaydet',
    ],

    'validations' => [
        'weights-sum' => 'Varyant ağırlıklarının toplamı tam olarak 10000 baz puan (%100) olmalıdır.',
        'invalid-winner' => 'Seçilen kazanan varyant yapılandırılmış varyant anahtarlarından biriyle eşleşmelidir.',
        'locked-after-start' => 'Veri toplama başladıktan sonra deney anahtarı ve varyant ağırlıkları değiştirilemez.',
    ],
];
