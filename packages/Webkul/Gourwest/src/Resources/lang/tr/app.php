<?php

return [
    'menu' => [
        'title' => 'Gourwest Küresel Merkez',
    ],

    'acl' => [
        'title' => 'Gourwest Küresel Merkez',
        'customers' => '7 Ülke Birleşik Profiller ve Tekilleştirme',
        'integrations' => '8 Sistem Entegrasyon Merkezi ve Yeniden Oynatma',
        'loyalty' => 'Sadakat Seviyeleri ve Sınır Ötesi Puanlar',
        'sla' => 'Bölgesel Müşteri Hizmetleri ve SLA Masası',
        'benchmark' => 'Hizmet Garantisi ve <3sn Benchmark',
    ],

    'index' => [
        'title' => 'Gourwest Küresel CRM ve Entegrasyon Merkezi',
        'subtitle' => '7 ülkede (AU, NZ, SG, MY, TH, UK, CA) birleşik Altın Kayıt profilleri, gerçek zamanlı 8 sistem telemetrisi, sadakat defteri, bölgesel SLA masası ve <3sn garanti kontrolleri.',
        'seed-btn' => 'Gourwest Küresel Veri Setini Yükle',
        'refresh-btn' => 'Telemetriyi Yenile',
        'retry-btn' => 'Yeniden Dene',
        'load-error' => 'Gourwest Küresel Merkez telemetrisi yüklenemedi. Lütfen tekrar deneyin.',
        'empty-title' => 'Henüz Gourwest Çok Bölgeli Profil Oluşturulmadı',
        'empty-description' => '7 ülke müşteri profillerini, 11 paydaşı, 8 sistem sözleşmesini, bölgeler arası mükerrer çiftleri ve SLA kayıtlarını tek tıkla yükleyin.',
    ],

    'kpis' => [
        'golden-records' => 'Altın Müşteri Kayıtları',
        'loyalty-liability' => 'Birleşik Sadakat Puanları',
        'integrations-health' => 'Sağlıklı Sistem Sözleşmeleri',
        'sla-latency' => 'Sorgu p95 ve Açık SLA Kayıtları',
    ],

    'tabs' => [
        'customers' => 'Birleşik 7 Bölge Müşterileri',
        'integrations' => '8 Sistem Entegrasyon Merkezi',
        'sla' => 'Sadakat ve Bölgesel SLA Masası',
        'benchmark' => 'Garanti ve <3sn Benchmark',
    ],

    'customers' => [
        'all-regions' => 'Tüm 7 Bölge',
        'search-placeholder' => 'Üyelik no, Altın UID, müşteri adı veya şube ara...',
        'duplicates-banner' => 'Bölgeler arası mükerrer müşteri kümeleri tespit edildi',
        'deduplicate-btn' => 'Bölgeler Arası Mükerrerleri Birleştir',
        'adjust-points-btn' => 'Puan Düzenle',
        'merged-badge' => 'Birleştirilmiş İkincil',
        'golden-badge' => 'Birincil Altın Kayıt',
    ],

    'integrations' => [
        'replay-btn' => 'Hatalı Olayları Yeniden Oynat',
        'sync-btn' => 'Şimdi Eşitle',
        'recent-events' => 'Son Sistemler Arası Eşitleme ve Webhook Akışı',
        'webhook-endpoint' => 'HMAC-SHA256 Webhook Uç Noktası',
    ],

    'sla' => [
        'new-ticket-btn' => 'Bölgesel SLA Kaydı Aç',
        'resolve-btn' => 'Kaydı Çözümle',
        'resolved-label' => 'Çözüldü',
        'loyalty-modal-title' => 'Sınır Ötesi Sadakat Puanlarını Düzenle',
        'ticket-modal-title' => 'Bölgesel Müşteri Hizmetleri SLA Kaydı Oluştur',
        'points-delta' => 'Puan Değişimi (+/-)',
        'reason' => 'Denetim Nedeni',
        'region' => 'Yetki Alanı / Bölge',
        'channel' => 'Hizmet Kanalı',
        'category' => 'Şikayet Kategorisi',
        'priority' => 'SLA Önceliği',
        'subject' => 'Konu',
        'summary' => 'İnceleme Notları',
        'save-btn' => 'Kaydet',
        'cancel-btn' => 'İptal',
    ],

    'benchmark' => [
        'run-btn' => 'Canlı 25 Sorgu (<3sn) Benchmark Çalıştır',
        'sla-pass' => '<= 3.000 ms GARANTİYİ GEÇTİ',
        'sla-fail' => 'SLA İHLALİ (> 3.000 ms)',
        'index-audit-title' => '5.000.000 Kayıt Bileşik İndeks ve Hazırlık Denetimi',
        'privacy-matrix-title' => '7 Ülke Gizlilik Mevzuatı ve Veri Egemenliği Matrisi',
    ],

    'messages' => [
        'seed-success' => 'Gourwest 7 ülke kurumsal veri seti başarıyla yüklendi.',
        'deduplicate-success' => ':count adet bölgeler arası mükerrer profil Altın Kayıtlarda birleştirildi.',
        'sync-success' => ':system bağlantı sinyali ve bölgesel telemetri eşitlendi.',
        'replay-success' => ':count adet hatalı entegrasyon olayı başarıyla yeniden işlendi.',
        'loyalty-adjusted' => 'Müşteri sadakat puanı güncellendi ve seviye yeniden hesaplandı.',
        'ticket-created' => 'Bölgesel SLA kaydı oluşturuldu.',
        'ticket-resolved' => 'Bölgesel SLA kaydı çözüldü ve denetim izine yazıldı.',
        'benchmark-complete' => '25 sorguluk (<3sn) erişim ve 5M kayıt garanti testi tamamlandı.',
    ],
];
