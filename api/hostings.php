<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/functions.php';

// Admin kontrolü
checkAdmin();

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetHostings();
            break;
        case 'POST':
            handlePostHosting();
            break;
        case 'PUT':
            handlePutHosting();
            break;
        case 'DELETE':
            handleDeleteHosting();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGetHostings() {
    global $db;
    
    // Tek hosting bilgisi isteniyor mu?
    if (isset($_GET['id'])) {
        $hosting_id = (int)$_GET['id'];
        $stmt = $db->prepare("SELECT * FROM hostings WHERE id = ?");
        $stmt->execute([$hosting_id]);
        $hosting = $stmt->fetch();
        
        if ($hosting) {
            $hosting['days_remaining'] = calculateHostingDaysRemaining($hosting['expiry_date']);
            $hosting['status_color'] = getHostingStatusColor($hosting['days_remaining']);
            $hosting['status_text'] = getHostingStatusText($hosting['days_remaining']);
            
            echo json_encode([
                'success' => true,
                'data' => [$hosting]
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Hosting bulunamadı']);
        }
        return;
    }
    
    $search = $_GET['search'] ?? '';
    $filter = $_GET['filter'] ?? 'all';
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);
    
    $query = "SELECT * FROM hostings WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (customer_name LIKE ? OR hosting_name LIKE ? OR provider LIKE ?)";
        $params[] = "%$search%";
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
    
    $query .= " ORDER BY expiry_date ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $hostings = $stmt->fetchAll();
    
    // Her hosting için kalan gün sayısını hesapla
    foreach ($hostings as &$hosting) {
        $hosting['days_remaining'] = calculateHostingDaysRemaining($hosting['expiry_date']);
        $hosting['status_color'] = getHostingStatusColor($hosting['days_remaining']);
        $hosting['status_text'] = getHostingStatusText($hosting['days_remaining']);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $hostings,
        'total' => count($hostings)
    ]);
}

function handlePostHosting() {
    global $db;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    $required_fields = ['customer_name', 'hosting_name', 'start_date', 'duration_months'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Gerekli alan eksik: $field"]);
            return;
        }
    }
    
    $customer_name = $input['customer_name'];
    $hosting_name = $input['hosting_name'];
    $provider = $input['provider'] ?? null;
    $start_date = $input['start_date'];
    $duration_months = (int)$input['duration_months'];
    $price = isset($input['price']) && $input['price'] !== '' ? (float)$input['price'] : null;
    $notes = $input['notes'] ?? null;
    
    // Bitiş tarihini hesapla
    $expiry_date = date('Y-m-d', strtotime($start_date . " +$duration_months months"));
    
    $stmt = $db->prepare("INSERT INTO hostings (customer_name, hosting_name, provider, start_date, expiry_date, duration_months, price, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$customer_name, $hosting_name, $provider, $start_date, $expiry_date, $duration_months, $price, $notes]);
    
    $hosting_id = $db->lastInsertId();
    
    // Eklenen hostingi geri döndür
    $stmt = $db->prepare("SELECT * FROM hostings WHERE id = ?");
    $stmt->execute([$hosting_id]);
    $hosting = $stmt->fetch();
    
    $hosting['days_remaining'] = calculateHostingDaysRemaining($hosting['expiry_date']);
    $hosting['status_color'] = getHostingStatusColor($hosting['days_remaining']);
    $hosting['status_text'] = getHostingStatusText($hosting['days_remaining']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Hosting başarıyla eklendi',
        'data' => $hosting
    ]);
}

function handlePutHosting() {
    global $db;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Hosting ID gerekli']);
        return;
    }
    
    $hosting_id = (int)$input['id'];
    
    // Mevcut hostingi kontrol et
    $stmt = $db->prepare("SELECT * FROM hostings WHERE id = ?");
    $stmt->execute([$hosting_id]);
    $existing = $stmt->fetch();
    
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['error' => 'Hosting bulunamadı']);
        return;
    }
    
    // Güncellenecek alanları belirle
    $customer_name = $input['customer_name'] ?? $existing['customer_name'];
    $hosting_name = $input['hosting_name'] ?? $existing['hosting_name'];
    $provider = $input['provider'] ?? $existing['provider'];
    $start_date = $input['start_date'] ?? $existing['start_date'];
    $duration_months = isset($input['duration_months']) ? (int)$input['duration_months'] : $existing['duration_months'];
    $price = isset($input['price']) ? (float)$input['price'] : $existing['price'];
    $notes = $input['notes'] ?? $existing['notes'];
    
    // Bitiş tarihini yeniden hesapla
    $expiry_date = date('Y-m-d', strtotime($start_date . " +$duration_months months"));
    
    $stmt = $db->prepare("UPDATE hostings SET customer_name = ?, hosting_name = ?, provider = ?, start_date = ?, expiry_date = ?, duration_months = ?, price = ?, notes = ? WHERE id = ?");
    $stmt->execute([$customer_name, $hosting_name, $provider, $start_date, $expiry_date, $duration_months, $price, $notes, $hosting_id]);
    
    // Güncellenmiş hostingi geri döndür
    $stmt = $db->prepare("SELECT * FROM hostings WHERE id = ?");
    $stmt->execute([$hosting_id]);
    $hosting = $stmt->fetch();
    
    $hosting['days_remaining'] = calculateHostingDaysRemaining($hosting['expiry_date']);
    $hosting['status_color'] = getHostingStatusColor($hosting['days_remaining']);
    $hosting['status_text'] = getHostingStatusText($hosting['days_remaining']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Hosting başarıyla güncellendi',
        'data' => $hosting
    ]);
}

function handleDeleteHosting() {
    global $db;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Hosting ID gerekli']);
        return;
    }
    
    $hosting_id = (int)$input['id'];
    
    // Hostingi kontrol et
    $stmt = $db->prepare("SELECT * FROM hostings WHERE id = ?");
    $stmt->execute([$hosting_id]);
    $hosting = $stmt->fetch();
    
    if (!$hosting) {
        http_response_code(404);
        echo json_encode(['error' => 'Hosting bulunamadı']);
        return;
    }
    
    // Hostingi sil
    $stmt = $db->prepare("DELETE FROM hostings WHERE id = ?");
    $stmt->execute([$hosting_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Hosting başarıyla silindi'
    ]);
}

function calculateHostingDaysRemaining($expiry_date) {
    $today = new DateTime();
    $expiry = new DateTime($expiry_date);
    $diff = $today->diff($expiry);
    
    if ($expiry < $today) {
        return -$diff->days;
    }
    
    return $diff->days;
}

function getHostingStatusColor($days) {
    if ($days < 0) return 'danger';
    if ($days <= 7) return 'danger';
    if ($days <= 30) return 'warning';
    return 'success';
}

function getHostingStatusText($days) {
    if ($days < 0) return abs($days) . ' gün önce doldu';
    if ($days == 0) return 'Bugün doluyor';
    if ($days == 1) return 'Yarın doluyor';
    return $days . ' gün kaldı';
}
?>
