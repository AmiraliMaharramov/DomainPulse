<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// PHPMailer autoload - composer kullanmıyorsak manuel yükle
require_once dirname(__DIR__) . '/vendor/PHPMailer/src/Exception.php';
require_once dirname(__DIR__) . '/vendor/PHPMailer/src/PHPMailer.php';
require_once dirname(__DIR__) . '/vendor/PHPMailer/src/SMTP.php';

// Mail ayarlarını al
function getMailSettings($db) {
    $stmt = $db->query("SELECT * FROM mail_settings WHERE enabled = 1 LIMIT 1");
    return $stmt->fetch();
}

// Mail gönder
function sendMail($subject, $body, $db) {
    $settings = getMailSettings($db);
    
    if (!$settings) {
        return ['success' => false, 'error' => 'Mail ayarları bulunamadı'];
    }
    
    $mail = new PHPMailer(true);
    
    try {
        // Sunucu ayarları
        $mail->isSMTP();
        $mail->Host       = $settings['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $settings['smtp_username'];
        $mail->Password   = $settings['smtp_password'];
        $mail->SMTPSecure = $settings['smtp_secure'];
        $mail->Port       = $settings['smtp_port'];
        $mail->CharSet    = 'UTF-8';
        
        // Alıcılar
        $mail->setFrom($settings['from_email'], $settings['from_name']);
        $mail->addAddress($settings['to_email']);
        
        // İçerik
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);
        
        $mail->send();
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
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
    $result = sendMail($subject, $body, $db);
    
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

// Hosting için bildirim maili gönder
function sendHostingExpiryNotification($hosting, $daysRemaining, $db, $isTest = false) {
    // Test maili değilse, bu gün için daha önce mail gönderilmiş mi kontrol et
    if (!$isTest) {
        $stmt = $db->prepare("
            SELECT id FROM hosting_mail_logs 
            WHERE hosting_id = ? 
            AND days_remaining = ? 
            AND DATE(sent_at) = CURDATE()
        ");
        $stmt->execute([$hosting['id'], $daysRemaining]);
        
        if ($stmt->fetch()) {
            return false; // Bugün zaten gönderilmiş
        }
    }
    
    // Mail içeriği hazırla
    $subject = "⚠️ Hosting Süresi Uyarısı: {$hosting['hosting_name']} - {$daysRemaining} Gün Kaldı!";
    
    $statusColor = getHostingStatusColor($daysRemaining);
    $statusText = getHostingStatusText($daysRemaining);
    
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
                <h2 style='margin: 0; color: #333;'>☁️ Hosting Süresi Bildirimi</h2>
            </div>
            
            <div class='alert " . ($daysRemaining <= 7 ? 'alert-danger' : 'alert-warning') . "'>
                <strong>Dikkat!</strong> Aşağıdaki hostingin süresi dolmak üzere:
            </div>
            
            <table class='info-table'>
                <tr>
                    <td>Müşteri:</td>
                    <td><strong>{$hosting['customer_name']}</strong></td>
                </tr>
                <tr>
                    <td>Hosting Adı:</td>
                    <td><strong>{$hosting['hosting_name']}</strong></td>
                </tr>
                <tr>
                    <td>Kalan Süre:</td>
                    <td><strong style='color: " . ($daysRemaining <= 7 ? '#c33' : '#856404') . ";'>{$statusText}</strong></td>
                </tr>
                <tr>
                    <td>Bitiş Tarihi:</td>
                    <td>" . date('d.m.Y', strtotime($hosting['expiry_date'])) . "</td>
                </tr>
                <tr>
                    <td>Başlangıç Tarihi:</td>
                    <td>" . date('d.m.Y', strtotime($hosting['start_date'])) . "</td>
                </tr>
                <tr>
                    <td>Süre:</td>
                    <td>{$hosting['duration_months']} ay</td>
                </tr>";
    
    if ($hosting['provider']) {
        $body .= "
                <tr>
                    <td>Sağlayıcı:</td>
                    <td>{$hosting['provider']}</td>
                </tr>";
    }
    
    if ($hosting['price']) {
        $body .= "
                <tr>
                    <td>Fiyat:</td>
                    <td>" . number_format($hosting['price'], 2) . " ₺</td>
                </tr>";
    }
    
    if ($hosting['notes']) {
        $body .= "
                <tr>
                    <td>Notlar:</td>
                    <td>{$hosting['notes']}</td>
                </tr>";
    }
    
    $body .= "
            </table>
            
            <p>Hosting sürenizin dolmasına <strong>{$daysRemaining} gün</strong> kaldı. Lütfen yenileme işlemini zamanında yapınız.</p>
            
            <center>
                <a href='http://localhost/domaintakip/admin/hosting-takip.php' class='button'>Hosting Paneline Git</a>
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
    $result = sendMail($subject, $body, $db);
    
    // Test maili değilse log kaydet
    if (!$isTest) {
        $stmt = $db->prepare("
            INSERT INTO hosting_mail_logs (hosting_id, days_remaining, status, error_message) 
            VALUES (?, ?, ?, ?)
        ");
        
        $status = $result['success'] ? 'sent' : 'failed';
        $error = $result['error'] ?? null;
        
        $stmt->execute([$hosting['id'], $daysRemaining, $status, $error]);
    }
    
    return $result['success'];
}

// Tüm hostingler için bildirimleri kontrol et
function checkAndSendHostingNotifications($db) {
    // Mail ayarlarını al
    $settings = getMailSettings($db);
    if (!$settings) {
        return ['success' => false, 'error' => 'Mail ayarları yapılandırılmamış'];
    }
    
    // Bildirim günlerini al
    $notificationDays = explode(',', $settings['notification_days']);
    $notificationDays = array_map('intval', $notificationDays);
    
    // Süresi yaklaşan hostingleri al
    $stmt = $db->query("
        SELECT * FROM hostings 
        WHERE expiry_date IS NOT NULL 
        AND expiry_date > CURDATE()
        AND DATEDIFF(expiry_date, CURDATE()) <= 30
        AND status = 'active'
    ");
    
    $hostings = $stmt->fetchAll();
    $sentCount = 0;
    
    foreach ($hostings as $hosting) {
        $daysRemaining = calculateHostingDaysRemaining($hosting['expiry_date']);
        
        // Bu gün bildirim gönderilmeli mi?
        if (in_array($daysRemaining, $notificationDays)) {
            if (sendHostingExpiryNotification($hosting, $daysRemaining, $db)) {
                $sentCount++;
            }
        }
    }
    
    return ['success' => true, 'sent' => $sentCount];
}

// Yeni hosting eklendiğinde kontrol et
function checkNewHostingExpiry($hostingId, $db) {
    $stmt = $db->prepare("SELECT * FROM hostings WHERE id = ?");
    $stmt->execute([$hostingId]);
    $hosting = $stmt->fetch();
    
    if (!$hosting || !$hosting['expiry_date']) {
        return false;
    }
    
    $daysRemaining = calculateHostingDaysRemaining($hosting['expiry_date']);
    
    // 7 gün veya daha az kaldıysa hemen bildirim gönder
    if ($daysRemaining <= 7 && $daysRemaining >= 0) {
        return sendHostingExpiryNotification($hosting, $daysRemaining, $db);
    }
    
    return false;
}
?>
