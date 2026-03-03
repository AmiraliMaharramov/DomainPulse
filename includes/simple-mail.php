<?php
// PHPMailer olmadan basit mail gönderimi

// Mail ayarlarını al
function getMailSettings($db) {
    $stmt = $db->query("SELECT * FROM mail_settings WHERE enabled = 1 LIMIT 1");
    return $stmt->fetch();
}

// Basit mail gönder
function sendSimpleMail($to, $subject, $message, $headers = '') {
    return mail($to, $subject, $message, $headers);
}

// HTML mail gönder
function sendHtmlMail($subject, $body, $db) {
    $settings = getMailSettings($db);
    
    if (!$settings) {
        return ['success' => false, 'error' => 'Mail ayarları bulunamadı'];
    }
    
    $to = $settings['to_email'];
    $from = $settings['from_email'];
    $fromName = $settings['from_name'];
    
    // Mail başlıkları
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: $fromName <$from>" . "\r\n";
    $headers .= "Reply-To: $from" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Mail gönder
    $success = mail($to, $subject, $body, $headers);
    
    return ['success' => $success, 'error' => $success ? null : 'Mail gönderilemedi'];
}

// Domain için bildirim maili gönder
function sendDomainExpiryNotification($domain, $daysRemaining, $db) {
    // Bu gün için daha önce mail gönderilmiş mi kontrol et
    $stmt = $db->prepare("
        SELECT id FROM mail_logs 
        WHERE domain_id = ? 
        AND days_remaining = ? 
        AND DATE(sent_at) = CURDATE()
    ");
    $stmt->execute([$domain['id'], $daysRemaining]);
    
    if ($stmt->fetch()) {
        return false; // Bugün zaten gönderilmiş
    }
    
    // Mail içeriği hazırla
    $subject = "⚠️ Domain Süresi Uyarısı: {$domain['domain_name']} - {$daysRemaining} Gün Kaldı!";
    
    $statusColor = getStatusColor($daysRemaining);
    $statusText = getStatusText($daysRemaining);
    
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
            .alert { padding: 15px; border-radius: 5px; margin: 20px 0; }
            .alert-danger { background: #fee; color: #c33; border: 1px solid #fcc; }
            .alert-warning { background: #ffeaa7; color: #856404; border: 1px solid #ffeaa7; }
            .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .info-table td { padding: 10px; border-bottom: 1px solid #eee; }
            .info-table td:first-child { font-weight: bold; width: 150px; }
            .button { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2 style='margin: 0; color: #333;'>🌐 Domain Süresi Bildirimi</h2>
            </div>
            
            <div class='alert " . ($daysRemaining <= 7 ? 'alert-danger' : 'alert-warning') . "'>
                <strong>Dikkat!</strong> Aşağıdaki domainin süresi dolmak üzere:
            </div>
            
            <table class='info-table'>
                <tr>
                    <td>Domain Adı:</td>
                    <td><strong>{$domain['domain_name']}</strong></td>
                </tr>
                <tr>
                    <td>Kalan Süre:</td>
                    <td><strong style='color: " . ($daysRemaining <= 7 ? '#c33' : '#856404') . ";'>{$statusText}</strong></td>
                </tr>
                <tr>
                    <td>Bitiş Tarihi:</td>
                    <td>" . date('d.m.Y', strtotime($domain['expiry_date'])) . "</td>
                </tr>
                <tr>
                    <td>Kayıt Firması:</td>
                    <td>" . ($domain['registrar'] ?: 'Bilinmiyor') . "</td>
                </tr>
            </table>
            
            <p>Domain sürenizin dolmasına <strong>{$daysRemaining} gün</strong> kaldı. Lütfen yenileme işlemini zamanında yapınız.</p>
            
            <center>
                <a href='http://localhost/domain-takip/admin/' class='button'>Panele Git</a>
            </center>
            
            <div class='footer'>
                <p>Bu mail Domain Takip Sistemi tarafından otomatik olarak gönderilmiştir.</p>
                <p>Bildirim ayarlarını yönetim panelinden değiştirebilirsiniz.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Mail gönder
    $result = sendHtmlMail($subject, $body, $db);
    
    // Log kaydet
    $stmt = $db->prepare("
        INSERT INTO mail_logs (domain_id, days_remaining, status, error_message) 
        VALUES (?, ?, ?, ?)
    ");
    
    $status = $result['success'] ? 'sent' : 'failed';
    $error = $result['error'] ?? null;
    
    $stmt->execute([$domain['id'], $daysRemaining, $status, $error]);
    
    return $result['success'];
}

// Tüm domainler için bildirimleri kontrol et
function checkAndSendNotifications($db) {
    // Mail ayarlarını al
    $settings = getMailSettings($db);
    if (!$settings) {
        return ['success' => false, 'error' => 'Mail ayarları yapılandırılmamış'];
    }
    
    // Bildirim günlerini al
    $notificationDays = explode(',', $settings['notification_days']);
    $notificationDays = array_map('intval', $notificationDays);
    
    // Süresi yaklaşan domainleri al
    $stmt = $db->query("
        SELECT * FROM domains 
        WHERE expiry_date IS NOT NULL 
        AND expiry_date > CURDATE()
        AND DATEDIFF(expiry_date, CURDATE()) <= 30
    ");
    
    $domains = $stmt->fetchAll();
    $sentCount = 0;
    
    foreach ($domains as $domain) {
        $daysRemaining = calculateDaysRemaining($domain['expiry_date']);
        
        // Bu gün bildirim gönderilmeli mi?
        if (in_array($daysRemaining, $notificationDays)) {
            if (sendDomainExpiryNotification($domain, $daysRemaining, $db)) {
                $sentCount++;
            }
        }
    }
    
    return ['success' => true, 'sent' => $sentCount];
}

// Yeni domain eklendiğinde kontrol et
function checkNewDomainExpiry($domainId, $db) {
    $stmt = $db->prepare("SELECT * FROM domains WHERE id = ?");
    $stmt->execute([$domainId]);
    $domain = $stmt->fetch();
    
    if (!$domain || !$domain['expiry_date']) {
        return false;
    }
    
    $daysRemaining = calculateDaysRemaining($domain['expiry_date']);
    
    // 7 gün veya daha az kaldıysa hemen bildirim gönder
    if ($daysRemaining <= 7 && $daysRemaining >= 0) {
        return sendDomainExpiryNotification($domain, $daysRemaining, $db);
    }
    
    return false;
}
?>