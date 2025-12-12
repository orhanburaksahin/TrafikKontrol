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

// Eğer Turnstile ile doğrulanmışsa, normal içeriği göster
$showTurnstile = !isset($_SESSION['turnstile_verified']) || $_SESSION['turnstile_verified'] !== true;
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
        h1 { color: #2ecc71; }
        .content { background: #f0f8ff; padding: 20px; border-radius: 10px; margin-top: 20px; }
        .cta-button {
            background: #2ecc71;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 20px;
        }
        #turnstile-container {
            margin: 30px 0;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 10px;
            text-align: center;
        }
        /* HONEYPOT STİLLERİ - ASLA DEĞİŞTİRME! */
        .hp-field { 
            display: none !important; 
            visibility: hidden !important;
            height: 0 !important;
            width: 0 !important;
            opacity: 0 !important;
            position: absolute !important;
            left: -9999px !important;
            top: -9999px !important;
            z-index: -9999 !important;
            pointer-events: none !important;
        }
    </style>
</head>
<body>
    <!-- GİZLİ İŞARET -->
    <div style="display:none;">HUMAN_PAGE</div>
    
    <h1>Sitemize Hoş Geldiniz! 👋</h1>
    
    <?php if ($showTurnstile): ?>
    <!-- TURNSTILE DOĞRULAMA ALANI -->
    <div id="turnstile-container">
        <p style="margin-bottom: 15px; color: #555;">
            <strong>Güvenlik Doğrulaması:</strong> Lütfen robot olmadığınızı doğrulayın.
        </p>
        
        <!-- Cloudflare Turnstile -->
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        
        <!-- FORM + HONEYPOT -->
        <form method="post" id="main-form">
            <!-- TURNSTILE WIDGET -->
            <div class="cf-turnstile" 
                 data-sitekey="0x4AAAAAACGEjxLN_2ofO4sZ" 
                 data-callback="onTurnstileSuccess">
            </div>
            
            <!-- HONEYPOT FIELDS - BOTLAR BUNLARI DOLDURUR -->
            <input type="text" name="website_url" class="hp-field" tabindex="-1" autocomplete="off">
            <input type="email" name="contact_email" class="hp-field" tabindex="-1" autocomplete="off">
            <input type="url" name="homepage" class="hp-field" tabindex="-1" autocomplete="off">
            <input type="tel" name="phone" class="hp-field" tabindex="-1" autocomplete="off">
            
            <!-- TOKEN FIELD -->
            <input type="hidden" name="cf-turnstile-response" id="cf-token">
        </form>
        
        <script>
        function onTurnstileSuccess(token) {
            console.log('Turnstile verified, submitting form...');
            document.getElementById('cf-token').value = token;
            
            // 1 saniye bekle ve otomatik submit
            setTimeout(function() {
                document.getElementById('main-form').submit();
            }, 1000);
        }
        
        // EK GÜVENLİK: Honeypot alanlarına focus olursa formu boz
        document.querySelectorAll('.hp-field').forEach(function(field) {
            field.addEventListener('focus', function() {
                this.form.action = '/bot/'; // Bot sayfasına yönlendir
                this.form.submit();
            });
        });
        </script>
    </div>
    
    <script>
    console.log('Turnstile required - not verified yet');
    </script>
    
    <?php else: ?>
    <!-- DOĞRULANMIŞ KULLANICI İÇİN NORMAL İÇERİK -->
    <div class="content">
        <p>Değerli ziyaretçimiz, sitemizi tercih ettiğiniz için teşekkür ederiz. Size özel hazırladığımız içeriklerle karşınızdayız.</p>
        
        <h2>Hizmetlerimiz</h2>
        <ul>
            <li>Web Tasarım ve Geliştirme</li>
            <li>SEO Optimizasyonu</li>
            <li>E-Ticaret Sistemleri</li>
            <li>Marka Danışmanlığı</li>
        </ul>
        
        <p>Uzman ekibimiz ile her zaman yanınızdayız. Müşteri memnuniyeti bizim için en önemli öncelektir.</p>
        
        <button class="cta-button" onclick="alert('Teşekkür ederiz! En kısa sürede sizinle iletişime geçeceğiz.')">
            📞 Ücretsiz Danışmanlık Alın
        </button>
    </div>
    
    <!-- DOĞRULANMIŞ KULLANICI İÇİN DE HONEYPOT (gizli) -->
    <form method="post" style="display: none;">
        <input type="text" name="website_url" class="hp-field">
        <input type="email" name="contact_email" class="hp-field">
    </form>
    
    <script>
    console.log('Already verified via Turnstile, showing content');
    </script>
    <?php endif; ?>
    
    <footer style="margin-top: 40px; text-align: center; color: #666;">
        <p>&copy; 2024 Tüm hakları saklıdır.</p>
    </footer>
</body>
</html>