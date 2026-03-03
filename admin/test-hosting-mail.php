<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/mail-functions.php';

// Admin kontrolü
checkAdmin();

$message = '';
$error = '';

// Test hosting maili gönder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test_hosting_mail'])) {
    try {
        // Mail ayarlarını kontrol et
        $stmt = $db->query("SELECT * FROM mail_settings WHERE enabled = 1 LIMIT 1");
        $mailSettings = $stmt->fetch();
        
        if (!$mailSettings) {
            throw new Exception('Mail ayarları yapılandırılmamış veya devre dışı!');
        }
        
        // Test hosting verisi oluştur
        $testHosting = [
            'id' => 999,
            'customer_name' => 'Test Müşteri',
            'hosting_name' => 'test-hosting.com',
            'provider' => 'Test Hosting Sağlayıcısı',
            'start_date' => date('Y-m-d', strtotime('-11 months')),
            'expiry_date' => date('Y-m-d', strtotime('+' . $_POST['days_remaining'] . ' days')),
            'duration_months' => 12,
            'price' => 299.99,
            'notes' => 'Bu bir test hosting kaydıdır. Gerçek bir hosting değildir.',
            'status' => 'active'
        ];
        
        $daysRemaining = (int)$_POST['days_remaining'];
        
        // Test maili gönder (test parametresi ile)
        $result = sendHostingExpiryNotification($testHosting, $daysRemaining, $db, true);
        
        if ($result) {
            $message = "Test hosting maili başarıyla gönderildi! ({$daysRemaining} gün kaldı senaryosu)";
        } else {
            $error = "Test maili gönderilemedi. Mail ayarlarını kontrol edin.";
        }
        
    } catch (Exception $e) {
        $error = 'Hata: ' . $e->getMessage();
    }
}

// Mail loglarını çek
try {
    $stmt = $db->query("
        SELECT hml.*, h.customer_name, h.hosting_name 
        FROM hosting_mail_logs hml 
        LEFT JOIN hostings h ON hml.hosting_id = h.id 
        ORDER BY hml.sent_at DESC 
        LIMIT 10
    ");
    $mailLogs = $stmt->fetchAll();
} catch (PDOException $e) {
    $mailLogs = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Hosting Maili - BD Domain Takip</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo">
                    <i class="fas fa-globe"></i>
                    BD Domain Takip Sistemi
                </a>
                <nav style="flex: 1; display: flex; justify-content: center; align-items: center;">
                    <a href="index.php" style="color: white; text-decoration: none; font-weight: 500; padding: 0.5rem 1rem; border-radius: var(--radius-sm); transition: all 0.3s ease;">
                        <i class="fas fa-server"></i> Domain Kontrol
                    </a>
                    <a href="hosting-takip.php" style="color: white; text-decoration: none; font-weight: 500; padding: 0.5rem 1rem; margin-left: 1rem; border-radius: var(--radius-sm); transition: all 0.3s ease;">
                        <i class="fas fa-cloud"></i> Hosting Takip
                    </a>
                    <a href="borc-takip.php" style="color: white; text-decoration: none; font-weight: 500; padding: 0.5rem 1rem; margin-left: 1rem; border-radius: var(--radius-sm); transition: all 0.3s ease;">
                        <i class="fas fa-money-bill-wave"></i> Borç Takip
                    </a>
                </nav>
                <div class="user-menu">
                    <div class="user-info">
                        <i class="fas fa-user-circle"></i>
                        <span><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                    </div>
                    <a href="change-password.php" class="btn-logout" style="background: rgba(255, 255, 255, 0.1); margin-right: 0.5rem;">
                        <i class="fas fa-key"></i> Şifre Değiştir
                    </a>
                    <a href="logout.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Çıkış
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container" style="max-width: 1000px;">
            <div class="domain-panel">
                <div class="panel-header">
                    <h2 class="panel-title">
                        <i class="fas fa-paper-plane"></i> Test Hosting Maili
                    </h2>
                    <div class="panel-actions">
                        <a href="hosting-takip.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Hosting Takip
                        </a>
                        <a href="mail-settings.php" class="btn btn-primary">
                            <i class="fas fa-cog"></i> Mail Ayarları
                        </a>
                    </div>
                </div>
                
                <div style="padding: 2rem;">
                    <?php if ($message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                        <!-- Test Mail Gönderme -->
                        <div>
                            <h3 style="margin-bottom: 1rem;">
                                <i class="fas fa-envelope-open-text"></i> Test Maili Gönder
                            </h3>
                            
                            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                                <h4 style="margin-bottom: 1rem;">Test Hosting Bilgileri:</h4>
                                <ul style="margin: 0; padding-left: 1.5rem;">
                                    <li><strong>Müşteri:</strong> Test Müşteri</li>
                                    <li><strong>Hosting:</strong> test-hosting.com</li>
                                    <li><strong>Sağlayıcı:</strong> Test Hosting Sağlayıcısı</li>
                                    <li><strong>Süre:</strong> 12 ay</li>
                                    <li><strong>Fiyat:</strong> 299.99 ₺</li>
                                    <li><strong>Not:</strong> Bu bir test hosting kaydıdır</li>
                                </ul>
                            </div>
                            
                            <form method="POST">
                                <div class="form-group">
                                    <label class="form-label" for="days_remaining">Kalan Gün Sayısı</label>
                                    <select id="days_remaining" name="days_remaining" class="form-control" required>
                                        <option value="">Seçin...</option>
                                        <option value="30">30 gün kaldı (Uyarı)</option>
                                        <option value="15">15 gün kaldı (Uyarı)</option>
                                        <option value="7">7 gün kaldı (Kritik)</option>
                                        <option value="5">5 gün kaldı (Kritik)</option>
                                        <option value="3">3 gün kaldı (Kritik)</option>
                                        <option value="1">1 gün kaldı (Acil)</option>
                                        <option value="0">Bugün süresi doluyor (Acil)</option>
                                        <option value="-1">1 gün geçmiş (Süresi dolmuş)</option>
                                    </select>
                                    <div class="form-text">Farklı senaryoları test etmek için gün sayısını seçin</div>
                                </div>
                                
                                <button type="submit" name="send_test_hosting_mail" class="btn btn-success">
                                    <i class="fas fa-paper-plane"></i> Test Maili Gönder
                                </button>
                            </form>
                        </div>
                        
                        <!-- Mail Önizleme -->
                        <div>
                            <h3 style="margin-bottom: 1rem;">
                                <i class="fas fa-eye"></i> Mail Önizleme
                            </h3>
                            
                            <div style="border: 1px solid #ddd; border-radius: 8px; padding: 1rem; background: white; font-size: 0.875rem;">
                                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                                    <h4 style="margin: 0; color: #333;">☁️ Hosting Süresi Bildirimi</h4>
                                </div>
                                
                                <div style="padding: 10px; background: #ffeaa7; color: #856404; border: 1px solid #ffeaa7; border-radius: 4px; margin: 15px 0;">
                                    <strong>Dikkat!</strong> Aşağıdaki hostingin süresi dolmak üzere:
                                </div>
                                
                                <table style="width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 0.8rem;">
                                    <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold; width: 100px;">Müşteri:</td><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Test Müşteri</strong></td></tr>
                                    <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Hosting:</td><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>test-hosting.com</strong></td></tr>
                                    <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Kalan Süre:</td><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong style="color: #856404;">X gün kaldı</strong></td></tr>
                                    <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Bitiş Tarihi:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">Seçilen güne göre</td></tr>
                                    <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Sağlayıcı:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">Test Hosting Sağlayıcısı</td></tr>
                                    <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Fiyat:</td><td style="padding: 8px; border-bottom: 1px solid #eee;">299,99 ₺</td></tr>
                                </table>
                                
                                <p style="font-size: 0.8rem;">Hosting sürenizin dolmasına <strong>X gün</strong> kaldı. Lütfen yenileme işlemini zamanında yapınız.</p>
                                
                                <div style="text-align: center; margin: 15px 0;">
                                    <a href="#" style="display: inline-block; padding: 8px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 0.8rem;">Hosting Paneline Git</a>
                                </div>
                                
                                <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee; font-size: 0.7rem; color: #666;">
                                    <p style="margin: 0;">Bu mail Domain Takip Sistemi tarafından otomatik olarak gönderilmiştir.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Mail Logları -->
                    <?php if (!empty($mailLogs)): ?>
                    <div style="margin-top: 3rem;">
                        <h3 style="margin-bottom: 1rem;">
                            <i class="fas fa-history"></i> Son Gönderilen Mailler
                        </h3>
                        
                        <div style="overflow-x: auto;">
                            <table class="domain-table">
                                <thead>
                                    <tr>
                                        <th>Hosting</th>
                                        <th>Müşteri</th>
                                        <th>Kalan Gün</th>
                                        <th>Durum</th>
                                        <th>Gönderim Zamanı</th>
                                        <th>Hata</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mailLogs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['hosting_name'] ?: 'Test Hosting'); ?></td>
                                        <td><?php echo htmlspecialchars($log['customer_name'] ?: 'Test Müşteri'); ?></td>
                                        <td><?php echo $log['days_remaining']; ?> gün</td>
                                        <td>
                                            <span class="status-badge <?php echo $log['status'] == 'sent' ? 'success' : 'danger'; ?>">
                                                <i class="fas fa-circle"></i>
                                                <?php echo $log['status'] == 'sent' ? 'Gönderildi' : 'Başarısız'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($log['sent_at'])); ?></td>
                                        <td>
                                            <?php if ($log['error_message']): ?>
                                                <span style="color: var(--danger-color); font-size: 0.875rem;">
                                                    <?php echo htmlspecialchars($log['error_message']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: var(--success-color);">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <style>
        .alert {
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
    </style>
</body>
</html>
