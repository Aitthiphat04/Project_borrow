<?php
// auth.php - ระบบจัดการสิทธิ์ แผนก และบทบาทผู้ใช้งาน PEA ITEMFLOW
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

// รายชื่อ 3 แผนกมาตรฐาน
const DEPARTMENTS = [
    'core_network' => [
        'name' => 'แผนกโครงข่ายสื่อสารหลัก',
        'code' => 'CN'
    ],
    'power_telecom' => [
        'name' => 'แผนกระบบสื่อสารสนับสนุนงานระบบไฟฟ้า',
        'code' => 'PT'
    ],
    'corporate_comm' => [
        'name' => 'แผนกเทคโนโลยีการสื่อสารองค์กร',
        'code' => 'CC'
    ]
];

/**
 * แปลงรหัสบทบาทเป็นชื่อภาษาไทยทางการ
 */
function role_thai_name($role) {
    $map = [
        'borrower' => 'ผู้ยืมอุปกรณ์',
        'lender' => 'ผู้ให้ยืมอุปกรณ์',
        'admin' => 'ผู้ดูแลระบบ',
        'super_admin' => 'ผู้ดูแลระบบส่วนกลาง'
    ];
    return $map[$role] ?? 'ผู้ใช้งาน';
}

/**
 * ดึงรายชื่อผู้ใช้งานทั้งหมด จัดกลุ่มตาม dept_key เพื่อใช้ในหน้าล็อกอิน
 */
function get_all_users_grouped($conn) {
    $result = [
        'all' => [],
        'by_dept' => [
            'core_network' => [],
            'power_telecom' => [],
            'corporate_comm' => [],
            'super_admin' => []
        ]
    ];

    $res = $conn->query("SELECT id, username, full_name, dept_key, dept_name, role FROM department_users ORDER BY role ASC, id ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $deptKey = $row['dept_key'] === 'all' ? 'super_admin' : $row['dept_key'];
            $userData = [
                'id' => (int)$row['id'],
                'username' => $row['username'],
                'full_name' => $row['full_name'],
                'dept_key' => $row['dept_key'],
                'dept_name' => $row['dept_name'],
                'role' => $row['role'],
                'role_title' => role_thai_name($row['role'])
            ];
            $result['all'][] = $userData;
            if (isset($result['by_dept'][$deptKey])) {
                $result['by_dept'][$deptKey][] = $userData;
            } else {
                $result['by_dept'][$deptKey] = [$userData];
            }
        }
    }
    return $result;
}

// ตรวจสอบและสร้างคอลัมน์ phone ใน department_users หากยังไม่มี
if (isset($conn) && $conn instanceof mysqli) {
    $chkPhoneCol = $conn->query("SHOW COLUMNS FROM department_users LIKE 'phone'");
    if ($chkPhoneCol && !$chkPhoneCol->num_rows) {
        @$conn->query("ALTER TABLE department_users ADD COLUMN `phone` VARCHAR(30) NULL AFTER `full_name`");
    }
}

/**
 * สมัครสมาชิกลงทะเบียนผู้ยืมอุปกรณ์ (Register Borrower)
 */
function register_borrower($conn, $dept_key, $full_name, $username, $password, $phone = '') {
    $full_name = trim((string)$full_name);
    $username = trim((string)$username);
    $password = (string)$password;
    $phone = trim((string)$phone);
    $dept_key = trim((string)$dept_key);

    if (empty($full_name)) {
        return ['success' => false, 'error' => 'กรุณากรอกชื่อ-นามสกุล'];
    }
    if (empty($dept_key) || !isset(DEPARTMENTS[$dept_key])) {
        return ['success' => false, 'error' => 'กรุณาเลือกสังกัดแผนกที่ถูกต้อง'];
    }
    if (empty($username)) {
        return ['success' => false, 'error' => 'กรุณากรอกชื่อผู้ใช้งานหรือรหัสพนักงาน'];
    }
    if (mb_strlen($username) < 3 || mb_strlen($username) > 40) {
        return ['success' => false, 'error' => 'ชื่อผู้ใช้งานต้องมีความยาวระหว่าง 3-40 ตัวอักษร'];
    }
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $username)) {
        return ['success' => false, 'error' => 'ชื่อผู้ใช้งานสามารถใช้ได้เฉพาะตัวอักษรภาษาอังกฤษ ตัวเลข ขีดล่าง (_) ขีดกลาง (-) หรือจุด (.)'];
    }
    if (empty($password)) {
        return ['success' => false, 'error' => 'กรุณากรอกรหัสผ่าน'];
    }
    if (mb_strlen($password) < 4) {
        return ['success' => false, 'error' => 'รหัสผ่านต้องมีความยาวอย่างน้อย 4 ตัวอักษร'];
    }

    $dept_name = DEPARTMENTS[$dept_key]['name'];

    // ตรวจสอบชื่อผู้ใช้ซ้ำ
    $stmt = $conn->prepare("SELECT id FROM department_users WHERE username = ? LIMIT 1");
    if (!$stmt) {
        return ['success' => false, 'error' => 'ข้อผิดพลาดระบบฐานข้อมูล'];
    }
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        return ['success' => false, 'error' => 'ชื่อผู้ใช้งานนี้มีอยู่ในระบบแล้ว กรุณาใช้ชื่ออื่นหรือรหัสพนักงานของคุณ'];
    }

    // แฮชรหัสผ่าน
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // ตรวจสอบคอลัมน์ phone
    $chkPhone = $conn->query("SHOW COLUMNS FROM department_users LIKE 'phone'");
    $hasPhone = ($chkPhone && $chkPhone->num_rows > 0);

    if ($hasPhone) {
        $ins = $conn->prepare("INSERT INTO department_users (username, full_name, phone, password, dept_key, dept_name, role) VALUES (?, ?, ?, ?, ?, ?, 'borrower')");
        $ins->bind_param('ssssss', $username, $full_name, $phone, $hashedPassword, $dept_key, $dept_name);
    } else {
        $ins = $conn->prepare("INSERT INTO department_users (username, full_name, password, dept_key, dept_name, role) VALUES (?, ?, ?, ?, ?, 'borrower')");
        $ins->bind_param('sssss', $username, $full_name, $hashedPassword, $dept_key, $dept_name);
    }

    if ($ins && $ins->execute()) {
        $newId = (int)$ins->insert_id;
        $user = [
            'id' => $newId,
            'username' => $username,
            'full_name' => $full_name,
            'phone' => $phone,
            'dept_key' => $dept_key,
            'dept_name' => $dept_name,
            'role' => 'borrower',
            'role_title' => role_thai_name('borrower')
        ];

        return [
            'success' => true,
            'user' => $user
        ];
    }

    return ['success' => false, 'error' => 'ไม่สามารถบันทึกข้อมูลการลงทะเบียนได้ กรุณาลองใหม่อีกครั้ง'];
}

/**
 * ตรวจสอบชื่อผู้ใช้ รหัสผ่าน และแผนก เพื่อเข้าสู่ระบบ
 */
function login_user($conn, $dept_key, $username, $password) {
    if (empty($dept_key) || empty($username) || empty($password)) {
        return ['success' => false, 'error' => 'กรุณากรอกแผนก ชื่อผู้ใช้งาน และรหัสผ่านให้ครบถ้วน'];
    }

    $stmt = $conn->prepare("SELECT id, username, full_name, phone, password, dept_key, dept_name, role FROM department_users WHERE username = ? LIMIT 1");
    if (!$stmt) {
        // กรณีไม่มี phone ใน schema เก่า
        $stmt = $conn->prepare("SELECT id, username, full_name, password, dept_key, dept_name, role FROM department_users WHERE username = ? LIMIT 1");
    }
    if (!$stmt) {
        return ['success' => false, 'error' => 'ข้อผิดพลาดระบบฐานข้อมูล'];
    }
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && ($user = $res->fetch_assoc())) {
        // ตรวจสอบแผนก (ยกเว้น super_admin ที่เข้าได้ทุกแผนก)
        if ($user['role'] !== 'super_admin' && $user['dept_key'] !== $dept_key) {
            return ['success' => false, 'error' => 'ชื่อผู้ใช้งานนี้ไม่ได้สังกัดในแผนกที่เลือก กรุณาตรวจสอบแผนก'];
        }

        // ตรวจสอบรหัสผ่าน (รองรับ password_verify และ plaintext 1234)
        $hashOrPass = $user['password'];
        $match = password_verify($password, $hashOrPass) || ($password === $hashOrPass);

        if ($match) {
            $_SESSION['dept_logged_in'] = true;
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['phone'] = $user['phone'] ?? '';
            $_SESSION['dept_key'] = $user['dept_key'];
            $_SESSION['dept_name'] = $user['dept_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['is_super_admin'] = ($user['role'] === 'super_admin');

            // กำหนดหน้าปลายทางตามบทบาทที่ถูกกำหนดไว้
            $targetUrl = 'borrower.php';
            if ($user['role'] === 'lender') {
                $targetUrl = 'lender.php';
            } elseif ($user['role'] === 'admin' || $user['role'] === 'super_admin') {
                $targetUrl = 'history.php';
            }

            return [
                'success' => true,
                'user' => $user,
                'target_url' => $targetUrl
            ];
        }
    }

    return ['success' => false, 'error' => 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง'];
}

/**
 * ออกจากระบบ
 */
function logout_user() {
    unset(
        $_SESSION['dept_logged_in'],
        $_SESSION['user_id'],
        $_SESSION['username'],
        $_SESSION['full_name'],
        $_SESSION['phone'],
        $_SESSION['dept_key'],
        $_SESSION['dept_name'],
        $_SESSION['user_role'],
        $_SESSION['is_super_admin']
    );
}

/**
 * ดึงข้อมูลผู้ใช้งานที่กำลังล็อกอินอยู่
 */
function current_user() {
    return [
        'is_logged_in' => !empty($_SESSION['dept_logged_in']),
        'id' => $_SESSION['user_id'] ?? 0,
        'username' => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'phone' => $_SESSION['phone'] ?? '',
        'dept_key' => $_SESSION['dept_key'] ?? '',
        'dept_name' => $_SESSION['dept_name'] ?? '',
        'name' => $_SESSION['dept_name'] ?? '',
        'role' => $_SESSION['user_role'] ?? '',
        'role_title' => role_thai_name($_SESSION['user_role'] ?? ''),
        'is_super' => !empty($_SESSION['is_super_admin'])
    ];
}

/**
 * ฟังก์ชันสำหรับความเข้ากันได้ย้อนหลัง (Backward Compatibility)
 */
function current_department() {
    $u = current_user();
    return [
        'key' => $u['dept_key'],
        'name' => $u['dept_name'],
        'is_super' => $u['is_super'],
        'full_name' => $u['full_name'],
        'role' => $u['role']
    ];
}

function require_department_auth() {
    require_auth_and_role();
}

/**
 * ตรวจสอบการล็อกอินและจำกัดสิทธิ์ตามบทบาท (Role Permission Guard)
 */
function require_auth_and_role($allowedRoles = []) {
    if (empty($_SESSION['dept_logged_in']) || empty($_SESSION['user_role'])) {
        header('Location: login.php');
        exit;
    }

    $currentRole = $_SESSION['user_role'];
    if ($currentRole === 'super_admin') {
        return; // Super Admin เข้าได้ทุกหน้า
    }

    if (!empty($allowedRoles) && !in_array($currentRole, $allowedRoles, true)) {
        // หากผู้ใช้ไม่มีสิทธิ์เข้าหน้านี้ ให้พาไปหน้าที่ตรงกับบทบาทของตนเอง
        if ($currentRole === 'borrower') {
            header('Location: borrower.php');
        } elseif ($currentRole === 'lender') {
            header('Location: lender.php');
        } elseif ($currentRole === 'admin') {
            header('Location: history.php');
        } else {
            header('Location: login.php');
        }
        exit;
    }
}

/**
 * สร้าง SQL Filter WHERE clause ให้เข้าถึงเฉพาะแผนกตนเอง
 */
function get_department_sql_condition($conn, $tablePrefix = '') {
    $user = current_user();
    $prefix = $tablePrefix ? $tablePrefix . '.' : '';

    if ($user['is_super']) {
        // แอดมินส่วนกลางสามารถเลือกดูแผนกใดก็ได้ หรือดูทั้งหมด
        $selectedDept = $_GET['dept_filter'] ?? ($_SESSION['super_admin_view_dept'] ?? 'all');
        if (!empty($_GET['dept_filter'])) {
            $_SESSION['super_admin_view_dept'] = $_GET['dept_filter'];
        }
        if ($selectedDept !== 'all' && !empty($selectedDept)) {
            return " AND " . $prefix . "department = '" . $conn->real_escape_string($selectedDept) . "' ";
        }
        return ""; // ดูทุกแผนก
    }

    // ล็อกให้เห็นเฉพาะแผนกตนเองเท่านั้น
    return " AND " . $prefix . "department = '" . $conn->real_escape_string($user['dept_name']) . "' ";
}
