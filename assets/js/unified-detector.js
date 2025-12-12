// Unified Bot Detector v3.0 - İnsan Dostu Versiyon
// Daha az false-positive, daha çok tolerans

(function() {
    'use strict';
    
    const DEBUG = true;
    
    // YENİ: İNSAN DOSTU AYARLAR
    const SETTINGS = {
        ADVANCED: {
            ENABLED: true,
            TIMEOUT_15S: 15000,    // 15 sn ilk kontrol (eski: 7s)
            TIMEOUT_30S: 30000,    // 30 sn final (eski: 13s)
            MIN_SCORE: 25,         // Düşürüldü! (eski: 55)
            MAX_STRAIGHT: 50,      // Artırıldı! (eski: 25)
            START_SCORE: 20        // Başlangıç puanı
        },
        STEALTH: {
            ENABLED: true,
            TIMEOUT_15S: 15000,    // 15 sn ilk kontrol
            TIMEOUT_40S: 40000,    // 40 sn final (eski: 15s)
            MIN_SCORE: 40,         // Düşürüldü (eski: 50)
            MAX_STRAIGHT: 30,      // Artırıldı (eski: 18)
            MIN_EVENTS: 1,         // Azaltıldı (eski: 2)
            MIN_MOVES: 10          // Azaltıldı (eski: 15)
        },
        // YENİ: İNSAN DAVRANIŞI TANIMA
        HUMAN_BEHAVIOR: {
            MIN_READING_TIME: 10000,  // 10 sn okuma süresi (normal)
            MAX_INACTIVITY: 60000     // 60 sn tam hareketsizlik
        }
    };
    
    // BAŞLANGIÇ PUANI VER
    let advancedScore = SETTINGS.ADVANCED.START_SCORE;
    let stealthScore = 100;
    let isBotDetected = false;
    let timers = [];
    let humanBehavior = {
        lastActivity: Date.now(),
        totalActivity: 0,
        isReading: false
    };
    
    function log(message) {
        if (DEBUG) console.log('🤖 [BOT DETECTOR] ' + message);
    }
    
    // YENİ: İNSAN AKTİVİTE TAKİBİ
    function updateActivity() {
        const now = Date.now();
        humanBehavior.totalActivity += (now - humanBehavior.lastActivity);
        humanBehavior.lastActivity = now;
        humanBehavior.isReading = (humanBehavior.totalActivity > SETTINGS.HUMAN_BEHAVIOR.MIN_READING_TIME);
        
        if (humanBehavior.isReading) {
            log('📖 İnsan okuma modunda (aktivite: ' + humanBehavior.totalActivity + 'ms)');
        }
    }
    
    // YENİ: HAREKETSİZLİK KONTROLÜ
    function checkInactivity() {
        const inactiveTime = Date.now() - humanBehavior.lastActivity;
        
        if (inactiveTime > SETTINGS.HUMAN_BEHAVIOR.MAX_INACTIVITY) {
            log('💤 60 saniyedir hareket yok, uyku modunda');
            // Bu bile bot değil, sadece log
            return true;
        }
        return false;
    }
    
    function clearAllTimers() {
        timers.forEach(timer => clearTimeout(timer));
        timers = [];
    }
    
    function markAsBot(reason) {
        if (isBotDetected) return;
        
        // YENİ: OKUMA MODUNDAYSA BOT İŞARETLEME
        if (humanBehavior.isReading) {
            log('⚠️ Okuma modundaki kullanıcı bot olarak işaretlenmedi');
            return;
        }
        
        isBotDetected = true;
        clearAllTimers();
        
        log('🚨 BOT TESPİT EDİLDİ: ' + reason);
        log('📊 Advanced: ' + advancedScore + ', Stealth: ' + stealthScore);
        
        // 1. COOKIE
        document.cookie = "unified_bot_detected=1; path=/; max-age=86400";
        
        // 2. PHP LOG
        fetch('/?unified_bot=1&reason=' + encodeURIComponent(reason), {
            method: 'GET'
        });
        
        // 3. YÖNLENDİRME (1.5 sn sonra)
        setTimeout(() => {
            if (!window.location.pathname.includes('/bot/')) {
                window.location.replace('/bot/');
            }
        }, 1500);
    }
    
    // YENİ: YUMUŞAK BOT İŞARETLEME (sadece cookie)
    function markAsSuspicious(reason) {
        log('⚠️ Şüpheli davranış: ' + reason);
        document.cookie = "suspicious_activity=1; path=/; max-age=3600";
    }
    
    function initAdvancedDetection() {
        if (!SETTINGS.ADVANCED.ENABLED) return;
        
        log('✅ Gelişmiş tespit (İnsan dostu)');
        log('🎯 Başlangıç puanı: ' + advancedScore);
        
        let scrolled = false;
        let moves = 0;
        let lastX = 0, lastY = 0;
        let straightMoves = 0;
        
        // AKTİVİTE TAKİBİ İÇİN EVENT'LER
        const activityEvents = ['scroll', 'mousemove', 'click', 'keydown', 'touchstart'];
        activityEvents.forEach(event => {
            window.addEventListener(event, updateActivity, { passive: true });
        });
        
        // SCROLL
        window.addEventListener('scroll', () => { 
            if (!scrolled) { 
                scrolled = true; 
                advancedScore += 30; // Artırıldı
                log('🔄 Scroll (+30) - Total: ' + advancedScore);
            }
        }, { once: true });
        
        // FARE HAREKETİ
        window.addEventListener('mousemove', (e) => {
            moves++;
            
            // 2 hareket yeterli (eski: 3)
            if (moves === 2) {
                advancedScore += 20; // Azaltıldı
                log('🖱️ 2+ fare hareketi (+20) - Total: ' + advancedScore);
            }
            
            // DÜZ HAREKET (daha toleranslı)
            if (lastX !== 0 && lastY !== 0) {
                const dx = Math.abs(e.clientX - lastX);
                const dy = Math.abs(e.clientY - lastY);
                
                if (dx < 5 && dy < 5 && dx > 0) { // Eşik artırıldı (4→5)
                    straightMoves++;
                    
                    if (straightMoves > SETTINGS.ADVANCED.MAX_STRAIGHT) {
                        // -60 yerine -30 (daha hafif ceza)
                        advancedScore -= 30;
                        markAsSuspicious('Çok fazla düz fare hareketi');
                        log('📉 Düz hareket (-30) - Total: ' + advancedScore);
                    }
                }
            }
            
            lastX = e.clientX;
            lastY = e.clientY;
        });
        
        // KLAVYE
        window.addEventListener('keydown', () => {
            advancedScore += 15; // Azaltıldı (20→15)
            log('⌨️ Klavye (+15) - Total: ' + advancedScore);
        }, { once: true });
        
        // TIKLAMA (YENİ EKLENDİ)
        window.addEventListener('click', () => {
            advancedScore += 25;
            log('👆 Tıklama (+25) - Total: ' + advancedScore);
        }, { once: true });
        
        // 15 SANİYE - SADECE UYARI
        timers.push(setTimeout(() => {
            if (advancedScore < 40) { // Eşik düşük
                log('⏰ 15s: Puan düşük (' + advancedScore + '), ama henüz bot değil');
                markAsSuspicious('15sn içinde yetersiz aktivite');
            }
        }, SETTINGS.ADVANCED.TIMEOUT_15S));
        
        // 30 SANİYE - FİNAL (YUMUŞAK)
        timers.push(setTimeout(() => {
            log('⏳ 30s final - Score: ' + advancedScore);
            
            if (advancedScore < SETTINGS.ADVANCED.MIN_SCORE) {
                // Önce inactivity kontrolü
                if (checkInactivity()) {
                    log('💤 Uyku modu, bot işaretlenmiyor');
                    return;
                }
                
                // Çok düşük puan + hiç aktivite yoksa
                if (advancedScore <= 10 && moves === 0 && !scrolled) {
                    markAsBot('30s: Hiç aktivite yok - Puan: ' + advancedScore);
                } else {
                    // Sadece şüpheli işaretle
                    markAsSuspicious('30s: Düşük puan (' + advancedScore + ')');
                    log('⚠️ Düşük puan ama bot değil, şüpheli işaretlendi');
                }
            } else {
                log('✅ 30s: İnsan olarak işaretlendi');
            }
        }, SETTINGS.ADVANCED.TIMEOUT_30S));
    }
    
    function initStealthDetection() {
        if (!SETTINGS.STEALTH.ENABLED) return;
        
        log('✅ Stealth tespit (Gelişmiş)');
        
        let events = new Set();
        let mousePath = [];
        let lastX = 0, lastY = 0;
        let straightCount = 0;
        let scrollTimes = [];
        
        // FARE
        window.addEventListener('mousemove', (e) => {
            if (lastX !== 0 && lastY !== 0) {
                const dx = Math.abs(e.clientX - lastX);
                const dy = Math.abs(e.clientY - lastY);
                
                if (dx < 8 && dy < 8 && dx > 0) { // Eşik artırıldı (7→8)
                    straightCount++;
                    
                    if (straightCount > SETTINGS.STEALTH.MAX_STRAIGHT) {
                        stealthScore -= 40; // Hafifletildi (80→40)
                        log('📉 Stealth: Düz hareket (-40) - Score: ' + stealthScore);
                    }
                }
            }
            
            lastX = e.clientX;
            lastY = e.clientY;
            events.add('mousemove');
            mousePath.push([e.clientX, e.clientY, Date.now()]);
        });
        
        // SCROLL
        window.addEventListener('scroll', () => {
            const now = Date.now();
            scrollTimes.push(now);
            
            if (scrollTimes.length > 5) {
                const avg = (scrollTimes[scrollTimes.length - 1] - scrollTimes[0]) / 5;
                
                if (avg < 200) { // Eşik düşürüldü (300→200)
                    stealthScore -= 40; // Hafifletildi (70→40)
                    log('📉 Stealth: Hızlı scroll (-40) - Score: ' + stealthScore);
                }
            }
            
            events.add('scroll');
        });
        
        // KLAVYE
        window.addEventListener('keydown', () => {
            events.add('keyboard');
        }, { once: true });
        
        // TIKLAMA
        window.addEventListener('click', () => {
            events.add('click');
            stealthScore += 10; // Tıklama ödülü!
            log('👆 Stealth: Tıklama (+10) - Score: ' + stealthScore);
        }, { once: true });
        
        // 15 SANİYE
        timers.push(setTimeout(() => {
            if (events.size < SETTINGS.STEALTH.MIN_EVENTS || mousePath.length < SETTINGS.STEALTH.MIN_MOVES) {
                stealthScore -= 40; // Hafifletildi (90→40)
                log('📉 Stealth 15s: Az aktivite (-40) - Score: ' + stealthScore);
            }
        }, SETTINGS.STEALTH.TIMEOUT_15S));
        
        // 40 SANİYE - FİNAL (ÇOK TOLERANSLI)
        timers.push(setTimeout(() => {
            log('⏳ 40s final - Stealth Score: ' + stealthScore);
            
            // ÇOK DÜŞÜK DEĞİLSE BOT İŞARETLEME
            if (stealthScore < 20) { // Çok düşük eşik
                if (events.size === 0 && mousePath.length === 0) {
                    markAsBot('Stealth: 40s hiç aktivite yok');
                } else {
                    markAsSuspicious('Stealth: Çok düşük puan (' + stealthScore + ')');
                }
            } else if (stealthScore < SETTINGS.STEALTH.MIN_SCORE) {
                // Sadece şüpheli işaretle
                markAsSuspicious('Stealth: Düşük puan (' + stealthScore + ')');
            } else {
                log('✅ Stealth: İnsan olarak işaretlendi');
            }
        }, SETTINGS.STEALTH.TIMEOUT_40S));
    }
    
    function disableOldSystems() {
        document.cookie = "adv_bot=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
        document.cookie = "final_bot=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
        document.cookie = "ultra_bot=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
    }
    
    function init() {
        log('🚀 İnsan Dostu Bot Detector v3.0');
        log('🎯 Hedef: Daha az false-positive, daha çok insan');
        
        disableOldSystems();
        
        // AKTİVİTE TAKİBİ BAŞLAT
        humanBehavior.lastActivity = Date.now();
        
        // HAREKETSİZLİK KONTROLÜ (60 sn'de bir)
        setInterval(() => {
            checkInactivity();
        }, 10000);
        
        initAdvancedDetection();
        initStealthDetection();
        
        log('===========================================');
    }
    
    // BAŞLAT
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();