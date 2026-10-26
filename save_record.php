<?php
header('Content-Type: text/plain; charset=utf-8');
date_default_timezone_set('Asia/Bangkok');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';

function column_exists($conn, $column) {
    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM transactions LIKE '$column'");
    return $result && $result->num_rows > 0;
}
function ensure_transaction_columns($conn) {
    $columns = [
        'department' => "VARCHAR(150) NOT NULL DEFAULT 'แผนกโครงข่ายสื่อสารหลัก'",
        'dept_seq' => "INT NOT NULL DEFAULT 1",
        'borrower_username' => "VARCHAR(100) NOT NULL DEFAULT ''",
        'sub_item_name' => "VARCHAR(150) NOT NULL DEFAULT ''",
        'serial_number' => "VARCHAR(100) NOT NULL DEFAULT ''",
        'borrower_phone' => "VARCHAR(30) NOT NULL DEFAULT ''",
        'borrow_duration' => "VARCHAR(100) NOT NULL DEFAULT ''",
        'lender_name' => "VARCHAR(100) NOT NULL DEFAULT ''",
        'borrower_image_path' => "TEXT NULL",
        'return_image_path' => "TEXT NULL",
        'return_time' => "DATETIME NULL",
        'condition_status' => "VARCHAR(30) NOT NULL DEFAULT 'pending'",
        'condition_note' => "TEXT NULL",
        'extension_status' => "VARCHAR(20) NOT NULL DEFAULT 'none'",
        'extension_days' => "INT NOT NULL DEFAULT 0",
        'extension_reason' => "TEXT NULL",
        'extension_request_time' => "DATETIME NULL"
    ];
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
    if (empty($value)) return 0;
    $rawList = is_array($value) ? $value : explode(',', (string)$value);
    $deletedCount = 0;
    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';

    foreach ($rawList as $raw) {
        $raw = trim((string)$raw);
        if ($raw === '') continue;

        $basename = basename($raw);
        $candidates = [
            $uploadDir . DIRECTORY_SEPARATOR . $basename,
            __DIR__ . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw), '\\/'),
            $raw
        ];

        foreach ($candidates as $filePath) {
            if (is_file($filePath)) {
                clearstatcache(true, $filePath);
                if (@unlink($filePath)) {
                    $deletedCount++;
                    break;
                }
            }
        }
    }
    return $deletedCount;
}

/**
 * สแกนโฟลเดอร์ uploads และลบไฟล์รูปภาพที่ไม่มีในฐานข้อมูล (Orphaned images)
 */
function cleanup_orphaned_images($conn) {
    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadDir)) return 0;

    // รวบรวมชื่อไฟล์รูปภาพทั้งหมดที่ยังมีอยู่ในฐานข้อมูล transactions
    $dbFiles = [];
    $res = $conn->query("SELECT image_path, return_image_path, borrower_image_path FROM transactions");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            foreach (['image_path', 'return_image_path', 'borrower_image_path'] as $col) {
                if (!empty($row[$col])) {
                    foreach (explode(',', (string)$row[$col]) as $p) {
                        $name = basename(trim($p));
                        if ($name !== '') {
                            $dbFiles[$name] = true;
                        }
                    }
                }
            }
        }
    }

    $deletedCount = 0;
    $items = scandir($uploadDir);
    if ($items) {
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '.gitkeep' || $item === '.gitignore') continue;
            $fullPath = $uploadDir . DIRECTORY_SEPARATOR . $item;
            if (!is_file($fullPath)) continue;

            // หากไม่มีชื่อไฟล์นี้ในฐานข้อมูล ให้ลบทิ้งทันที
            if (!isset($dbFiles[$item])) {
                clearstatcache(true, $fullPath);
                if (@unlink($fullPath)) {
                    $deletedCount++;
                }
            }
        }
    }
    return $deletedCount;
}

function resequence_dept_seq($conn, $department = null) {
    $conn->query("UPDATE transactions SET department = 'แผนกโครงข่ายสื่อสารหลัก' WHERE department IS NULL OR TRIM(department) = ''");
    $departments = [];
    if ($department !== null && trim($department) !== '') {
        $departments[] = trim($department);
    } else {
        $res = $conn->query("SELECT DISTINCT department FROM transactions");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if (!empty($row['department'])) {
                    $departments[] = $row['department'];
                }
            }
        }
    }
    foreach ($departments as $dept) {
        $deptEsc = $conn->real_escape_string($dept);
        $conn->query("SET @dept_num := 0");
        $conn->query("UPDATE transactions SET dept_seq = (@dept_num := @dept_num + 1) WHERE department = '$deptEsc' ORDER BY id ASC");
    }
}

function resequence_ids($conn) {
    $conn->query("SET @num := 0");
    $conn->query("UPDATE transactions SET id = (@num := @num + 1) ORDER BY id ASC");
    $conn->query("ALTER TABLE transactions AUTO_INCREMENT = 1");
    resequence_dept_seq($conn);
}
ensure_transaction_columns($conn);
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';

if ($requestMethod === 'POST' && ($_POST['action'] ?? '') === 'update_condition') {
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

if ($requestMethod === 'POST' && ($_POST['action'] ?? '') === 'update_note') {
    $id = (int)($_POST['id'] ?? 0);
    $note = trim($_POST['condition_note'] ?? '');
    if ($id < 1) { http_response_code(422); exit('ไม่พบรายการที่ต้องการบันทึกหมายเหตุ'); }
    $stmt = $conn->prepare("UPDATE transactions SET condition_note = ? WHERE id = ? AND action_type = 'Borrow'");
    $stmt->bind_param('si', $note, $id);
    if ($stmt->execute()) exit('บันทึกหมายเหตุเรียบร้อยแล้ว');
    http_response_code(500); exit('ไม่สามารถบันทึกหมายเหตุได้: ' . $conn->error);
}

if ($requestMethod === 'POST' && ($_POST['action'] ?? '') === 'request_extension') {
    $id = (int)($_POST['id'] ?? 0);
    $days = (int)($_POST['extension_days'] ?? 0);
    $reason = trim($_POST['extension_reason'] ?? '');
    if ($id < 1 || $days < 1) {
        http_response_code(422);
        exit('กรุณาระบุจำนวนวันที่ต้องการขอต่อเวลาอย่างน้อย 1 วัน');
    }

    if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'borrower') {
        $uName = trim($_SESSION['full_name'] ?? '');
        $uUser = trim($_SESSION['username'] ?? '');
        $uPhone = trim($_SESSION['phone'] ?? '');
        $chk = $conn->prepare("SELECT id FROM transactions WHERE id = ? AND (borrower_username = ? OR TRIM(borrower_name) = ? OR REPLACE(TRIM(borrower_name), ' ', '') = REPLACE(?, ' ', '') OR (borrower_phone != '' AND borrower_phone = ?))");
        $chk->bind_param('issss', $id, $uUser, $uName, $uName, $uPhone);
        $chk->execute();
        $chkRes = $chk->get_result();
        if (!$chkRes || $chkRes->num_rows === 0) {
            http_response_code(403);
            exit('คุณสามารถขอต่อเวลาได้เฉพาะรายการของตนเองเท่านั้น');
        }
        $chk->close();
    }
    $stmt = $conn->prepare("UPDATE transactions SET extension_status = 'pending', extension_days = ?, extension_reason = ?, extension_request_time = NOW() WHERE id = ? AND return_time IS NULL");
    $stmt->bind_param('isi', $days, $reason, $id);
    if ($stmt->execute() && $stmt->affected_rows >= 0) {
        exit("ส่งคำขอต่อเวลา ($days วัน) เรียบร้อยแล้ว กรุณารอเจ้าหน้าที่ตรวจสอบอนุมัติ");
    }
    http_response_code(500);
    exit('ไม่สามารถส่งคำขอต่อเวลาได้');
}

if ($requestMethod === 'POST' && ($_POST['action'] ?? '') === 'approve_extension') {
    $id = (int)($_POST['id'] ?? 0);
    $decision = $_POST['decision'] ?? ''; // 'approve' or 'reject'
    if ($id < 1 || !in_array($decision, ['approve', 'reject'], true)) {
        http_response_code(422);
        exit('คำขอไม่ถูกต้อง');
    }
    if ($decision === 'approve') {
        $q = $conn->query("SELECT borrow_duration, extension_days FROM transactions WHERE id = $id AND return_time IS NULL");
        if ($q && ($row = $q->fetch_assoc())) {
            $curDays = (int)$row['borrow_duration'];
            if ($curDays < 1) $curDays = 7;
            $extDays = (int)$row['extension_days'];
            $newDays = $curDays + ($extDays > 0 ? $extDays : 1);
            $newDaysStr = (string)$newDays;
            $stmt = $conn->prepare("UPDATE transactions SET borrow_duration = ?, extension_status = 'approved' WHERE id = ?");
            $stmt->bind_param('si', $newDaysStr, $id);
            if ($stmt->execute()) exit("อนุมัติต่อเวลาเพิ่ม $extDays วัน เรียบร้อยแล้ว (รวมเป็น $newDays วัน)");
        }
        http_response_code(404);
        exit('ไม่พบรายการหรือรายการนี้คืนอุปกรณ์แล้ว');
    } else {
        $stmt = $conn->prepare("UPDATE transactions SET extension_status = 'rejected' WHERE id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) exit('ปฏิเสธคำขอต่อเวลาเรียบร้อยแล้ว');
    }
    http_response_code(500);
    exit('เกิดข้อผิดพลาดในการประมวลผล');
}

if ($requestMethod === 'POST' && ($_POST['action'] ?? '') === 'cleanup_orphans') {
    $cleaned = cleanup_orphaned_images($conn);
    exit("ล้างไฟล์รูปภาพตกค้างในระบบเรียบร้อยแล้ว ($cleaned ไฟล์)");
}

if ($requestMethod === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) { http_response_code(422); exit('รหัสรายการไม่ถูกต้อง'); }
    $result = $conn->query("SELECT image_path, return_image_path, borrower_image_path FROM transactions WHERE id = $id");
    if ($result && ($row = $result->fetch_assoc())) {
        delete_image_paths($row['image_path']);
        delete_image_paths($row['return_image_path']);
        delete_image_paths($row['borrower_image_path'] ?? '');
    }
    if ($conn->query("DELETE FROM transactions WHERE id = $id")) {
        resequence_ids($conn);
        cleanup_orphaned_images($conn);
        exit('ลบรายการและไฟล์รูปภาพเรียบร้อยแล้ว');
    }
    exit('ลบรายการไม่สำเร็จ');
}

if ($requestMethod === 'POST' && ($_POST['action'] ?? '') === 'delete_batch') {
    $raw_ids = $_POST['ids'] ?? [];
    if (is_string($raw_ids)) $raw_ids = explode(',', $raw_ids);
    $ids = array_values(array_filter(array_map('intval', (array)$raw_ids), fn($v) => $v > 0));
    if (empty($ids)) { http_response_code(422); exit('กรุณาเลือกรายการที่ต้องการลบอย่างน้อย 1 รายการ'); }
    $id_list = implode(',', $ids);
    $result = $conn->query("SELECT image_path, return_image_path, borrower_image_path FROM transactions WHERE id IN ($id_list)");
    while ($result && ($row = $result->fetch_assoc())) {
        delete_image_paths($row['image_path']);
        delete_image_paths($row['return_image_path']);
        delete_image_paths($row['borrower_image_path'] ?? '');
    }
    $count = count($ids);
    if ($conn->query("DELETE FROM transactions WHERE id IN ($id_list)")) {
        resequence_ids($conn);
        cleanup_orphaned_images($conn);
        exit("ลบรายการที่เลือกและไฟล์รูปภาพเรียบร้อยแล้ว ($count รายการ)");
    }
    exit('ลบรายการไม่สำเร็จ');
}

if ($requestMethod === 'POST') {
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
        $department = trim($_POST['department'] ?? ($_SESSION['dept_name'] ?? 'แผนกโครงข่ายสื่อสารหลัก'));
        if ($department === '') $department = 'แผนกโครงข่ายสื่อสารหลัก';
        $borrow_paths = implode(',', $saved_paths);
        $status = 'pending';

        // จัดการบันทึกรูปถ่ายผู้ยืม (1 รูป)
        $borrower_image = $_POST['borrower_image'] ?? '';
        $borrower_image_path = '';
        if (!empty($borrower_image)) {
            $saved_borrower = save_images([$borrower_image]);
            if (!empty($saved_borrower)) {
                $borrower_image_path = $saved_borrower[0];
            }
        }

        // คำนวณลำดับประจำแผนก (dept_seq)
        $seqStmt = $conn->prepare("SELECT COALESCE(MAX(dept_seq), 0) + 1 AS next_seq FROM transactions WHERE department = ?");
        $seqStmt->bind_param('s', $department);
        $seqStmt->execute();
        $seqRes = $seqStmt->get_result();
        $dept_seq = 1;
        if ($seqRes && ($sRow = $seqRes->fetch_assoc())) {
            $dept_seq = (int)$sRow['next_seq'];
            if ($dept_seq < 1) $dept_seq = 1;
        }
        $seqStmt->close();

        // ค้นหาหรือบันทึก username ของผู้ยืม
        $borrower_username = trim($_POST['borrower_username'] ?? '');
        if ($borrower_username === '') {
            $uLookup = $conn->prepare("SELECT username FROM department_users WHERE (TRIM(full_name) = ? OR (phone != '' AND phone = ?)) LIMIT 1");
            $uLookup->bind_param('ss', $borrower, $phone);
            $uLookup->execute();
            $uLookupRes = $uLookup->get_result();
            if ($uLookupRes && ($uRow = $uLookupRes->fetch_assoc())) {
                $borrower_username = $uRow['username'];
            }
            $uLookup->close();
        }

        $stmt = $conn->prepare("INSERT INTO transactions (borrower_name, borrower_username, item_name, department, dept_seq, sub_item_name, serial_number, borrower_phone, borrow_duration, lender_name, action_type, image_path, condition_status, borrower_image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Borrow', ?, ?, ?)");
        $stmt->bind_param('ssssissssssss', $borrower, $borrower_username, $item, $department, $dept_seq, $sub_item, $serial_number, $phone, $duration, $lender, $borrow_paths, $status, $borrower_image_path);
        if ($stmt->execute()) exit('บันทึกการยืมและรูปภาพเรียบร้อยแล้ว');
    }
    foreach ($saved_paths as $path) delete_image_paths($path);
    http_response_code(500); exit('บันทึกข้อมูลไม่สำเร็จ: ' . $conn->error);
}
if ($requestMethod === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] === 'cleanup_orphans') {
        $cleaned = cleanup_orphaned_images($conn);
        exit("ล้างไฟล์รูปภาพตกค้างในระบบเรียบร้อยแล้ว ($cleaned ไฟล์)");
    }
    if ($_GET['action'] === 'delete' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $result = $conn->query("SELECT image_path, return_image_path, borrower_image_path FROM transactions WHERE id = $id");
        if ($result && ($row = $result->fetch_assoc())) {
            delete_image_paths($row['image_path']);
            delete_image_paths($row['return_image_path']);
            delete_image_paths($row['borrower_image_path'] ?? '');
        }
        if ($conn->query("DELETE FROM transactions WHERE id = $id")) {
            resequence_ids($conn);
            cleanup_orphaned_images($conn);
            exit('ลบรายการและไฟล์รูปภาพเรียบร้อยแล้ว');
        }
        exit('ลบรายการไม่สำเร็จ');
    }
    if ($_GET['action'] === 'delete_batch' && isset($_GET['ids'])) {
        $ids = array_values(array_filter(array_map('intval', explode(',', (string)$_GET['ids'])), fn($v) => $v > 0));
        if (empty($ids)) { http_response_code(422); exit('กรุณาเลือกรายการที่ต้องการลบอย่างน้อย 1 รายการ'); }
        $id_list = implode(',', $ids);
        $result = $conn->query("SELECT image_path, return_image_path, borrower_image_path FROM transactions WHERE id IN ($id_list)");
        while ($result && ($row = $result->fetch_assoc())) {
            delete_image_paths($row['image_path']);
            delete_image_paths($row['return_image_path']);
            delete_image_paths($row['borrower_image_path'] ?? '');
        }
        $count = count($ids);
        if ($conn->query("DELETE FROM transactions WHERE id IN ($id_list)")) {
            resequence_ids($conn);
            cleanup_orphaned_images($conn);
            exit("ลบรายการที่เลือกและไฟล์รูปภาพเรียบร้อยแล้ว ($count รายการ)");
        }
        exit('ลบรายการไม่สำเร็จ');
    }
    if ($_GET['action'] === 'delete_all') {
        $result = $conn->query('SELECT image_path, return_image_path, borrower_image_path FROM transactions');
        while ($result && ($row = $result->fetch_assoc())) {
            delete_image_paths($row['image_path']);
            delete_image_paths($row['return_image_path']);
            delete_image_paths($row['borrower_image_path'] ?? '');
        }
        if ($conn->query('DELETE FROM transactions')) {
            $conn->query('ALTER TABLE transactions AUTO_INCREMENT = 1');
            cleanup_orphaned_images($conn);
            exit('ลบประวัติและไฟล์รูปภาพทั้งหมดเรียบร้อยแล้ว');
        }
        exit('ลบประวัติทั้งหมดไม่สำเร็จ');
    }
}
if ($requestMethod !== '') {
    http_response_code(400); echo 'รูปแบบคำขอไม่ถูกต้อง';
}
