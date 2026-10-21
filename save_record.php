<?php
header('Content-Type: text/plain; charset=utf-8');
$conn = new mysqli('localhost', 'root', '', 'borrow_db');
if ($conn->connect_error) { http_response_code(500); exit('ไม่สามารถเชื่อมต่อฐานข้อมูลได้: ' . $conn->connect_error); }
$conn->set_charset('utf8mb4');

function column_exists($conn, $column) {
    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM transactions LIKE '$column'");
    return $result && $result->num_rows > 0;
}
function ensure_transaction_columns($conn) {
    $columns = ['sub_item_name' => "VARCHAR(150) NOT NULL DEFAULT ''", 'serial_number' => "VARCHAR(100) NOT NULL DEFAULT ''", 'borrower_phone' => "VARCHAR(30) NOT NULL DEFAULT ''", 'borrow_duration' => "VARCHAR(100) NOT NULL DEFAULT ''", 'lender_name' => "VARCHAR(100) NOT NULL DEFAULT ''", 'return_image_path' => "TEXT NULL", 'return_time' => "DATETIME NULL", 'condition_status' => "VARCHAR(30) NOT NULL DEFAULT 'pending'", 'condition_note' => "TEXT NULL"];
    foreach ($columns as $name => $definition) if (!column_exists($conn, $name)) $conn->query("ALTER TABLE transactions ADD COLUMN `$name` $definition");

    $image_column = $conn->query("SHOW COLUMNS FROM transactions LIKE 'image_path'");
    if ($image_column && ($image_info = $image_column->fetch_assoc()) && stripos($image_info['Type'], 'text') === false) {
        $conn->query("ALTER TABLE transactions MODIFY COLUMN image_path TEXT NOT NULL");
    }
}
function save_images($images) {
    $directory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($directory) && !mkdir($directory, 0777, true)) return [];
    $paths = [];
    foreach ($images as $image) {
        if (!is_string($image) || !preg_match('#^data:image/(png|jpe?g|webp);base64,#i', $image, $match)) continue;
        $data = base64_decode(substr($image, strpos($image, ',') + 1), true);
        if ($data === false) continue;
        $extension = strtolower($match[1]) === 'jpeg' ? 'jpg' : strtolower($match[1]);
        $filename = uniqid('equipment_', true) . '.' . $extension;
        if (file_put_contents($directory . DIRECTORY_SEPARATOR . $filename, $data) !== false) $paths[] = 'uploads/' . $filename;
    }
    return $paths;
}
function delete_image_paths($value) {
    foreach (array_filter(array_map('trim', explode(',', (string)$value))) as $path) {
        $absolute = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        if (is_file($absolute)) unlink($absolute);
    }
}
ensure_transaction_columns($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_condition') {
    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['condition_status'] ?? '';
    $note = trim($_POST['condition_note'] ?? '');
    if ($id < 1 || !in_array($status, ['good', 'damaged', 'needs_review'], true)) {
        http_response_code(422); exit('Invalid condition update');
    }
    $stmt = $conn->prepare("UPDATE transactions SET condition_status = ?, condition_note = ? WHERE id = ? AND action_type = 'Borrow'");
    $stmt->bind_param('ssi', $status, $note, $id);
    if ($stmt->execute() && $stmt->affected_rows >= 0) exit('Condition updated');
    http_response_code(500); exit('Unable to update condition');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action_type'] ?? 'Borrow';
    $borrower = trim($_POST['borrower_name'] ?? '');
    $item = trim($_POST['item_name'] ?? '');
    $images = $_POST['image'] ?? [];
    $images = is_array($images) ? $images : [$images];
    if ($borrower === '' || $item === '' || empty($images)) { http_response_code(422); exit('กรุณากรอกชื่อผู้ยืม ชื่ออุปกรณ์ และถ่ายรูปอย่างน้อย 1 รูป'); }
    $saved_paths = save_images($images);
    if (empty($saved_paths)) { http_response_code(422); exit('ไม่สามารถบันทึกรูปภาพได้ กรุณาเลือกรูปภาพใหม่'); }

    if ($action === 'Return') {
        $borrow_id = (int)($_POST['borrow_id'] ?? 0);
        $condition = $_POST['condition_status'] ?? 'needs_review';
        $note = trim($_POST['condition_note'] ?? '');
        if ($borrow_id < 1) {
            $normalized_borrower = preg_replace('/\s+/u', '', $borrower);
            $normalized_item = preg_replace('/\s+/u', '', $item);
            $match = $conn->prepare("SELECT id FROM transactions WHERE REPLACE(TRIM(borrower_name), ' ', '') = ? AND (REPLACE(TRIM(item_name), ' ', '') = ? OR REPLACE(TRIM(item_name), ' ', '') LIKE CONCAT('%', ?, '%') OR ? LIKE CONCAT('%', REPLACE(TRIM(item_name), ' ', ''), '%')) AND action_type = 'Borrow' ORDER BY (return_time IS NULL) DESC, record_time DESC LIMIT 1");
            $match->bind_param('ssss', $normalized_borrower, $normalized_item, $normalized_item, $normalized_item); $match->execute();
            $matches = $match->get_result();
            if ($matches->num_rows === 1) $borrow_id = (int)$matches->fetch_assoc()['id'];
        }
        $check = $conn->prepare("SELECT id, image_path, return_image_path, condition_status, condition_note FROM transactions WHERE id = ? AND action_type = 'Borrow'");
        $check->bind_param('i', $borrow_id); $check->execute();
        $existing = $check->get_result();
        if ($borrow_id < 1 || $existing->num_rows === 0) { foreach ($saved_paths as $path) delete_image_paths($path); http_response_code(404); exit('ไม่พบรายการยืม กรุณาตรวจสอบชื่อผู้ยืมและชื่ออุปกรณ์'); }
        $existing_row = $existing->fetch_assoc();
        $return_paths = implode(',', array_filter(array_merge(explode(',', (string)$existing_row['return_image_path']), $saved_paths)));
        $combined_note = trim((string)$existing_row['condition_note']);
        if ($note !== '') $combined_note = $combined_note === '' ? $note : $combined_note . "\n" . $note;
        $borrow_image_count = count(array_filter(array_map('trim', explode(',', (string)$existing_row['image_path']))));
        $return_image_count = count(array_filter(array_map('trim', explode(',', $return_paths))));
        $final_condition = ($borrow_image_count > 0 && $return_image_count >= $borrow_image_count) ? 'good' : 'needs_review';
        $is_additional_return = !empty($existing_row['return_image_path']);
        $stmt = $conn->prepare("UPDATE transactions SET return_image_path = ?, return_time = COALESCE(return_time, NOW()), condition_status = ?, condition_note = ? WHERE id = ?");
        $stmt->bind_param('sssi', $return_paths, $final_condition, $combined_note, $borrow_id);
        if ($stmt->execute()) exit($is_additional_return ? 'เพิ่มรูปและรายละเอียดการคืนในรายการเดิมเรียบร้อยแล้ว' : 'บันทึกการคืนและรูปเปรียบเทียบเรียบร้อยแล้ว');
    } else {
        $sub_item = trim($_POST['sub_item_name'] ?? '');
        $serial_number = trim($_POST['serial_number'] ?? '');
        $phone = trim($_POST['borrower_phone'] ?? '');
        $duration = trim($_POST['borrow_duration'] ?? '');
        $lender = trim($_POST['lender_name'] ?? '');
        $borrow_paths = implode(',', $saved_paths); $status = 'pending';
        $stmt = $conn->prepare("INSERT INTO transactions (borrower_name, item_name, sub_item_name, serial_number, borrower_phone, borrow_duration, lender_name, action_type, image_path, condition_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Borrow', ?, ?)");
        $stmt->bind_param('sssssssss', $borrower, $item, $sub_item, $serial_number, $phone, $duration, $lender, $borrow_paths, $status);
        if ($stmt->execute()) exit('บันทึกการยืมและรูปสภาพก่อนยืมเรียบร้อยแล้ว');
    }
    foreach ($saved_paths as $path) delete_image_paths($path);
    http_response_code(500); exit('บันทึกข้อมูลไม่สำเร็จ: ' . $conn->error);
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] === 'delete' && isset($_GET['id'])) {
        $id = (int)$_GET['id']; $result = $conn->query("SELECT image_path, return_image_path FROM transactions WHERE id = $id");
        if ($result && ($row = $result->fetch_assoc())) { delete_image_paths($row['image_path']); delete_image_paths($row['return_image_path']); }
        exit($conn->query("DELETE FROM transactions WHERE id = $id") ? 'ลบรายการเรียบร้อยแล้ว' : 'ลบรายการไม่สำเร็จ');
    }
    if ($_GET['action'] === 'delete_all') {
        $result = $conn->query('SELECT image_path, return_image_path FROM transactions');
        while ($result && ($row = $result->fetch_assoc())) { delete_image_paths($row['image_path']); delete_image_paths($row['return_image_path']); }
        exit($conn->query('DELETE FROM transactions') ? 'ลบประวัติทั้งหมดเรียบร้อยแล้ว' : 'ลบประวัติทั้งหมดไม่สำเร็จ');
    }
}
http_response_code(400); echo 'รูปแบบคำขอไม่ถูกต้อง';
