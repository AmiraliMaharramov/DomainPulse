<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Admin kontrolü
checkAdmin();

// İstatistikleri çek
try {
    // Toplam hosting sayısı
    $stmt = $db->query("SELECT COUNT(*) FROM hostings");
    $totalHostings = $stmt->fetchColumn();
    
    // Aktif hosting sayısı (süresi dolmamış)
    $stmt = $db->query("SELECT COUNT(*) FROM hostings WHERE expiry_date > CURDATE()");
    $activeHostings = $stmt->fetchColumn();
    
    // 30 gün içinde dolacak hostingler
    $stmt = $db->query("SELECT COUNT(*) FROM hostings WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
    $expiringHostings = $stmt->fetchColumn();
    
    // Süresi dolmuş hostingler
    $stmt = $db->query("SELECT COUNT(*) FROM hostings WHERE expiry_date < CURDATE()");
    $expiredHostings = $stmt->fetchColumn();
    
    // Hosting listesini çek
    $search = $_GET['search'] ?? '';
    $filter = $_GET['filter'] ?? 'all';
    
    $query = "SELECT * FROM hostings WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (customer_name LIKE ? OR hosting_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    switch ($filter) {
        case 'active':
            $query .= " AND expiry_date > CURDATE()";
            break;
        case 'expiring':
            $query .= " AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
            break;
        case 'expired':
            $query .= " AND expiry_date < CURDATE()";
            break;
    }
    
    $query .= " ORDER BY expiry_date ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $hostings = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Veritabanı hatası: " . $e->getMessage());
}

// Hosting ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_hosting') {
    try {
        $customer_name = $_POST['customer_name'];
        $hosting_name = $_POST['hosting_name'];
        $provider = $_POST['provider'];
        $start_date = $_POST['start_date'];
        $duration_months = (int)$_POST['duration_months'];
        $price = $_POST['price'] ? (float)$_POST['price'] : null;
        $notes = $_POST['notes'];
        
        // Bitiş tarihini hesapla
        $expiry_date = date('Y-m-d', strtotime($start_date . " +$duration_months months"));
        
        $stmt = $db->prepare("INSERT INTO hostings (customer_name, hosting_name, provider, start_date, expiry_date, duration_months, price, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$customer_name, $hosting_name, $provider, $start_date, $expiry_date, $duration_months, $price, $notes]);
        
        header('Location: hosting-takip.php?success=1');
        exit;
    } catch (PDOException $e) {
        $error = "Hosting eklenirken hata oluştu: " . $e->getMessage();
    }
}

// Hosting düzenleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_hosting') {
    try {
        $hosting_id = (int)$_POST['hosting_id'];
        $customer_name = $_POST['customer_name'];
        $hosting_name = $_POST['hosting_name'];
        $provider = $_POST['provider'];
        $start_date = $_POST['start_date'];
        $duration_months = (int)$_POST['duration_months'];
        $price = $_POST['price'] ? (float)$_POST['price'] : null;
        $notes = $_POST['notes'];
        
        // Bitiş tarihini hesapla
        $expiry_date = date('Y-m-d', strtotime($start_date . " +$duration_months months"));
        
        $stmt = $db->prepare("UPDATE hostings SET customer_name = ?, hosting_name = ?, provider = ?, start_date = ?, expiry_date = ?, duration_months = ?, price = ?, notes = ? WHERE id = ?");
        $stmt->execute([$customer_name, $hosting_name, $provider, $start_date, $expiry_date, $duration_months, $price, $notes, $hosting_id]);
        
        header('Location: hosting-takip.php?updated=1');
        exit;
    } catch (PDOException $e) {
        $error = "Hosting güncellenirken hata oluştu: " . $e->getMessage();
    }
}

// Hosting silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_hosting') {
    try {
        $hosting_id = (int)$_POST['hosting_id'];
        $stmt = $db->prepare("DELETE FROM hostings WHERE id = ?");
        $stmt->execute([$hosting_id]);
        
        header('Location: hosting-takip.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        $error = "Hosting silinirken hata oluştu: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hosting Takip - BD Domain Takip Sistemi</title>
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
                    <a href="hosting-takip.php" style="color: white; text-decoration: none; font-weight: 500; padding: 0.5rem 1rem; margin-left: 1rem; border-radius: var(--radius-sm); transition: all 0.3s ease; background: rgba(255, 255, 255, 0.1);">
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
        <div class="container">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Hosting başarıyla eklendi!
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['updated'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Hosting başarıyla güncellendi!
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Hosting başarıyla silindi!
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- İstatistik Kartları -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3 class="stat-card-title">Toplam Hosting</h3>
                        <div class="stat-card-icon primary">
                            <i class="fas fa-cloud"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo $totalHostings; ?></div>
                    <div class="stat-card-desc">Kayıtlı hosting sayısı</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3 class="stat-card-title">Aktif Hosting</h3>
                        <div class="stat-card-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo $activeHostings; ?></div>
                    <div class="stat-card-desc">Süresi dolmamış</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3 class="stat-card-title">Yakında Dolacak</h3>
                        <div class="stat-card-icon warning">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo $expiringHostings; ?></div>
                    <div class="stat-card-desc">30 gün içinde</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3 class="stat-card-title">Süresi Dolmuş</h3>
                        <div class="stat-card-icon danger">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo $expiredHostings; ?></div>
                    <div class="stat-card-desc">Yenilenmesi gereken</div>
                </div>
            </div>

            <!-- Hosting Paneli -->
            <div class="domain-panel">
                <div class="panel-header">
                    <h2 class="panel-title">Hosting Listesi</h2>
                    <div class="panel-actions">
                        <form method="GET" class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text"
                                   name="search"
                                   placeholder="Müşteri veya hosting ara..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                        </form>
                        
                        <div style="display: grid; grid-template-columns: 1fr; gap: 0.5rem; width: 100%;">
                            <button class="btn btn-primary" onclick="showAddHostingModal()">
                                <i class="fas fa-plus"></i> Yeni Hosting
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filtre Butonları -->
                <div style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--gray-200);">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 0.5rem;">
                        <a href="?filter=all<?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                           class="btn btn-sm <?php echo $filter == 'all' ? 'btn-primary' : 'btn-secondary'; ?>">
                            Tümü (<?php echo $totalHostings; ?>)
                        </a>
                        <a href="?filter=active<?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                           class="btn btn-sm <?php echo $filter == 'active' ? 'btn-primary' : 'btn-secondary'; ?>">
                            Aktif (<?php echo $activeHostings; ?>)
                        </a>
                        <a href="?filter=expiring<?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                           class="btn btn-sm <?php echo $filter == 'expiring' ? 'btn-primary' : 'btn-secondary'; ?>">
                            Yakında Dolacak (<?php echo $expiringHostings; ?>)
                        </a>
                        <a href="?filter=expired<?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                           class="btn btn-sm <?php echo $filter == 'expired' ? 'btn-primary' : 'btn-secondary'; ?>">
                            Süresi Dolmuş (<?php echo $expiredHostings; ?>)
                        </a>
                    </div>
                </div>

                <!-- Hosting Tablosu -->
                <?php if (empty($hostings)): ?>
                    <div class="empty-state">
                        <i class="fas fa-cloud"></i>
                        <h3>Hosting bulunamadı</h3>
                        <p>Henüz kayıtlı hosting bulunmuyor veya arama kriterlerinize uygun sonuç yok.</p>
                        <button class="btn btn-primary" onclick="showAddHostingModal()">
                            <i class="fas fa-plus"></i> İlk Hostingi Ekle
                        </button>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                    <table class="domain-table">
                        <thead>
                            <tr>
                                <th>Müşteri Adı</th>
                                <th>Domain Adı</th>
                                <th>Sağlayıcı</th>
                                <th class="hide-mobile">Başlangıç</th>
                                <th>Bitiş Tarihi</th>
                                <th class="hide-mobile">Süre</th>
                                <th class="hide-mobile">Fiyat</th>
                                <th>Durum</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hostings as $hosting): ?>
                                <?php 
                                    $days = calculateHostingDaysRemaining($hosting['expiry_date']);
                                    $statusColor = getHostingStatusColor($days);
                                    $statusText = getHostingStatusText($days);
                                ?>
                                <tr>
                                    <td>
                                        <div class="domain-name"><?php echo htmlspecialchars($hosting['customer_name']); ?></div>
                                    </td>
                                    <td>
                                        <div class="domain-name"><?php echo htmlspecialchars($hosting['hosting_name']); ?></div>
                                        <?php if ($hosting['notes']): ?>
                                            <div class="domain-registrar">
                                                <i class="fas fa-sticky-note"></i>
                                                <?php echo htmlspecialchars($hosting['notes']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($hosting['provider'] ?: 'Belirtilmemiş'); ?></td>
                                    <td class="hide-mobile">
                                        <?php echo date('d.m.Y', strtotime($hosting['start_date'])); ?>
                                    </td>
                                    <td>
                                        <?php echo date('d.m.Y', strtotime($hosting['expiry_date'])); ?>
                                    </td>
                                    <td class="hide-mobile">
                                        <?php echo $hosting['duration_months']; ?> ay
                                    </td>
                                    <td class="hide-mobile">
                                        <?php echo $hosting['price'] ? number_format($hosting['price'], 2) . ' ₺' : '-'; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $statusColor; ?>">
                                            <i class="fas fa-circle"></i>
                                            <?php echo $statusText; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn-action"
                                                    onclick="editHosting(<?php echo $hosting['id']; ?>, event)"
                                                    title="Düzenle">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-action danger"
                                                    onclick="deleteHosting(<?php echo $hosting['id']; ?>, '<?php echo htmlspecialchars($hosting['customer_name']); ?>', event)"
                                                    title="Sil">
                                            <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Hosting Ekleme Modal -->
    <div id="addHostingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Yeni Hosting Ekle</h3>
                <button class="modal-close" onclick="closeModal('addHostingModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_hosting">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="customer_name">Müşteri Adı *</label>
                        <input type="text" 
                               id="customer_name" 
                               name="customer_name" 
                               class="form-control" 
                               placeholder="Müşteri adını girin"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="hosting_name">Domain Adı *</label>
                        <input type="text"
                               id="hosting_name"
                               name="hosting_name"
                               class="form-control"
                               placeholder="Domain Adını girin"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="provider">Sağlayıcı</label>
                        <input type="text"
                               id="provider"
                               name="provider"
                               class="form-control"
                               placeholder="Hosting sağlayıcısı">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="start_date">Başlangıç Tarihi *</label>
                        <input type="date"
                               id="start_date"
                               name="start_date"
                               class="form-control"
                               value="<?php echo date('Y-m-d'); ?>"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="duration_months">Süre *</label>
                        <select id="duration_months" name="duration_months" class="form-control" required>
                            <option value="">Süre seçin</option>
                            <option value="1">1 Ay</option>
                            <option value="2">2 Ay</option>
                            <option value="3">3 Ay</option>
                            <option value="6">6 Ay</option>
                            <option value="12">1 Yıl</option>
                            <option value="24">2 Yıl</option>
                            <option value="36">3 Yıl</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="price">Fiyat (₺)</label>
                        <input type="number"
                               id="price"
                               name="price"
                               class="form-control"
                               step="0.01"
                               placeholder="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="notes">Notlar</label>
                        <textarea id="notes"
                                  name="notes"
                                  class="form-control"
                                  rows="3"
                                  placeholder="Ek notlar..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addHostingModal')">İptal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Hosting Ekle
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hosting Düzenleme Modal -->
    <div id="editHostingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Hosting Düzenle</h3>
                <button class="modal-close" onclick="closeModal('editHostingModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit_hosting">
                <input type="hidden" id="edit_hosting_id" name="hosting_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="edit_customer_name">Müşteri Adı *</label>
                        <input type="text" 
                               id="edit_customer_name" 
                               name="customer_name" 
                               class="form-control" 
                               placeholder="Müşteri adını girin"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="edit_hosting_name">Domain Adı *</label>
                        <input type="text"
                               id="edit_hosting_name"
                               name="hosting_name"
                               class="form-control"
                               placeholder="Domain Adını girin"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="edit_provider">Sağlayıcı</label>
                        <input type="text"
                               id="edit_provider"
                               name="provider"
                               class="form-control"
                               placeholder="Hosting sağlayıcısı">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="edit_start_date">Başlangıç Tarihi *</label>
                        <input type="date"
                               id="edit_start_date"
                               name="start_date"
                               class="form-control"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="edit_duration_months">Süre *</label>
                        <select id="edit_duration_months" name="duration_months" class="form-control" required>
                            <option value="">Süre seçin</option>
                            <option value="1">1 Ay</option>
                            <option value="2">2 Ay</option>
                            <option value="3">3 Ay</option>
                            <option value="6">6 Ay</option>
                            <option value="12">1 Yıl</option>
                            <option value="24">2 Yıl</option>
                            <option value="36">3 Yıl</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="edit_price">Fiyat (₺)</label>
                        <input type="number"
                               id="edit_price"
                               name="price"
                               class="form-control"
                               step="0.01"
                               placeholder="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="edit_notes">Notlar</label>
                        <textarea id="edit_notes"
                                  name="notes"
                                  class="form-control"
                                  rows="3"
                                  placeholder="Ek notlar..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editHostingModal')">İptal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Güncelle
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script>
        function showAddHostingModal() {
            document.getElementById('addHostingModal').style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function editHosting(id, event) {
            event.preventDefault();
            
            // AJAX ile hosting bilgilerini çek
            fetch(`../api/hostings.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        const hosting = data.data[0];
                        
                        // Form alanlarını doldur
                        document.getElementById('edit_hosting_id').value = hosting.id;
                        document.getElementById('edit_customer_name').value = hosting.customer_name;
                        document.getElementById('edit_hosting_name').value = hosting.hosting_name;
                        document.getElementById('edit_provider').value = hosting.provider || '';
                        document.getElementById('edit_start_date').value = hosting.start_date;
                        document.getElementById('edit_duration_months').value = hosting.duration_months;
                        document.getElementById('edit_price').value = hosting.price || '';
                        document.getElementById('edit_notes').value = hosting.notes || '';
                        
                        // Modalı göster
                        document.getElementById('editHostingModal').style.display = 'flex';
                    } else {
                        alert('Hosting bilgileri yüklenemedi!');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Hosting bilgileri yüklenirken hata oluştu!');
                });
        }
        
        function deleteHosting(id, customerName, event) {
            event.preventDefault();
            if (confirm('Bu hostingi silmek istediğinizden emin misiniz?\n\nMüşteri: ' + customerName)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_hosting">
                    <input type="hidden" name="hosting_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Modal dışına tıklandığında kapatma
        window.onclick = function(event) {
            const addModal = document.getElementById('addHostingModal');
            const editModal = document.getElementById('editHostingModal');
            
            if (event.target == addModal) {
                addModal.style.display = 'none';
            }
            if (event.target == editModal) {
                editModal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
