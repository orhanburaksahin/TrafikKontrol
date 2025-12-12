<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
} 

// GLOBAL AYARLARI KONTROL ET
$settingsFile = dirname(__DIR__) . '/settings.json';
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
    
    // Session'da yoksa settings'den yükle
    if (!isset($_SESSION['stealth_mode']) && isset($settings['stealth_mode'])) {
        $_SESSION['stealth_mode'] = $settings['stealth_mode'];
    }
    
    if (!isset($_SESSION['adv_detection']) && isset($settings['adv_detection'])) {
        $_SESSION['adv_detection'] = $settings['adv_detection'];
    }
}

// BOT SAYFASINA DİREKT ERİŞİMİ ENGELLE
if (!isset($_SESSION['detected']) || $_SESSION['detected'] !== 'bot') {
    // Direkt erişim deniyor, ana sayfaya yönlendir
    header('Location: /');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Web Sitemiz</title>
    
    <?php if (!empty($_SESSION['stealth_mode']) || !empty($_SESSION['adv_detection'])): ?>
    <script src="/assets/js/unified-detector.js?v=<?=time()?>"></script>
    <?php endif; ?>
    
    <style>
        body { font-family: Arial; padding: 20px; max-width: 800px; margin: 0 auto; }
        h1 { color: #333; }
        .content { background: #f9f9f9; padding: 20px; border-radius: 10px; margin-top: 20px; }
        /* HONEYPOT STİLLERİ */
        .hp-field { 
            display: none !important; 
            visibility: hidden !important;
            height: 0 !important;
            width: 0 !important;
            opacity: 0 !important;
            position: absolute !important;
            left: -9999px !important;
        }
    </style>
</head>
<body>
    <!-- GİZLİ İŞARET -->
    <div style="display:none;">BOT_PAGE</div>
    
    <h1>Sitemize Hoş Geldiniz</h1>
    
    <div class="content">
        <p>Profesyonel web çözümleri sunuyoruz. En güncel teknolojilerle hazırlanmış çözümlerimizle hizmetinizdeyiz.</p>
        
        <h2>Hizmetlerimiz</h2>
        <ul>
            <li>Web Tasarım ve Geliştirme</li>
            <li>SEO Optimizasyonu</li>
            <li>E-Ticaret Sistemleri</li>
            <li>Marka Danışmanlığı</li>
        </ul>
        
        <p>Müşteri memnuniyeti odaklı çalışma prensibimiz ile projelerinizi en iyi şekilde hayata geçiriyoruz.</p>
    </div>
    
    <!-- BOT SAYFASINDA DA HONEYPOT FORM (gizli) -->
    <form method="post" style="display: none;">
        <input type="text" name="website_url" class="hp-field" tabindex="-1">
        <input type="email" name="contact_email" class="hp-field" tabindex="-1">
        <input type="url" name="homepage" class="hp-field" tabindex="-1">
        <input type="tel" name="phone" class="hp-field" tabindex="-1">
    </form>
    
    <script>
    // BOT SAYFASINDA HONEYPOT TRAP
    document.addEventListener('DOMContentLoaded', function() {
        // Honeypot alanlarına JavaScript ile değer ata (botlar genelde JS'yi çalıştırır)
        setTimeout(function() {
            var hpFields = document.querySelectorAll('.hp-field');
            hpFields.forEach(function(field) {
                field.value = 'bot_trap_' + Date.now();
                // Eğer bot bu değeri değiştirirse veya focus olursa...
                field.addEventListener('change', function() {
                    window.location.href = '/?unified_bot=1&reason=honeypot_js_trap';
                });
            });
        }, 2000);
    });
    </script>
    
    <footer style="margin-top: 40px; text-align: center; color: #666;">
        <p>&copy; 2024 Tüm hakları saklıdır.</p>
    </footer>
</body>
</html>