<?php
// assets/detector.php – Geliştirilmiş versiyon
function getRealIp() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return $_SERVER['HTTP_CF_CONNECTING_IP'];
    if (!empty($_SERVER['CF_CONNECTING_IP'])) return $_SERVER['CF_CONNECTING_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $list = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        foreach (array_reverse($list) as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function detectVisitor() {
    if (isset($_SESSION['detected']) && $_SESSION['detected'] === 'bot') return 'bot';

    $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

    // 1. BİLİNEN BOTLAR (Geliştirilmiş liste)
    $hardBots = [
        'googlebot','bingbot','yandex','duckduckbot','baiduspider',
        'ahrefs','semrush','mj12bot','dotbot','headless',
        'phantomjs','selenium','puppeteer','playwright',
        'scrapy','curl','wget','python-requests','java',
        'httpclient','go-http-client','node-fetch','axios',
        'postmanruntime','apache-httpclient','okhttp',
        'facebookexternalhit','twitterbot','whatsapp',
        'telegrambot','discordbot','slackbot','linkedinbot'
    ];
    
    foreach ($hardBots as $bot) {
        if (strpos($ua, $bot) !== false) {
            $_SESSION['detected'] = 'bot';
            $_SESSION['bot_reason'] = 'User-Agent: Bilinen bot (' . $bot . ')';
            return 'bot';
        }
    }

    // 2. HONEYPOT TUZAĞI - ÇOKLU ALAN
    $honeypotFields = ['website_url', 'contact_email', 'homepage', 'url', 'link', 'website', 'email_confirm', 'phone', 'fax'];
    
    foreach ($honeypotFields as $field) {
        if (!empty($_POST[$field])) {
            $_SESSION['detected'] = 'bot';
            $_SESSION['bot_reason'] = 'Honeypot tuzağına düştü (' . $field . ' = ' . substr($_POST[$field], 0, 50) . ')';
            return 'bot';
        }
    }

    // 3. HIZLI İSTEK KONTROLÜ (DDoS/brute-force koruması)
    if (isset($_SESSION['last_request'])) {
        $timeDiff = microtime(true) - $_SESSION['last_request'];
        if ($timeDiff < 0.3) { // 0.3 saniyeden hızlı istekler
            $_SESSION['detected'] = 'bot';
            $_SESSION['bot_reason'] = 'Çok hızlı istek (' . round($timeDiff, 3) . 's)';
            return 'bot';
        }
    }
    $_SESSION['last_request'] = microtime(true);

    // 4. EMPTY USER-AGENT KONTROLÜ
    if (empty($ua) || strlen($ua) < 10) {
        $_SESSION['detected'] = 'bot';
        $_SESSION['bot_reason'] = 'Geçersiz/boş User-Agent';
        return 'bot';
    }

    // 5. TOR/VPN IP KONTROLÜ (opsiyonel - açabilirsin)
    // $ip = getRealIp();
    // if (isTorExitNode($ip)) { ... }

    // 6. BAŞKA ŞÜPHE YOKSA → İNSAN
    $_SESSION['detected'] = 'human';
    return 'human';
}

function logVisit($type) {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = getRealIp();
    $country = getCountry($ip);
    $device = getDevice($ua);

    // REASON belirleme
    if (isset($_SESSION['bot_reason'])) {
        $reason = $_SESSION['bot_reason'];
    } elseif ($type === 'human') {
        $reason = "Tüm kontrolleri geçti → Gerçek insan";
    } else {
        $reason = strpos($ua, 'bot') !== false ? 'Bilinen bot olarak tespit edildi' : 'Honeypot tuzağına düştü';
    }

    $line = date('Y-m-d H:i:s') . " | $ip | $country | $device | $type | $reason | $ua" . PHP_EOL;
    file_put_contents(__DIR__ . '/../log/visits.log', $line, FILE_APPEND | LOCK_EX);
}

function getCountry($ip) {
    if ($ip === '127.0.0.1' || $ip === '::1') return 'Local';
    $data = @json_decode(file_get_contents("http://ip-api.com/json/{$ip}?fields=country"), true);
    return $data['country'] ?? 'Unknown';
}

function getDevice($ua) {
    return preg_match('/mobile|android|iphone|ipad|tablet/i', $ua) ? 'Mobile' : 'Desktop';
}
?>