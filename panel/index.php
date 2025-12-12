<?php
session_start();
$hashed = '$2y$10$.oHWo2VPgC47WLY.jksNueaGLbMrcaZseez0Z9VnFO6HOHOidQk1i'; // 3a80f3a286

// AYARLAR DOSYASI YOLU
$settingsFile = dirname(__DIR__) . '/settings.json';

// 1. AYARLARI YÜKLE FONKSİYONU
function loadSettings($file) {
    if (!file_exists($file)) {
        $default = [
            'stealth_mode' => false,
            'adv_detection' => false,
            'protection_level' => 'balanced',
            'last_updated' => date('Y-m-d H:i:s'),
            'updated_by' => 'system',
            'site_name' => 'gonderisorgula.xyz',
            'version' => '3.0'
        ];
        file_put_contents($file, json_encode($default, JSON_PRETTY_PRINT));
        return $default;
    }
    
    $content = file_get_contents($file);
    $settings = json_decode($content, true);
    
    // Yeni alanları kontrol et, yoksa ekle
    if (!isset($settings['protection_level'])) {
        $settings['protection_level'] = 'balanced';
    }
    if (!isset($settings['site_name'])) {
        $settings['site_name'] = 'gonderisorgula.xyz';
    }
    if (!isset($settings['version'])) {
        $settings['version'] = '3.0';
    }
    
    return $settings;
}

// 2. AYAR DEĞİŞTİR FONKSİYONU
function saveSetting($file, $key, $value) {
    $settings = loadSettings($file);
    $oldValue = $settings[$key] ?? null;
    $settings[$key] = $value;
    $settings['last_updated'] = date('Y-m-d H:i:s');
    $settings['updated_by'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $result = file_put_contents($file, json_encode($settings, JSON_PRETTY_PRINT));
    
    // Session'ı da güncelle
    $_SESSION[$key] = $value;
    
    // Log değişikliği
    if ($oldValue !== $value) {
        error_log("Panel: $key değiştirildi: " . ($oldValue ? 'AÇIK' : 'KAPALI') . " -> " . ($value ? 'AÇIK' : 'KAPALI'));
    }
    
    return $result !== false;
}

// 3. AYARLARI YÜKLE
$settings = loadSettings($settingsFile);

// 4. AÇ/KAPA İSTEKLERİNİ İŞLE (TEK NOKTA)
if (isset($_GET['stealth'])) {
    saveSetting($settingsFile, 'stealth_mode', $_GET['stealth'] === 'on');
    header('Location: index.php');
    exit;
}

if (isset($_GET['adv'])) {
    saveSetting($settingsFile, 'adv_detection', $_GET['adv'] === 'on');
    header('Location: index.php');
    exit;
}

// 5. SESSION'A YÜKLE
$_SESSION['stealth_mode'] = $settings['stealth_mode'];
$_SESSION['adv_detection'] = $settings['adv_detection'];

// 6. BRUTE-FORCE KORUMASI
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt'] = time();
}

if ($_SESSION['login_attempts'] >= 5) {
    $wait_time = 300;
    if (time() - $_SESSION['last_attempt'] < $wait_time) {
        die('<div style="text-align:center;padding:50px;font-family:Arial">
                <h3 style="color:#dc3545">🚫 Çok Fazla Hatalı Giriş</h3>
                <p>5 dakika boyunca bloklandınız. Lütfen daha sonra tekrar deneyin.</p>
                <p><small>IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'Bilinmiyor') . '</small></p>
             </div>');
    } else {
        $_SESSION['login_attempts'] = 0;
    }
}

// 7. ŞİFRE KONTROLÜ
if (!isset($_SESSION['auth'])) {
    if (isset($_POST['pass']) && password_verify($_POST['pass'], $hashed)) {
        $_SESSION['auth'] = true;
        $_SESSION['login_attempts'] = 0;
        header('Location: index.php');
        exit;
    } else {
        if (isset($_POST['pass'])) {
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt'] = time();
        }
        
        $attemptsLeft = 5 - ($_SESSION['login_attempts'] ?? 0);
        $attemptsMsg = $attemptsLeft > 0 ? "Kalan deneme: $attemptsLeft" : "Bloklandınız";
        
        die('<div style="height:100vh;display:flex;align-items:center;justify-content:center;background:#f4f6f9;font-family:system-ui">
                <form method="post" style="width:400px;padding:50px;background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,0.1);text-align:center">
                    <h3 style="margin-bottom:20px;color:#0d6efd">🔐 Yönetici Paneli</h3>
                    <p style="color:#666;margin-bottom:25px;font-size:14px">GönderiSorgula.xyz - Bot Tespit Sistemi</p>
                    <input type="password" name="pass" placeholder="Şifre" required 
                           style="width:100%;padding:14px;border:1px solid #ddd;border-radius:8px;margin-bottom:15px">
                    <button type="submit" style="width:100%;padding:14px;background:#0d6efd;border:none;border-radius:8px;color:white;font-weight:600">
                        Giriş Yap
                    </button>
                    ' . (isset($_POST['pass']) ? '<p style="color:#dc3545;margin-top:15px;font-size:13px">❌ Hatalı şifre! ' . $attemptsMsg . '</p>' : '') . '
                </form>
             </div>');
    }
}

// 8. LOG DOSYASI İŞLEMLERİ
$logFile = dirname(dirname(__FILE__)) . '/log/visits.log';

// Log dosyası yoksa oluştur
if (!file_exists($logFile)) {
    file_put_contents($logFile, '');
    chmod($logFile, 0666);
}

if (isset($_POST['clear'])) {
    file_put_contents($logFile, '');
    $_SESSION['clear_message'] = '✅ Tüm loglar temizlendi';
    header('Location: index.php');
    exit;
}

if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=gonderisorgula_logs_' . date('Y-m-d_H-i') . '.csv');
    echo "\xEF\xBB\xBFTarih/Saat;IP;Ülke;Cihaz;Durum;Neden;User-Agent\n";
    if (file_exists($logFile)) {
        echo file_get_contents($logFile);
    }
    exit;
}

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $lines = file_exists($logFile) ? array_reverse(file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : [];
    echo json_encode($lines, JSON_UNESCAPED_UNICODE);
    exit;
}

// 9. CLEAR MESAJINI GÖSTER VE TEMİZLE
$clearMessage = '';
if (isset($_SESSION['clear_message'])) {
    $clearMessage = $_SESSION['clear_message'];
    unset($_SESSION['clear_message']);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GönderiSorgula.xyz - Yönetim Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f6f9; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        .card-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .badge-human { background: #d1edff; color: #084298; }
        .badge-bot { background: #f8d7da; color: #721c24; }
        .flag { font-size: 1.6rem; margin-right: 8px; }
        .stat-card { transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .mode-indicator { font-size: 0.85rem; padding: 3px 8px; border-radius: 12px; }
    </style>
</head>
<body>
    <!-- ÜST BİLGİ -->
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-shield-alt me-2"></i> GönderiSorgula.xyz
            </a>
            <div class="text-light">
                <small>v<?= $settings['version'] ?> | 
                <?= $settings['protection_level'] === 'maximum' ? '🛡️ Maksimum' : ($settings['protection_level'] === 'balanced' ? '⚖️ Dengeli' : '🔓 Hafif') ?> Koruma</small>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <?php if ($clearMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $clearMessage ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- SİSTEM DURUMU -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="card border-<?= $settings['adv_detection'] ? 'success' : 'secondary' ?> shadow">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title mb-1">
                                    <i class="fas fa-robot text-<?= $settings['adv_detection'] ? 'success' : 'secondary' ?>"></i>
                                    Gelişmiş Bot Tespiti
                                </h5>
                                <p class="card-text text-muted mb-0">
                                    <small>Davranış analizi ile bot tespiti (30sn)</small>
                                </p>
                            </div>
                            <div class="text-end">
                                <?php if($settings['adv_detection']): ?>
                                    <span class="badge bg-success fs-6">AKTİF</span>
                                    <a href="?adv=off" class="btn btn-outline-danger btn-sm mt-2">Kapat</a>
                                <?php else: ?>
                                    <span class="badge bg-secondary fs-6">PASİF</span>
                                    <a href="?adv=on" class="btn btn-outline-success btn-sm mt-2">Aç</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-3">
                <div class="card border-<?= $settings['stealth_mode'] ? 'danger' : 'secondary' ?> shadow">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title mb-1">
                                    <i class="fas fa-user-secret text-<?= $settings['stealth_mode'] ? 'danger' : 'secondary' ?>"></i>
                                    Stealth Bot Tespiti
                                </h5>
                                <p class="card-text text-muted mb-0">
                                    <small>Advanced botlar için (40sn + honeypot)</small>
                                </p>
                            </div>
                            <div class="text-end">
                                <?php if($settings['stealth_mode']): ?>
                                    <span class="badge bg-danger fs-6">AKTİF</span>
                                    <a href="?stealth=off" class="btn btn-outline-dark btn-sm mt-2">Kapat</a>
                                <?php else: ?>
                                    <span class="badge bg-secondary fs-6">PASİF</span>
                                    <a href="?stealth=on" class="btn btn-outline-danger btn-sm mt-2">Aç</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="fas fa-clock"></i> Son güncelleme: <?= $settings['last_updated'] ?>
                                <span class="ms-2"><i class="fas fa-user"></i> <?= $settings['updated_by'] ?></span>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MOD BİLGİSİ -->
        <div class="alert alert-info mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong><i class="fas fa-info-circle me-2"></i> Aktif Koruma Modu:</strong>
                    <?php if ($settings['adv_detection'] && $settings['stealth_mode']): ?>
                        <span class="badge bg-dark ms-2">Maksimum Koruma</span>
                        <small class="text-muted ms-2">(İki sistem de aktif)</small>
                    <?php elseif ($settings['adv_detection']): ?>
                        <span class="badge bg-success ms-2">Gelişmiş Mod</span>
                        <small class="text-muted ms-2">(Sadece davranış analizi)</small>
                    <?php elseif ($settings['stealth_mode']): ?>
                        <span class="badge bg-danger ms-2">Stealth Mod</span>
                        <small class="text-muted ms-2">(Sadece advanced botlar)</small>
                    <?php else: ?>
                        <span class="badge bg-secondary ms-2">Temel Koruma</span>
                        <small class="text-muted ms-2">(Sadece UA + Honeypot)</small>
                    <?php endif; ?>
                </div>
                <div>
                    <a href="/" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-external-link-alt"></i> Siteyi Görüntüle
                    </a>
                </div>
            </div>
        </div>

        <!-- TRAFİK PANELİ -->
        <div class="card shadow-lg border-0">
            <div class="card-header py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i> Canlı Trafik İzleme</h4>
                        <small class="text-light">Gerçek zamanlı bot/insan ayrımı</small>
                    </div>
                    <div>
                        <button onclick="window.location='?csv=1'" class="btn btn-success btn-sm me-2">
                            <i class="fas fa-download"></i> CSV İndir
                        </button>
                        <form method="post" class="d-inline" onsubmit="return confirm('Tüm loglar silinecek, emin misin?');">
                            <button type="submit" name="clear" class="btn btn-danger btn-sm">
                                <i class="fas fa-trash"></i> Temizle
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <!-- İSTATİSTİKLER -->
                <div class="row g-3 mb-4" id="stats">
                    <div class="col-12 text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Yükleniyor...</span>
                        </div>
                    </div>
                </div>

                <!-- LOG TABLOSU -->
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th width="150">Tarih/Saat</th>
                                <th width="120">IP Adresi</th>
                                <th width="100">Ülke</th>
                                <th width="90">Cihaz</th>
                                <th width="90">Durum</th>
                                <th>Neden</th>
                            </tr>
                        </thead>
                        <tbody id="logs">
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loglar yükleniyor...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card-footer text-muted text-center py-3">
                <small>
                    <i class="fas fa-sync-alt me-1"></i> 3 saniyede bir güncellenir | 
                    <i class="fas fa-database ms-2 me-1"></i> Log dosyası: <?= basename($logFile) ?>
                </small>
            </div>
        </div>

        <!-- SİSTEM BİLGİSİ -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h6><i class="fas fa-cogs me-2"></i> Sistem Bilgisi</h6>
                        <table class="table table-sm">
                            <tr><td>Sunucu:</td><td><?= php_uname('s') . ' ' . php_uname('r') ?></td></tr>
                            <tr><td>PHP:</td><td><?= phpversion() ?></td></tr>
                            <tr><td>Log Dosyası:</td><td><?= file_exists($logFile) ? number_format(filesize($logFile) / 1024, 2) . ' KB' : 'Yok' ?></td></tr>
                            <tr><td>Son Güncelleme:</td><td><?= $settings['last_updated'] ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h6><i class="fas fa-shield-alt me-2"></i> Aktif Korumalar</h6>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between">
                                <span>User-Agent Tespiti</span>
                                <span class="badge bg-success">AKTİF</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Honeypot Sistemi (4 alan)</span>
                                <span class="badge bg-success">AKTİF</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Cloudflare Turnstile</span>
                                <span class="badge bg-success">AKTİF</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Unified JS Detector</span>
                                <span class="badge bg-<?= ($settings['adv_detection'] || $settings['stealth_mode']) ? 'success' : 'secondary' ?>">
                                    <?= ($settings['adv_detection'] || $settings['stealth_mode']) ? 'AKTİF' : 'PASİF' ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    const flags = {
        "Türkiye":"https://flagcdn.com/32x24/tr.png", "Turkey":"https://flagcdn.com/32x24/tr.png",
        "Almanya":"https://flagcdn.com/32x24/de.png", "Germany":"https://flagcdn.com/32x24/de.png",
        "Rusya":"https://flagcdn.com/32x24/ru.png", "Russia":"https://flagcdn.com/32x24/ru.png",
        "ABD":"https://flagcdn.com/32x24/us.png", "United States":"https://flagcdn.com/32x24/us.png",
        "Fransa":"https://flagcdn.com/32x24/fr.png", "Hollanda":"https://flagcdn.com/32x24/nl.png",
        "Ukrayna":"https://flagcdn.com/32x24/ua.png", "Polonya":"https://flagcdn.com/32x24/pl.png",
        "İngiltere":"https://flagcdn.com/32x24/gb.png", "United Kingdom":"https://flagcdn.com/32x24/gb.png",
        "Kanada":"https://flagcdn.com/32x24/ca.png", "Yerel":"https://flagcdn.com/32x24/xx.png",
        "Unknown":"https://flagcdn.com/32x24/un.png"
    };

    function load() {
        fetch('?ajax=1')
        .then(r => r.json())
        .then(lines => {
            let insan = 0, bot = 0;
            
            if (lines.length === 0) {
                document.getElementById('logs').innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="fas fa-inbox fa-2x mb-3"></i><br>
                            Henüz kayıt bulunamadı
                        </td>
                    </tr>`;
                
                document.getElementById('stats').innerHTML = `
                    <div class="col-6 col-md-3"><div class="p-3 bg-white rounded shadow-sm text-center stat-card"><h4>0</h4><small>Toplam</small></div></div>
                    <div class="col-6 col-md-3"><div class="p-3 bg-white rounded shadow-sm text-center text-success stat-card"><h4>0</h4><small>İnsan</small></div></div>
                    <div class="col-6 col-md-3"><div class="p-3 bg-white rounded shadow-sm text-center text-danger stat-card"><h4>0</h4><small>Bot</small></div></div>
                    <div class="col-6 col-md-3"><div class="p-3 bg-white rounded shadow-sm text-center stat-card"><h4>%0</h4><small>İnsan Oranı</small></div></div>`;
                return;
            }
            
            document.getElementById('logs').innerHTML = lines.map(l => {
                let d = l.split(' | ');
                if(d.length < 7) return '';
                
                if(d[4].trim() === 'human') insan++; else bot++;
                
                const bayrakUrl = flags[d[2].trim()] || 'https://flagcdn.com/32x24/un.png';
                const isHuman = d[4].trim() === 'human';
                
                return `<tr>
                    <td><small>${d[0]}</small></td>
                    <td><code>${d[1]}</code></td>
                    <td><img src="${bayrakUrl}" width="24" height="18" class="me-2" style="vertical-align:middle"> ${d[2]}</td>
                    <td><span class="badge ${d[3] === 'Mobile' ? 'bg-info' : 'bg-secondary'}">${d[3]}</span></td>
                    <td><span class="badge ${isHuman ? 'badge-human' : 'badge-bot'}">${isHuman ? '👤 İNSAN' : '🤖 BOT'}</span></td>
                    <td><small>${d[5]}</small></td>
                </tr>`;
            }).join('');
            
            const total = lines.length;
            const humanPercent = total ? Math.round(insan / total * 100) : 0;
            
            document.getElementById('stats').innerHTML = `
                <div class="col-6 col-md-3">
                    <div class="p-3 bg-white rounded shadow-sm text-center stat-card">
                        <h4 class="text-primary">${total}</h4>
                        <small class="text-muted">Toplam Ziyaret</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 bg-white rounded shadow-sm text-center stat-card border-success">
                        <h4 class="text-success">${insan}</h4>
                        <small class="text-muted">İnsan Ziyareti</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 bg-white rounded shadow-sm text-center stat-card border-danger">
                        <h4 class="text-danger">${bot}</h4>
                        <small class="text-muted">Bot Ziyareti</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 bg-white rounded shadow-sm text-center stat-card">
                        <h4 class="${humanPercent > 70 ? 'text-success' : humanPercent > 30 ? 'text-warning' : 'text-danger'}">%${humanPercent}</h4>
                        <small class="text-muted">İnsan Oranı</small>
                    </div>
                </div>`;
        })
        .catch(error => {
            console.error('Log yükleme hatası:', error);
            document.getElementById('logs').innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4 text-danger">
                        <i class="fas fa-exclamation-triangle fa-2x mb-3"></i><br>
                        Log yüklenirken hata oluştu
                    </td>
                </tr>`;
        });
    }
    
    // İlk yükleme
    load();
    
    // 3 saniyede bir güncelle
    setInterval(load, 3000);
    
    // Sayfa kapanırken timer'ı temizle
    window.addEventListener('beforeunload', function() {
        clearInterval(loadInterval);
    });
    
    // Auto-refresh info
    let lastUpdate = new Date();
    setInterval(() => {
        const now = new Date();
        const diff = Math.floor((now - lastUpdate) / 1000);
        document.querySelector('.card-footer small').innerHTML = 
            `<i class="fas fa-sync-alt me-1"></i> ${diff}s önce güncellendi | ` +
            `<i class="fas fa-database ms-2 me-1"></i> Log dosyası: <?= basename($logFile) ?>`;
    }, 1000);
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>