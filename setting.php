<?php
// setting.php - หน้าตั้งค่าระบบ ข้อมูลส่วนตัว และการแสดงผล PEA ITEMFLOW
require_once __DIR__ . '/auth.php';

// บังคับล็อกอินก่อนเข้าหน้าตั้งค่า
require_auth_and_role();
$currentUser = current_user();

$msgSuccess = '';
$msgError = '';
$canManageLenders = in_array($currentUser['role'], ['admin', 'super_admin'], true);
$activeTab = $_GET['tab'] ?? 'tab-profile';
if (!in_array($activeTab, ['tab-profile', 'tab-lenders', 'tab-borrowers', 'tab-display', 'tab-security'], true)) {
    $activeTab = 'tab-profile';
}
if (in_array($activeTab, ['tab-lenders', 'tab-borrowers'], true) && !$canManageLenders) {
    $activeTab = 'tab-profile';
}

// จัดการเมื่อมีการส่งฟอร์มบันทึกข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $activeTab = 'tab-profile';
        $oldFullName = trim((string)($currentUser['full_name'] ?? ''));
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $phone = preg_replace('/\D/', '', trim((string)($_POST['phone'] ?? '')));

        if (empty($fullName)) {
            $msgError = 'กรุณากรอกชื่อ-นามสกุล';
        } elseif (empty($phone) || strlen($phone) !== 10) {
            $msgError = 'กรุณากรอกเบอร์โทรศัพท์เป็นตัวเลข 10 หลัก';
        } else {
            $userId = (int)$currentUser['id'];
            $stmt = $conn->prepare("UPDATE department_users SET full_name = ?, phone = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("ssi", $fullName, $phone, $userId);
                if ($stmt->execute()) {
                    // หากผู้ใช้เป็น lender และเปลี่ยนชื่อตัวเอง ให้อัปเดตชื่อผู้ให้ยืมในรายการของแผนกด้วย
                    if ($currentUser['role'] === 'lender' && $oldFullName !== '' && $oldFullName !== $fullName) {
                        $stmtSync = $conn->prepare("UPDATE transactions SET lender_name = ? WHERE department = ? AND lender_name = ?");
                        if ($stmtSync) {
                            $deptName = $currentUser['dept_name'];
                            $stmtSync->bind_param("sss", $fullName, $deptName, $oldFullName);
                            $stmtSync->execute();
                            $stmtSync->close();
                        }
                    }
                    $_SESSION['full_name'] = $fullName;
                    $_SESSION['phone'] = $phone;
                    $currentUser['full_name'] = $fullName;
                    $currentUser['phone'] = $phone;
                    $msgSuccess = 'บันทึกข้อมูลส่วนตัวเรียบร้อยแล้ว';
                } else {
                    $msgError = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $conn->error;
                }
                $stmt->close();
            }
        }
    } elseif ($action === 'update_lender' && $canManageLenders) {
        $activeTab = 'tab-lenders';
        $lenderId = (int)($_POST['lender_id'] ?? 0);
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $phone = preg_replace('/\D/', '', trim((string)($_POST['phone'] ?? '')));
        $newPass = trim((string)($_POST['new_password'] ?? ''));
        $syncTransactions = !empty($_POST['sync_transactions']);

        if ($lenderId < 1) {
            $msgError = 'ไม่พบรหัสผู้ให้ยืมที่ต้องการแก้ไข';
        } elseif (empty($fullName)) {
            $msgError = 'กรุณากรอกชื่อ-นามสกุลของผู้ให้ยืม';
        } elseif (empty($phone) || strlen($phone) !== 10) {
            $msgError = 'กรุณากรอกเบอร์โทรศัพท์ผู้ให้ยืมเป็นตัวเลข 10 หลัก';
        } elseif ($newPass !== '' && strlen($newPass) < 4) {
            $msgError = 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 4 ตัวอักษร';
        } else {
            $chk = $conn->prepare("SELECT id, username, full_name, dept_key, dept_name FROM department_users WHERE id = ? AND role = 'lender' LIMIT 1");
            $chk->bind_param("i", $lenderId);
            $chk->execute();
            $lenderRow = $chk->get_result()->fetch_assoc();
            $chk->close();

            if (!$lenderRow) {
                $msgError = 'ไม่พบบัญชีผู้ให้ยืมในระบบ';
            } elseif (!$currentUser['is_super'] && $lenderRow['dept_key'] !== $currentUser['dept_key']) {
                $msgError = 'คุณสามารถเปลี่ยนผู้ให้ยืมได้เฉพาะภายในแผนกของคุณเท่านั้น';
            } else {
                $oldLenderName = trim((string)$lenderRow['full_name']);
                $targetDeptName = $lenderRow['dept_name'];

                if ($newPass !== '') {
                    $hashedPass = password_hash($newPass, PASSWORD_DEFAULT);
                    $upd = $conn->prepare("UPDATE department_users SET full_name = ?, phone = ?, password = ? WHERE id = ? AND role = 'lender'");
                    $upd->bind_param("sssi", $fullName, $phone, $hashedPass, $lenderId);
                } else {
                    $upd = $conn->prepare("UPDATE department_users SET full_name = ?, phone = ? WHERE id = ? AND role = 'lender'");
                    $upd->bind_param("ssi", $fullName, $phone, $lenderId);
                }

                if ($upd && $upd->execute()) {
                    $upd->close();
                    $syncedCount = 0;
                    if ($syncTransactions && $oldLenderName !== '' && $oldLenderName !== $fullName) {
                        $syncStmt = $conn->prepare("UPDATE transactions SET lender_name = ? WHERE department = ? AND lender_name = ?");
                        if ($syncStmt) {
                            $syncStmt->bind_param("sss", $fullName, $targetDeptName, $oldLenderName);
                            $syncStmt->execute();
                            $syncedCount = (int)$syncStmt->affected_rows;
                            $syncStmt->close();
                        }
                    }
                    $extraMsg = $syncedCount > 0 ? " (อัปเดตชื่อในประวัติการยืม $syncedCount รายการ)" : "";
                    $msgSuccess = 'เปลี่ยนข้อมูลผู้ให้ยืมประจำแผนกเป็น "' . $fullName . '" เรียบร้อยแล้ว' . $extraMsg;
                } else {
                    if ($upd) $upd->close();
                    $msgError = 'ไม่สามารถบันทึกข้อมูลผู้ให้ยืมได้';
                }
            }
        }
    } elseif ($action === 'add_lender' && $canManageLenders) {
        $activeTab = 'tab-lenders';
        $deptKey = $currentUser['is_super'] ? trim((string)($_POST['dept_key'] ?? '')) : $currentUser['dept_key'];
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $phone = preg_replace('/\D/', '', trim((string)($_POST['phone'] ?? '')));
        $username = trim((string)($_POST['username'] ?? ''));
        $password = trim((string)($_POST['password'] ?? '1234'));

        if (!isset(DEPARTMENTS[$deptKey])) {
            $msgError = 'กรุณาเลือกแผนกที่ถูกต้อง';
        } elseif (empty($fullName)) {
            $msgError = 'กรุณากรอกชื่อ-นามสกุลผู้ให้ยืมใหม่';
        } elseif (empty($phone) || strlen($phone) !== 10) {
            $msgError = 'กรุณากรอกเบอร์โทรศัพท์เป็นตัวเลข 10 หลัก';
        } elseif (strlen($password) < 4) {
            $msgError = 'รหัสผ่านเริ่มต้นต้องมีอย่างน้อย 4 ตัวอักษร';
        } else {
            $deptName = DEPARTMENTS[$deptKey]['name'];
            $deptCode = strtolower(DEPARTMENTS[$deptKey]['code']);

            if ($username === '') {
                for ($i = 0; $i < 10; $i++) {
                    $candidate = 'lender_' . $deptCode . '_' . substr(bin2hex(random_bytes(2)), 0, 4);
                    $chkU = $conn->prepare("SELECT id FROM department_users WHERE username = ? LIMIT 1");
                    $chkU->bind_param("s", $candidate);
                    $chkU->execute();
                    if ($chkU->get_result()->num_rows === 0) {
                        $username = $candidate;
                        $chkU->close();
                        break;
                    }
                    $chkU->close();
                }
            }

            if (!preg_match('/^[a-zA-Z0-9_\-\.]{3,40}$/', $username)) {
                $msgError = 'ชื่อผู้ใช้งาน (Username) ต้องเป็นภาษาอังกฤษหรือตัวเลข 3-40 ตัวอักษร';
            } else {
                $chkDup = $conn->prepare("SELECT id FROM department_users WHERE username = ? LIMIT 1");
                $chkDup->bind_param("s", $username);
                $chkDup->execute();
                $isDup = $chkDup->get_result()->num_rows > 0;
                $chkDup->close();

                if ($isDup) {
                    $msgError = 'ชื่อผู้ใช้งาน "' . $username . '" มีในระบบแล้ว กรุณาใช้ชื่ออื่น';
                } else {
                    $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                    $ins = $conn->prepare("INSERT INTO department_users (username, full_name, phone, password, dept_key, dept_name, role) VALUES (?, ?, ?, ?, ?, ?, 'lender')");
                    if ($ins) {
                        $ins->bind_param("ssssss", $username, $fullName, $phone, $hashedPass, $deptKey, $deptName);
                        if ($ins->execute()) {
                            $msgSuccess = 'เพิ่มผู้ให้ยืมใหม่ "' . $fullName . '" (' . $username . ') เรียบร้อยแล้ว';
                        } else {
                            $msgError = 'ไม่สามารถเพิ่มผู้ให้ยืมได้';
                        }
                        $ins->close();
                    }
                }
            }
        }
    } elseif ($action === 'delete_lender' && $canManageLenders) {
        $activeTab = 'tab-lenders';
        $lenderId = (int)($_POST['lender_id'] ?? 0);
        if ($lenderId > 0) {
            $chk = $conn->prepare("SELECT id, full_name, dept_key FROM department_users WHERE id = ? AND role = 'lender' LIMIT 1");
            $chk->bind_param("i", $lenderId);
            $chk->execute();
            $lenderRow = $chk->get_result()->fetch_assoc();
            $chk->close();

            if (!$lenderRow) {
                $msgError = 'ไม่พบบัญชีผู้ให้ยืมที่ต้องการลบ';
            } elseif (!$currentUser['is_super'] && $lenderRow['dept_key'] !== $currentUser['dept_key']) {
                $msgError = 'คุณไม่มีสิทธิ์ลบบัญชีของแผนกอื่น';
            } else {
                $cntStmt = $conn->prepare("SELECT COUNT(*) AS c FROM department_users WHERE dept_key = ? AND role = 'lender'");
                $cntStmt->bind_param("s", $lenderRow['dept_key']);
                $cntStmt->execute();
                $lenderCount = (int)$cntStmt->get_result()->fetch_assoc()['c'];
                $cntStmt->close();

                if ($lenderCount <= 1) {
                    $msgError = 'ไม่สามารถลบได้ เนื่องจากแต่ละแผนกต้องมีผู้ให้ยืมอย่างน้อย 1 คน (หากต้องการเปลี่ยนคน ให้แก้ไขชื่อ-นามสกุลแทน)';
                } else {
                    $del = $conn->prepare("DELETE FROM department_users WHERE id = ? AND role = 'lender'");
                    $del->bind_param("i", $lenderId);
                    if ($del->execute()) {
                        $msgSuccess = 'ลบบัญชีผู้ให้ยืม "' . $lenderRow['full_name'] . '" เรียบร้อยแล้ว';
                    } else {
                        $msgError = 'ไม่สามารถลบบัญชีผู้ให้ยืมได้';
                    }
                    $del->close();
                }
            }
        }
    } elseif ($action === 'update_borrower' && $canManageLenders) {
        $activeTab = 'tab-borrowers';
        $borrowerId = (int)($_POST['borrower_id'] ?? 0);
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $phone = preg_replace('/\D/', '', trim((string)($_POST['phone'] ?? '')));
        $newPass = trim((string)($_POST['new_password'] ?? ''));
        $syncTransactions = !empty($_POST['sync_transactions']);

        if ($borrowerId < 1) {
            $msgError = 'ไม่พบรหัสผู้ยืมที่ต้องการแก้ไข';
        } elseif (empty($fullName)) {
            $msgError = 'กรุณากรอกชื่อ-นามสกุลของผู้ยืม';
        } elseif (empty($phone) || strlen($phone) !== 10) {
            $msgError = 'กรุณากรอกเบอร์โทรศัพท์ผู้ยืมเป็นตัวเลข 10 หลัก';
        } elseif ($newPass !== '' && strlen($newPass) < 4) {
            $msgError = 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 4 ตัวอักษร';
        } else {
            $chk = $conn->prepare("SELECT id, username, full_name, phone, dept_key, dept_name FROM department_users WHERE id = ? AND role = 'borrower' LIMIT 1");
            $chk->bind_param("i", $borrowerId);
            $chk->execute();
            $bRow = $chk->get_result()->fetch_assoc();
            $chk->close();

            if (!$bRow) {
                $msgError = 'ไม่พบบัญชีผู้ยืมในระบบ';
            } elseif (!$currentUser['is_super'] && $bRow['dept_key'] !== $currentUser['dept_key']) {
                $msgError = 'คุณสามารถแก้ไขข้อมูลผู้ยืมได้เฉพาะภายในแผนกของคุณเท่านั้น';
            } else {
                // ตรวจสอบชื่อหรือเบอร์โทรซ้ำกับคนอื่นในแผนกเดียวกัน
                $dupStmt = $conn->prepare("SELECT id FROM department_users WHERE dept_key = ? AND role = 'borrower' AND id != ? AND (LOWER(TRIM(full_name)) = LOWER(?) OR phone = ?) LIMIT 1");
                $dupStmt->bind_param("siss", $bRow['dept_key'], $borrowerId, $fullName, $phone);
                $dupStmt->execute();
                $hasDup = $dupStmt->get_result()->num_rows > 0;
                $dupStmt->close();

                if ($hasDup) {
                    $msgError = 'ชื่อ-นามสกุล หรือเบอร์โทรศัพท์นี้ ซ้ำกับผู้ยืมคนอื่นในแผนกเดียวกัน';
                } else {
                    $oldName = trim((string)$bRow['full_name']);
                    $bUsername = trim((string)$bRow['username']);

                    if ($newPass !== '') {
                        $hashedPass = password_hash($newPass, PASSWORD_DEFAULT);
                        $upd = $conn->prepare("UPDATE department_users SET full_name = ?, phone = ?, password = ? WHERE id = ? AND role = 'borrower'");
                        $upd->bind_param("sssi", $fullName, $phone, $hashedPass, $borrowerId);
                    } else {
                        $upd = $conn->prepare("UPDATE department_users SET full_name = ?, phone = ? WHERE id = ? AND role = 'borrower'");
                        $upd->bind_param("ssi", $fullName, $phone, $borrowerId);
                    }

                    if ($upd && $upd->execute()) {
                        $upd->close();
                        $syncedCount = 0;
                        if ($syncTransactions) {
                            $syncStmt = $conn->prepare("UPDATE transactions SET borrower_name = ?, borrower_phone = ? WHERE (borrower_username != '' AND borrower_username = ?) OR TRIM(borrower_name) = ?");
                            if ($syncStmt) {
                                $syncStmt->bind_param("ssss", $fullName, $phone, $bUsername, $oldName);
                                $syncStmt->execute();
                                $syncedCount = (int)$syncStmt->affected_rows;
                                $syncStmt->close();
                            }
                        }
                        $extraMsg = $syncedCount > 0 ? " (อัปเดตข้อมูลในประวัติการยืม $syncedCount รายการ)" : "";
                        $msgSuccess = 'บันทึกการแก้ไขข้อมูลผู้ยืม "' . $fullName . '" เรียบร้อยแล้ว' . $extraMsg;
                    } else {
                        if ($upd) $upd->close();
                        $msgError = 'ไม่สามารถบันทึกข้อมูลผู้ยืมได้';
                    }
                }
            }
        }
    } elseif ($action === 'add_borrower' && $canManageLenders) {
        $activeTab = 'tab-borrowers';
        $deptKey = $currentUser['is_super'] ? trim((string)($_POST['dept_key'] ?? '')) : $currentUser['dept_key'];
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $phone = preg_replace('/\D/', '', trim((string)($_POST['phone'] ?? '')));
        $username = trim((string)($_POST['username'] ?? ''));
        $password = trim((string)($_POST['password'] ?? '1234'));

        $regRes = register_borrower($conn, $deptKey, $fullName, $username, $password, $phone);
        if ($regRes['success']) {
            $newU = $regRes['user'];
            $msgSuccess = 'เพิ่มบัญชีผู้ยืมใหม่ "' . $newU['full_name'] . '" (' . $newU['username'] . ') เรียบร้อยแล้ว';
        } else {
            $msgError = $regRes['error'] ?? 'ไม่สามารถเพิ่มผู้ยืมใหม่ได้';
        }
    } elseif ($action === 'delete_borrower' && $canManageLenders) {
        $activeTab = 'tab-borrowers';
        $borrowerId = (int)($_POST['borrower_id'] ?? 0);
        if ($borrowerId > 0) {
            $chk = $conn->prepare("SELECT id, username, full_name, dept_key FROM department_users WHERE id = ? AND role = 'borrower' LIMIT 1");
            $chk->bind_param("i", $borrowerId);
            $chk->execute();
            $bRow = $chk->get_result()->fetch_assoc();
            $chk->close();

            if (!$bRow) {
                $msgError = 'ไม่พบบัญชีผู้ยืมที่ต้องการลบ';
            } elseif (!$currentUser['is_super'] && $bRow['dept_key'] !== $currentUser['dept_key']) {
                $msgError = 'คุณไม่มีสิทธิ์ลบผู้ยืมของแผนกอื่น';
            } else {
                // กฎความปลอดภัย: ห้ามลบผู้ยืมที่ยังมีรายการยืมค้างอยู่ (return_time IS NULL)
                $bUsername = trim((string)$bRow['username']);
                $bFullName = trim((string)$bRow['full_name']);
                $activeStmt = $conn->prepare("SELECT COUNT(*) AS c FROM transactions WHERE action_type = 'Borrow' AND return_time IS NULL AND ((borrower_username != '' AND borrower_username = ?) OR TRIM(borrower_name) = ?)");
                $activeStmt->bind_param("ss", $bUsername, $bFullName);
                $activeStmt->execute();
                $activeBorrows = (int)$activeStmt->get_result()->fetch_assoc()['c'];
                $activeStmt->close();

                if ($activeBorrows > 0) {
                    $msgError = 'ไม่สามารถลบผู้ยืม "' . $bFullName . '" ได้ เนื่องจากยังมีอุปกรณ์ที่กำลังยืมค้างอยู่ ' . $activeBorrows . ' รายการ กรุณารับคืนอุปกรณ์ให้ครบก่อน';
                } else {
                    $del = $conn->prepare("DELETE FROM department_users WHERE id = ? AND role = 'borrower'");
                    $del->bind_param("i", $borrowerId);
                    if ($del->execute()) {
                        $msgSuccess = 'ลบบัญชีผู้ยืม "' . $bFullName . '" เรียบร้อยแล้ว (ประวัติการยืมในอดีตยังคงเก็บรักษาไว้ตามปกติ)';
                    } else {
                        $msgError = 'ไม่สามารถลบบัญชีผู้ยืมได้';
                    }
                    $del->close();
                }
            }
        }
    } elseif ($action === 'change_password') {
        $activeTab = 'tab-security';
        $currentPass = (string)($_POST['current_password'] ?? '');
        $newPass = (string)($_POST['new_password'] ?? '');
        $confirmPass = (string)($_POST['confirm_password'] ?? '');

        if (empty($currentPass) || empty($newPass)) {
            $msgError = 'กรุณากรอกรหัสผ่านให้ครบถ้วน';
        } elseif (strlen($newPass) < 4) {
            $msgError = 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 4 ตัวอักษร';
        } elseif ($newPass !== $confirmPass) {
            $msgError = 'รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน';
        } else {
            $userId = (int)$currentUser['id'];
            $res = $conn->query("SELECT password FROM department_users WHERE id = $userId LIMIT 1");
            if ($res && $row = $res->fetch_assoc()) {
                $stored = $row['password'];
                if (password_verify($currentPass, $stored) || $currentPass === $stored) {
                    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE department_users SET password = ? WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("si", $newHash, $userId);
                        if ($stmt->execute()) {
                            $msgSuccess = 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว';
                        } else {
                            $msgError = 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน';
                        }
                        $stmt->close();
                    }
                } else {
                    $msgError = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
                }
            }
        }
    }
}

// ดึงข้อมูลรายชื่อผู้ให้ยืม (Lenders) และผู้ยืม (Borrowers) สำหรับแอดมินและแอดมินส่วนกลาง
$departmentLenders = [];
$lenderCountsByDept = [];
$departmentBorrowers = [];
if ($canManageLenders) {
    if ($currentUser['is_super']) {
        $stmtLenders = $conn->prepare("SELECT id, username, full_name, phone, dept_key, dept_name FROM department_users WHERE role = 'lender' ORDER BY dept_key ASC, id ASC");
    } else {
        $stmtLenders = $conn->prepare("SELECT id, username, full_name, phone, dept_key, dept_name FROM department_users WHERE role = 'lender' AND dept_key = ? ORDER BY id ASC");
        $stmtLenders->bind_param("s", $currentUser['dept_key']);
    }
    if ($stmtLenders && $stmtLenders->execute()) {
        $resLenders = $stmtLenders->get_result();
        while ($lRow = $resLenders->fetch_assoc()) {
            $departmentLenders[] = $lRow;
            $dKey = $lRow['dept_key'];
            $lenderCountsByDept[$dKey] = ($lenderCountsByDept[$dKey] ?? 0) + 1;
        }
        $stmtLenders->close();
    }

    if ($currentUser['is_super']) {
        $stmtBorrowers = $conn->prepare("SELECT id, username, full_name, phone, dept_key, dept_name FROM department_users WHERE role = 'borrower' ORDER BY dept_key ASC, full_name ASC");
    } else {
        $stmtBorrowers = $conn->prepare("SELECT id, username, full_name, phone, dept_key, dept_name FROM department_users WHERE role = 'borrower' AND dept_key = ? ORDER BY full_name ASC");
        $stmtBorrowers->bind_param("s", $currentUser['dept_key']);
    }
    if ($stmtBorrowers && $stmtBorrowers->execute()) {
        $resBorrowers = $stmtBorrowers->get_result();
        $cntActiveStmt = $conn->prepare("SELECT COUNT(*) AS c FROM transactions WHERE action_type = 'Borrow' AND return_time IS NULL AND ((borrower_username != '' AND borrower_username = ?) OR TRIM(borrower_name) = ?)");
        while ($bRow = $resBorrowers->fetch_assoc()) {
            $activeCnt = 0;
            if ($cntActiveStmt) {
                $bU = trim((string)$bRow['username']);
                $bN = trim((string)$bRow['full_name']);
                $cntActiveStmt->bind_param("ss", $bU, $bN);
                $cntActiveStmt->execute();
                $activeCnt = (int)$cntActiveStmt->get_result()->fetch_assoc()['c'];
            }
            $bRow['active_borrows'] = $activeCnt;
            $departmentBorrowers[] = $bRow;
        }
        if ($cntActiveStmt) $cntActiveStmt->close();
        $stmtBorrowers->close();
    }
}

// ลิงก์ย้อนกลับตามบทบาท
$backUrl = 'index.php';
if ($currentUser['role'] === 'lender') {
    $backUrl = 'lender.php';
} elseif ($currentUser['role'] === 'borrower') {
    $backUrl = 'borrower.php';
} elseif ($currentUser['role'] === 'admin' || $currentUser['role'] === 'super_admin') {
    $backUrl = 'history.php';
}

$avatarChar = mb_substr($currentUser['full_name'] ?: $currentUser['username'] ?: 'U', 0, 1, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ITEMFLOW | ตั้งค่าระบบ</title>
    <script src="theme.js"></script>
    <style>
        :root {
            --purple: #4b2683;
            --deep: #250c50;
            --gold: #d9ac3b;
            --gold-light: #fae188;
            --line: #e5dcef;
            --red: #ca4358;
            --green: #168457;
            --shell-bg: #fffdfefa;
            --card-bg: #ffffff;
            --card-sub: #fbf9ff;
            --text-main: #2e2840;
            --text-muted: #6b5d82;
            --input-bg: #ffffff;
            --input-border: #d8cdea;
            --input-text: #2e2840;
            --input-readonly: #f4f0fa;
        }

        /* Dark Mode Theme */
        html[data-theme="dark"] {
            --purple: #7952b3;
            --deep: #160a2c;
            --gold: #f0c353;
            --gold-light: #fae8a6;
            --line: #3f315a;
            --shell-bg: #1e1333;
            --card-bg: #271a42;
            --card-sub: #201438;
            --text-main: #f5f0ff;
            --text-muted: #b9accf;
            --input-bg: #1c1230;
            --input-border: #4d3a6e;
            --input-text: #f8f6fc;
            --input-readonly: #281b43;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            background: radial-gradient(circle at 5% 10%, #f9e8ac9c, transparent 19rem),
                        radial-gradient(circle at 96% 87%, #4b268340, transparent 24rem),
                        linear-gradient(135deg, #eee8f8 0%, #faf6ff 48%, #fffaf0 100%);
            font-family: "Segoe UI", Tahoma, sans-serif;
            color: var(--text-main);
            padding: 16px 10px;
            transition: background 0.3s ease, color 0.3s ease;
        }

        html[data-theme="dark"] body {
            background: radial-gradient(circle at 5% 10%, #4b268350, transparent 22rem),
                        radial-gradient(circle at 96% 87%, #250c5070, transparent 24rem),
                        linear-gradient(135deg, #120924 0%, #1a0f30 48%, #140b26 100%);
        }

        main.shell {
            position: relative;
            overflow: hidden;
            max-width: 98%;
            width: min(1180px, 98%);
            margin: 14px auto;
            background: var(--shell-bg);
            border-radius: 22px;
            padding: 20px;
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 20px 50px rgba(52, 20, 87, 0.16);
            transition: background 0.3s ease, border-color 0.3s ease;
        }

        main.shell:before {
            content: "";
            position: absolute;
            width: 320px;
            height: 320px;
            top: -240px;
            right: -90px;
            border: 24px solid rgba(75, 38, 131, 0.04);
            border-radius: 50%;
            box-shadow: 0 0 0 24px rgba(75, 38, 131, 0.03), 0 0 0 48px rgba(217, 172, 59, 0.04);
            pointer-events: none;
        }

        .shell .top {
            position: relative;
            margin: -20px -20px 22px;
            padding: 18px 22px;
            background: linear-gradient(118deg, #250c50, #4b2683 58%, #7653a7);
            color: #fff;
            border-bottom: 5px solid var(--gold);
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .pea-logo {
            width: 70px;
            height: 70px;
            flex: none;
            object-fit: contain;
            border-radius: 50%;
            filter: drop-shadow(0 4px 7px rgba(19, 6, 42, 0.35));
        }

        .brand-name {
            font-size: .82rem;
            letter-spacing: .1em;
            color: #f8e9bb;
            font-weight: 800;
        }

        .brand-name b {
            display: inline-block;
            font-size: 1.25rem;
            letter-spacing: .06em;
            line-height: 1;
            margin-left: 8px;
            vertical-align: middle;
            text-shadow: 0 2px 8px rgba(24, 7, 49, 0.3);
            color: #fff;
        }

        .top h1 {
            margin: 4px 0 0;
            color: #fff;
            font-size: clamp(1.4rem, 2.5vw, 1.85rem);
            font-weight: 900;
            letter-spacing: .02em;
        }

        .shell .subtitle {
            margin: 4px 0 0;
            color: #eee5f9;
            font-size: .86rem;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-top {
            border: 1px solid rgba(255, 255, 255, 0.32);
            border-radius: 13px;
            padding: 11px 20px;
            font: inherit;
            font-size: .95rem;
            font-weight: 800;
            letter-spacing: .02em;
            cursor: pointer;
            color: #fff;
            background: rgba(255, 255, 255, 0.16);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            line-height: 1;
            transition: transform .18s, filter .18s, background .18s;
        }

        .btn-top:hover {
            background: rgba(255, 255, 255, 0.26);
            transform: translateY(-2px);
            filter: brightness(1.08);
        }

        /* Alerts */
        .alert-box {
            padding: 13px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: .92rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: space-between;
            animation: fadeIn .2s ease;
        }

        .alert-success {
            background: #eefbf4;
            border: 1px solid #b7ebd0;
            border-left: 5px solid var(--green);
            color: var(--green);
        }

        .alert-danger {
            background: #fff0f3;
            border: 1px solid #fecdd6;
            border-left: 5px solid var(--red);
            color: #a82038;
        }

        html[data-theme="dark"] .alert-success {
            background: #153326;
            border-color: #245740;
            color: #6ee7b7;
        }

        html[data-theme="dark"] .alert-danger {
            background: #3b141d;
            border-color: #662332;
            color: #fda4af;
        }

        .alert-close {
            background: transparent;
            border: none;
            color: inherit;
            font-size: 1.3rem;
            font-weight: 800;
            cursor: pointer;
            line-height: 1;
            padding: 0 4px;
        }

        /* Settings Grid Layout */
        .settings-layout {
            display: grid;
            grid-template-columns: 240px 1fr;
            gap: 20px;
            align-items: start;
        }

        @media (max-width: 820px) {
            .settings-layout {
                grid-template-columns: 1fr;
            }
        }

        /* Sidebar Nav Tabs */
        .settings-nav {
            background: var(--card-sub);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .nav-item {
            border: 0;
            background: transparent;
            color: var(--text-muted);
            font: inherit;
            font-size: .95rem;
            font-weight: 800;
            padding: 12px 16px;
            border-radius: 12px;
            text-align: left;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all .18s ease;
        }

        .nav-item:hover {
            background: rgba(75, 38, 131, 0.07);
            color: var(--purple);
        }

        .nav-item.active {
            background: linear-gradient(135deg, var(--deep), var(--purple));
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(75, 38, 131, 0.25);
        }

        /* Settings Card & Panes */
        .settings-card {
            background: var(--card-bg);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 26px;
            box-shadow: 0 8px 24px rgba(75, 38, 131, 0.05);
            transition: background 0.3s ease, border-color 0.3s ease;
        }

        .tab-pane {
            display: none;
            animation: fadeIn .2s ease;
        }

        .tab-pane.active {
            display: block;
        }

        .pane-header {
            margin-bottom: 22px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--line);
        }

        .pane-title {
            margin: 0 0 4px;
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-main);
        }

        .pane-desc {
            margin: 0;
            font-size: .86rem;
            color: var(--text-muted);
        }

        /* Profile Header */
        .profile-badge-row {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--line);
        }

        .avatar-box {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ede4f8, #dfd0f4);
            border: 3px solid var(--gold);
            color: var(--purple);
            font-size: 1.9rem;
            font-weight: 900;
            display: grid;
            place-items: center;
            flex: none;
            box-shadow: 0 4px 12px rgba(75, 38, 131, 0.15);
        }

        html[data-theme="dark"] .avatar-box {
            background: linear-gradient(135deg, #38245a, #4d337a);
            color: #fae8a6;
            border-color: var(--gold);
        }

        .profile-meta h3 {
            margin: 0 0 4px;
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--text-main);
        }

        .role-tag {
            display: inline-block;
            background: rgba(75, 38, 131, 0.1);
            border: 1px solid rgba(75, 38, 131, 0.2);
            color: var(--purple);
            padding: 3px 10px;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 800;
        }

        html[data-theme="dark"] .role-tag {
            background: rgba(240, 195, 83, 0.15);
            border-color: rgba(240, 195, 83, 0.35);
            color: var(--gold);
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px 20px;
            margin-bottom: 24px;
        }

        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-size: .88rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 6px;
        }

        .form-control {
            border: 1.5px solid var(--input-border);
            border-radius: 11px;
            padding: 10px 14px;
            font: inherit;
            font-size: .94rem;
            font-weight: 600;
            color: var(--input-text);
            background: var(--input-bg);
            transition: border-color .18s, box-shadow .18s, background .18s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(75, 38, 131, 0.18);
        }

        .form-control[readonly] {
            background: var(--input-readonly);
            cursor: not-allowed;
            color: var(--text-muted);
        }

        /* Primary Action Button */
        .btn-submit {
            border: 0;
            border-radius: 12px;
            padding: 12px 26px;
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
            color: #2a174d;
            font: inherit;
            font-size: .96rem;
            font-weight: 900;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(217, 172, 59, 0.28);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: transform .18s, filter .18s, box-shadow .18s;
        }

        .btn-submit:hover {
            filter: brightness(1.07);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(217, 172, 59, 0.4);
        }

        /* Theme Selector Cards (NO EMOJIS) */
        .theme-picker {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-top: 10px;
        }

        .theme-card {
            border: 2px solid var(--line);
            background: var(--card-sub);
            border-radius: 14px;
            padding: 16px;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            gap: 12px;
            transition: all .2s ease;
            position: relative;
        }

        .theme-card:hover {
            border-color: var(--purple);
            transform: translateY(-2px);
        }

        .theme-card.active {
            border-color: var(--gold);
            box-shadow: 0 6px 18px rgba(217, 172, 59, 0.22);
            background: rgba(217, 172, 59, 0.05);
        }

        .theme-preview {
            height: 90px;
            border-radius: 8px;
            border: 1px solid var(--line);
            padding: 8px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            overflow: hidden;
        }

        .preview-light {
            background: #fbf9fe;
        }

        .preview-light .mock-nav {
            height: 20px;
            border-radius: 4px;
            background: #4b2683;
            border-bottom: 2px solid #d9ac3b;
        }

        .preview-light .mock-line {
            height: 8px;
            border-radius: 3px;
            background: #e7def2;
            width: 70%;
        }

        .preview-light .mock-card {
            height: 36px;
            border-radius: 4px;
            background: #ffffff;
            border: 1px solid #e1d4ef;
        }

        .preview-dark {
            background: #170d29;
        }

        .preview-dark .mock-nav {
            height: 20px;
            border-radius: 4px;
            background: #250c50;
            border-bottom: 2px solid #d9ac3b;
        }

        .preview-dark .mock-line {
            height: 8px;
            border-radius: 3px;
            background: #443063;
            width: 70%;
        }

        .preview-dark .mock-card {
            height: 36px;
            border-radius: 4px;
            background: #25183f;
            border: 1px solid #3d285e;
        }

        .theme-label-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .theme-title {
            font-size: .95rem;
            font-weight: 800;
            color: var(--text-main);
        }

        .theme-check-indicator {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid var(--text-muted);
            display: grid;
            place-items: center;
        }

        .theme-card.active .theme-check-indicator {
            border-color: var(--gold);
            background: var(--gold);
        }

        .theme-card.active .theme-check-indicator:after {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #250c50;
        }

        /* Lender Management Cards */
        .lender-list {
            display: flex;
            flex-direction: column;
            gap: 18px;
            margin-bottom: 28px;
        }

        .lender-item-card {
            background: var(--card-sub);
            border: 1.5px solid var(--line);
            border-radius: 15px;
            padding: 20px;
            transition: border-color .2s, box-shadow .2s;
        }

        .lender-item-card:hover {
            border-color: rgba(75, 38, 131, 0.35);
        }

        .lender-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px dashed var(--line);
        }

        .lender-card-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .lender-avatar-sm {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--deep), var(--purple));
            color: #fae8a6;
            font-weight: 900;
            font-size: 1.15rem;
            display: grid;
            place-items: center;
            border: 2px solid var(--gold);
            flex: none;
        }

        .lender-card-name {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text-main);
        }

        .lender-card-sub {
            font-size: .82rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        .lender-dept-pill {
            background: rgba(75, 38, 131, 0.1);
            border: 1px solid rgba(75, 38, 131, 0.25);
            color: var(--purple);
            padding: 4px 12px;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 800;
        }

        html[data-theme="dark"] .lender-dept-pill {
            background: rgba(240, 195, 83, 0.15);
            border-color: rgba(240, 195, 83, 0.35);
            color: var(--gold);
        }

        .lender-sync-check {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: .86rem;
            font-weight: 700;
            color: var(--text-main);
            cursor: pointer;
            margin-bottom: 16px;
            user-select: none;
        }

        .lender-sync-check input[type="checkbox"] {
            width: 17px;
            height: 17px;
            accent-color: var(--purple);
            cursor: pointer;
        }

        .lender-actions-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-danger-outline {
            border: 1.5px solid var(--red);
            background: transparent;
            color: var(--red);
            border-radius: 10px;
            padding: 9px 16px;
            font: inherit;
            font-size: .86rem;
            font-weight: 800;
            cursor: pointer;
            transition: all .18s;
        }

        .btn-danger-outline:hover {
            background: var(--red);
            color: #fff;
        }

        .add-lender-box {
            background: var(--card-sub);
            border: 2px dashed var(--line);
            border-radius: 16px;
            padding: 22px;
            margin-top: 10px;
        }

        .add-lender-title {
            margin: 0 0 6px;
            font-size: 1.08rem;
            font-weight: 800;
            color: var(--text-main);
        }

        .add-lender-desc {
            margin: 0 0 18px;
            font-size: .84rem;
            color: var(--text-muted);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
    <link rel="stylesheet" href="theme.css?v=2">
</head>
<body>

<main class="shell">
    <div class="top">
        <div>
            <div class="brand">
                <img class="pea-logo" src="pea-logo.png" alt="ตราสัญลักษณ์ PEA" onerror="this.src='https://upload.wikimedia.org/wikipedia/th/thumb/a/a4/Provincial_Electricity_Authority_Logo.svg/800px-Provincial_Electricity_Authority_Logo.svg.png'">
                <div>
                    <div class="brand-name">PEA ASSET CARE <b>ITEMFLOW</b></div>
                    <h1><?= $canManageLenders ? 'ตั้งค่าระบบและจัดการผู้ใช้งาน' : 'ตั้งค่าบัญชีผู้ใช้งาน' ?></h1>
                    <p class="subtitle"><?= $canManageLenders ? 'จัดการข้อมูลส่วนตัว ผู้ให้ยืม ผู้ยืม และการแสดงผล' : 'จัดการข้อมูลส่วนตัว รหัสผ่าน และการแสดงผล' ?> (<?= htmlspecialchars($currentUser['dept_name']) ?>)</p>
                </div>
            </div>
        </div>
        <div class="actions">
            <a href="<?= htmlspecialchars($backUrl) ?>" class="btn-top" title="กลับหน้าหลัก">กลับหน้าหลัก</a>
        </div>
    </div>

    <?php if (!empty($msgSuccess)): ?>
        <div class="alert-box alert-success" id="alertSuccess">
            <span><?= htmlspecialchars($msgSuccess) ?></span>
            <button type="button" class="alert-close" onclick="document.getElementById('alertSuccess').remove()">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (!empty($msgError)): ?>
        <div class="alert-box alert-danger" id="alertError">
            <span><?= htmlspecialchars($msgError) ?></span>
            <button type="button" class="alert-close" onclick="document.getElementById('alertError').remove()">&times;</button>
        </div>
    <?php endif; ?>

    <div class="settings-layout">
        <!-- Sidebar Navigation Tabs (No Emojis) -->
        <div class="settings-nav">
            <button type="button" class="nav-item <?= $activeTab === 'tab-profile' ? 'active' : '' ?>" data-tab="tab-profile">
                <span>ข้อมูลส่วนตัว</span>
            </button>
            <?php if ($canManageLenders): ?>
                <button type="button" class="nav-item <?= $activeTab === 'tab-lenders' ? 'active' : '' ?>" data-tab="tab-lenders">
                    <span>จัดการผู้ให้ยืม</span>
                </button>
                <button type="button" class="nav-item <?= $activeTab === 'tab-borrowers' ? 'active' : '' ?>" data-tab="tab-borrowers">
                    <span>จัดการผู้ยืม (<?= count($departmentBorrowers) ?>)</span>
                </button>
            <?php endif; ?>
            <button type="button" class="nav-item <?= $activeTab === 'tab-display' ? 'active' : '' ?>" data-tab="tab-display">
                <span>การแสดงผล</span>
            </button>
            <button type="button" class="nav-item <?= $activeTab === 'tab-security' ? 'active' : '' ?>" data-tab="tab-security">
                <span>ความปลอดภัย</span>
            </button>
        </div>

        <!-- Settings Content Card -->
        <div class="settings-card">

            <!-- 1. ข้อมูลส่วนตัว (Profile) -->
            <div class="tab-pane <?= $activeTab === 'tab-profile' ? 'active' : '' ?>" id="tab-profile">
                <div class="pane-header">
                    <h2 class="pane-title">ข้อมูลส่วนตัว</h2>
                    <p class="pane-desc">จัดการข้อมูลผู้ใช้งานและเบอร์โทรศัพท์สำหรับติดต่อรับพัสดุ</p>
                </div>

                <div class="profile-badge-row">
                    <div class="avatar-box"><?= htmlspecialchars($avatarChar) ?></div>
                    <div class="profile-meta">
                        <h3><?= htmlspecialchars($currentUser['full_name']) ?></h3>
                        <span class="role-tag"><?= htmlspecialchars($currentUser['role_title']) ?></span>
                    </div>
                </div>

                <form method="POST" action="setting.php">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">ชื่อ-นามสกุล</label>
                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($currentUser['full_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">ชื่อผู้ใช้งาน (Username / รหัส)</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($currentUser['username']) ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">เบอร์โทรศัพท์ (สำหรับติดต่อรับของ)</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($currentUser['phone']) ?>" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')" placeholder="เช่น 0812345678" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">สังกัดแผนก / หน่วยงาน</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($currentUser['dept_name']) ?>" readonly>
                        </div>
                    </div>
                    <button type="submit" class="btn-submit">บันทึกการเปลี่ยนแปลง</button>
                </form>
            </div>

            <?php if ($canManageLenders): ?>
            <!-- 1.5 จัดการผู้ให้ยืมประจำแผนก (Lender Management) -->
            <div class="tab-pane <?= $activeTab === 'tab-lenders' ? 'active' : '' ?>" id="tab-lenders">
                <div class="pane-header">
                    <h2 class="pane-title">จัดการผู้ให้ยืมประจำแผนก</h2>
                    <p class="pane-desc">เปลี่ยนชื่อผู้รับผิดชอบให้ยืมอุปกรณ์ของแผนก แก้ไขเบอร์ติดต่อ หรือเพิ่มผู้ให้ยืมคนใหม่</p>
                </div>

                <div class="lender-list">
                    <?php foreach ($departmentLenders as $lender): 
                        $lChar = mb_substr($lender['full_name'] ?: $lender['username'] ?: 'L', 0, 1, 'UTF-8');
                        $deptCount = $lenderCountsByDept[$lender['dept_key']] ?? 1;
                    ?>
                        <div class="lender-item-card">
                            <div class="lender-card-head">
                                <div class="lender-card-info">
                                    <div class="lender-avatar-sm"><?= htmlspecialchars($lChar) ?></div>
                                    <div>
                                        <h3 class="lender-card-name"><?= htmlspecialchars($lender['full_name']) ?></h3>
                                        <span class="lender-card-sub">รหัสผู้ใช้: <b><?= htmlspecialchars($lender['username']) ?></b></span>
                                    </div>
                                </div>
                                <span class="lender-dept-pill"><?= htmlspecialchars($lender['dept_name']) ?></span>
                            </div>

                            <form method="POST" action="setting.php?tab=tab-lenders">
                                <input type="hidden" name="action" value="update_lender">
                                <input type="hidden" name="lender_id" value="<?= (int)$lender['id'] ?>">

                                <div class="form-grid" style="margin-bottom: 14px;">
                                    <div class="form-group">
                                        <label class="form-label">ชื่อ-นามสกุลผู้ให้ยืม (เปลี่ยนคนใหม่ได้ที่นี่)</label>
                                        <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($lender['full_name']) ?>" placeholder="ระบุชื่อ-นามสกุลผู้ให้ยืม" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">เบอร์โทรศัพท์ติดต่อ (10 หลัก)</label>
                                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($lender['phone'] ?? '') ?>" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')" placeholder="เช่น 0812222222" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">ตั้งรหัสผ่านใหม่ (เว้นว่างไว้หากไม่ต้องการเปลี่ยน)</label>
                                        <input type="password" name="new_password" class="form-control" placeholder="อย่างน้อย 4 ตัวอักษร (ถ้าต้องการเปลี่ยน)">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">บัญชีล็อกอิน (Username)</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($lender['username']) ?>" readonly>
                                    </div>
                                </div>

                                <label class="lender-sync-check">
                                    <input type="checkbox" name="sync_transactions" value="1" checked>
                                    <span>อัปเดตชื่อผู้ให้ยืมในประวัติรายการยืมของแผนกนี้ให้เป็นชื่อใหม่โดยอัตโนมัติ</span>
                                </label>

                                <div class="lender-actions-row">
                                    <button type="submit" class="btn-submit">บันทึกการเปลี่ยนผู้ให้ยืม</button>
                                </div>
                            </form>

                            <?php if ($deptCount > 1): ?>
                                <form method="POST" action="setting.php?tab=tab-lenders" onsubmit="return confirm('ยืนยันการลบบัญชีผู้ให้ยืม <?= htmlspecialchars(addslashes($lender['full_name'])) ?> หรือไม่?');" style="margin-top: 10px; text-align: right;">
                                    <input type="hidden" name="action" value="delete_lender">
                                    <input type="hidden" name="lender_id" value="<?= (int)$lender['id'] ?>">
                                    <button type="submit" class="btn-danger-outline">ลบบัญชีผู้ให้ยืมนี้</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- ฟอร์มเพิ่มผู้ให้ยืมคนใหม่ในแผนก -->
                <div class="add-lender-box">
                    <h3 class="add-lender-title">เพิ่มบัญชีผู้ให้ยืมคนใหม่ในแผนก</h3>
                    <p class="add-lender-desc">ใช้ในกรณีที่แผนกมีเจ้าหน้าที่ผู้ให้ยืมมากกว่า 1 คน เพื่อให้ล็อกอินแยกบัญชีกันได้</p>

                    <form method="POST" action="setting.php?tab=tab-lenders">
                        <input type="hidden" name="action" value="add_lender">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">สังกัดแผนก / หน่วยงาน</label>
                                <?php if ($currentUser['is_super']): ?>
                                    <select name="dept_key" class="form-control" required>
                                        <?php foreach (DEPARTMENTS as $dKey => $dInfo): ?>
                                            <option value="<?= htmlspecialchars($dKey) ?>"><?= htmlspecialchars($dInfo['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($currentUser['dept_name']) ?>" readonly>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label class="form-label">ชื่อ-นามสกุลผู้ให้ยืมใหม่</label>
                                <input type="text" name="full_name" class="form-control" placeholder="เช่น นายสมพงษ์ ดูแลพัสดุ" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">เบอร์โทรศัพท์ติดต่อ (10 หลัก)</label>
                                <input type="text" name="phone" class="form-control" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')" placeholder="เช่น 0891234567" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">ชื่อผู้ใช้งาน (Username - เว้นว่างให้ระบบตั้งอัตโนมัติได้)</label>
                                <input type="text" name="username" class="form-control" placeholder="เช่น lender_cn2 (ไม่บังคับ)">
                            </div>
                            <div class="form-group">
                                <label class="form-label">รหัสผ่านเริ่มต้น</label>
                                <input type="text" name="password" class="form-control" value="1234" required>
                            </div>
                        </div>
                        <button type="submit" class="btn-submit">เพิ่มผู้ให้ยืมคนใหม่</button>
                    </form>
                </div>
            </div>

            <!-- 1.6 จัดการผู้ยืมประจำแผนก (Borrower Management) -->
            <div class="tab-pane <?= $activeTab === 'tab-borrowers' ? 'active' : '' ?>" id="tab-borrowers">
                <div class="pane-header">
                    <h2 class="pane-title">จัดการผู้ยืมประจำแผนก</h2>
                    <p class="pane-desc">แก้ไขชื่อ-นามสกุล เบอร์โทรศัพท์ รีเซ็ตรหัสผ่าน หรือลบบัญชีผู้ยืมที่ไม่ได้ใช้งาน (เฉพาะผู้ที่ไม่มีอุปกรณ์ค้างส่งคืน)</p>
                </div>

                <div class="form-group" style="margin-bottom: 18px;">
                    <input type="text" id="borrowerSearchInput" class="form-control" placeholder="ค้นหาชื่อผู้ยืม, เบอร์โทรศัพท์ หรือรหัสผู้ใช้..." oninput="filterBorrowerCards(this.value)">
                </div>

                <div class="lender-list" id="borrowerCardsList">
                    <?php if (empty($departmentBorrowers)): ?>
                        <div class="lender-item-card" style="text-align: center; color: var(--text-muted); font-weight: 700;">
                            ยังไม่มีรายชื่อผู้ยืมในแผนกนี้
                        </div>
                    <?php else: ?>
                        <?php foreach ($departmentBorrowers as $borrower): 
                            $bChar = mb_substr($borrower['full_name'] ?: $borrower['username'] ?: 'B', 0, 1, 'UTF-8');
                            $activeLoans = (int)($borrower['active_borrows'] ?? 0);
                            $searchKey = mb_strtolower(($borrower['full_name'] ?? '') . ' ' . ($borrower['phone'] ?? '') . ' ' . ($borrower['username'] ?? '') . ' ' . ($borrower['dept_name'] ?? ''));
                        ?>
                            <div class="lender-item-card borrower-mgmt-card" data-search="<?= htmlspecialchars($searchKey) ?>">
                                <div class="lender-card-head">
                                    <div class="lender-card-info">
                                        <div class="lender-avatar-sm" style="background: linear-gradient(135deg, #b8861b, var(--gold)); color: #250c50; border-color: var(--purple);"><?= htmlspecialchars($bChar) ?></div>
                                        <div>
                                            <h3 class="lender-card-name"><?= htmlspecialchars($borrower['full_name']) ?></h3>
                                            <span class="lender-card-sub">
                                                รหัสผู้ใช้: <b><?= htmlspecialchars($borrower['username']) ?></b>
                                                <?php if ($activeLoans > 0): ?>
                                                    • <span style="color: var(--red); font-weight: 800;">กำลังยืมค้างอยู่ <?= $activeLoans ?> รายการ</span>
                                                <?php else: ?>
                                                    • <span style="color: var(--green); font-weight: 700;">ไม่มีรายการค้างส่งคืน</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <span class="lender-dept-pill"><?= htmlspecialchars($borrower['dept_name']) ?></span>
                                </div>

                                <form method="POST" action="setting.php?tab=tab-borrowers">
                                    <input type="hidden" name="action" value="update_borrower">
                                    <input type="hidden" name="borrower_id" value="<?= (int)$borrower['id'] ?>">

                                    <div class="form-grid" style="margin-bottom: 14px;">
                                        <div class="form-group">
                                            <label class="form-label">ชื่อ-นามสกุลผู้ยืม</label>
                                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($borrower['full_name']) ?>" placeholder="ระบุชื่อ-นามสกุลผู้ยืม" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">เบอร์โทรศัพท์ติดต่อ (10 หลัก)</label>
                                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($borrower['phone'] ?? '') ?>" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')" placeholder="เช่น 0811111111" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">รีเซ็ตรหัสผ่านใหม่ (เว้นว่างไว้หากไม่ต้องการเปลี่ยน)</label>
                                            <input type="password" name="new_password" class="form-control" placeholder="อย่างน้อย 4 ตัวอักษร (ถ้าต้องการรีเซ็ต)">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">บัญชีล็อกอิน (Username)</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($borrower['username']) ?>" readonly>
                                        </div>
                                    </div>

                                    <label class="lender-sync-check">
                                        <input type="checkbox" name="sync_transactions" value="1" checked>
                                        <span>อัปเดตชื่อและเบอร์โทรศัพท์ในประวัติรายการยืมของผู้ยืมคนนี้ด้วย</span>
                                    </label>

                                    <div class="lender-actions-row">
                                        <button type="submit" class="btn-submit">บันทึกข้อมูลผู้ยืม</button>
                                    </div>
                                </form>

                                <div style="margin-top: 10px; text-align: right;">
                                    <?php if ($activeLoans > 0): ?>
                                        <span style="font-size: .8rem; font-weight: 700; color: var(--text-muted);">ไม่สามารถลบได้ขณะมีอุปกรณ์ค้างส่งคืน (<?= $activeLoans ?> รายการ)</span>
                                    <?php else: ?>
                                        <form method="POST" action="setting.php?tab=tab-borrowers" onsubmit="return confirm('ยืนยันการลบบัญชีผู้ยืม <?= htmlspecialchars(addslashes($borrower['full_name'])) ?> หรือไม่?\n(หมายเหตุ: ประวัติการยืมในอดีตจะยังคงถูกเก็บรักษาไว้)');" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_borrower">
                                            <input type="hidden" name="borrower_id" value="<?= (int)$borrower['id'] ?>">
                                            <button type="submit" class="btn-danger-outline">ลบบัญชีผู้ยืมนี้</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- ฟอร์มเพิ่มผู้ยืมคนใหม่ในแผนก -->
                <div class="add-lender-box">
                    <h3 class="add-lender-title">เพิ่มบัญชีผู้ยืมคนใหม่ในแผนก</h3>
                    <p class="add-lender-desc">แอดมินสามารถลงทะเบียนสร้างบัญชีผู้ยืมให้พนักงานในแผนกได้โดยตรง</p>

                    <form method="POST" action="setting.php?tab=tab-borrowers">
                        <input type="hidden" name="action" value="add_borrower">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">สังกัดแผนก / หน่วยงาน</label>
                                <?php if ($currentUser['is_super']): ?>
                                    <select name="dept_key" class="form-control" required>
                                        <?php foreach (DEPARTMENTS as $dKey => $dInfo): ?>
                                            <option value="<?= htmlspecialchars($dKey) ?>"><?= htmlspecialchars($dInfo['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($currentUser['dept_name']) ?>" readonly>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label class="form-label">ชื่อ-นามสกุลผู้ยืมใหม่</label>
                                <input type="text" name="full_name" class="form-control" placeholder="เช่น นายกิตติ สายสื่อสาร" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">เบอร์โทรศัพท์ติดต่อ (10 หลัก)</label>
                                <input type="text" name="phone" class="form-control" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')" placeholder="เช่น 0812345678" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">ชื่อผู้ใช้งาน (Username - เว้นว่างให้ระบบตั้งอัตโนมัติได้)</label>
                                <input type="text" name="username" class="form-control" placeholder="เว้นว่างได้ ระบบจะสร้างรหัสให้อัตโนมัติ">
                            </div>
                            <div class="form-group">
                                <label class="form-label">รหัสผ่านเริ่มต้น</label>
                                <input type="text" name="password" class="form-control" value="1234" required>
                            </div>
                        </div>
                        <button type="submit" class="btn-submit">เพิ่มผู้ยืมคนใหม่</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- 2. การแสดงผล (Display / Theme - No Emojis) -->
            <div class="tab-pane <?= $activeTab === 'tab-display' ? 'active' : '' ?>" id="tab-display">
                <div class="pane-header">
                    <h2 class="pane-title">การแสดงผล</h2>
                    <p class="pane-desc">เลือกโทนสีการแสดงผลของหน้าจอระบบ</p>
                </div>

                <div class="theme-picker">
                    <div class="theme-card" id="themeLightCard" onclick="applyTheme('light')">
                        <div class="theme-preview preview-light">
                            <div class="mock-nav"></div>
                            <div class="mock-line"></div>
                            <div class="mock-card"></div>
                        </div>
                        <div class="theme-label-wrap">
                            <span class="theme-title">โหมดสว่าง (Light Mode)</span>
                            <span class="theme-check-indicator"></span>
                        </div>
                    </div>

                    <div class="theme-card" id="themeDarkCard" onclick="applyTheme('dark')">
                        <div class="theme-preview preview-dark">
                            <div class="mock-nav"></div>
                            <div class="mock-line"></div>
                            <div class="mock-card"></div>
                        </div>
                        <div class="theme-label-wrap">
                            <span class="theme-title">โหมดมืด (Dark Mode)</span>
                            <span class="theme-check-indicator"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. ความปลอดภัย (Security) -->
            <div class="tab-pane <?= $activeTab === 'tab-security' ? 'active' : '' ?>" id="tab-security">
                <div class="pane-header">
                    <h2 class="pane-title">ความปลอดภัย</h2>
                    <p class="pane-desc">เปลี่ยนรหัสผ่านเพื่อความปลอดภัยในการเข้าใช้งานระบบ</p>
                </div>

                <form method="POST" action="setting.php">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-grid" style="grid-template-columns: 1fr; max-width: 480px;">
                        <div class="form-group">
                            <label class="form-label">รหัสผ่านปัจจุบัน</label>
                            <input type="password" name="current_password" class="form-control" placeholder="กรอกรหัสผ่านปัจจุบัน" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">รหัสผ่านใหม่</label>
                            <input type="password" name="new_password" class="form-control" placeholder="รหัสผ่านใหม่อย่างน้อย 4 ตัวอักษร" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="กรอกรหัสผ่านใหม่อีกครั้ง" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-submit">อัปเดตรหัสผ่าน</button>
                </form>
            </div>

        </div>
    </div>
</main>

<script>
    // Tab switching logic
    const navItems = document.querySelectorAll('.nav-item');
    const tabPanes = document.querySelectorAll('.tab-pane');

    navItems.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-tab');
            navItems.forEach(b => b.classList.remove('active'));
            tabPanes.forEach(p => p.classList.remove('active'));

            btn.classList.add('active');
            const targetPane = document.getElementById(targetId);
            if (targetPane) targetPane.classList.add('active');
        });
    });

    // Theme switching logic (No Emojis)
    const htmlEl = document.documentElement;
    const cardLight = document.getElementById('themeLightCard');
    const cardDark = document.getElementById('themeDarkCard');

    function applyTheme(theme) {
        if (theme === 'dark') {
            htmlEl.setAttribute('data-theme', 'dark');
            cardDark.classList.add('active');
            cardLight.classList.remove('active');
        } else {
            htmlEl.removeAttribute('data-theme');
            cardLight.classList.add('active');
            cardDark.classList.remove('active');
        }
        localStorage.setItem('itemflow_theme', theme);
    }

    // Load saved theme
    const savedTheme = localStorage.getItem('itemflow_theme') || 'light';
    applyTheme(savedTheme);

    // Filter borrower cards in tab-borrowers
    function filterBorrowerCards(query) {
        const q = (query || '').trim().toLowerCase();
        const cards = document.querySelectorAll('.borrower-mgmt-card');
        cards.forEach(card => {
            const text = (card.getAttribute('data-search') || '').toLowerCase();
            card.style.display = (!q || text.includes(q)) ? '' : 'none';
        });
    }
</script>
</body>
</html>