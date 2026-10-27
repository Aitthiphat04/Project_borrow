<?php
// setting.php - หน้าตั้งค่าระบบ ข้อมูลส่วนตัว และการแสดงผล PEA ITEMFLOW
require_once __DIR__ . '/auth.php';

// บังคับล็อกอินก่อนเข้าหน้าตั้งค่า
require_auth_and_role();
$currentUser = current_user();

$msgSuccess = '';
$msgError = '';

// จัดการเมื่อมีการส่งฟอร์มบันทึกข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
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
    } elseif ($action === 'change_password') {
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

// หน้าหลักที่ผู้ใช้จะย้อนกลับไปตามสิทธิ์
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
    <title>ตั้งค่าผู้ใช้งาน | ITEMFLOW</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- ฟอนต์ Kanit -->
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-color: #f5edf8;
            --card-bg: white;
            --text-color: #333;
            --text-muted: #6c757d;
            --navbar-bg: #4b2f89;
            --sidebar-hover: #f7f3fb;
            --border-color: #e0dced;
            --input-bg: white;
            --input-text: #333;
            --input-placeholder: #adb5bd;
        }

        /* โหมดมืด (Dark Mode) */
        [data-theme="dark"] {
            --bg-color: #1a1a2e;
            --card-bg: #252542;
            --text-color: #f8f9fa;
            --text-muted: #b0b0c0;
            --navbar-bg: #301b5e;
            --sidebar-hover: #35355e;
            --border-color: #44446b;
            --input-bg: #1a1a2e;
            --input-text: #ffffff;
            --input-placeholder: #7a7a9d;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Kanit', sans-serif;
            margin: 0;
            padding: 20px 0;
            transition: background-color 0.3s, color 0.3s;
        }

        .top-navbar-card {
            background-color: var(--navbar-bg);
            color: white;
            border-radius: 16px;
            padding: 15px 30px;
            max-width: 95%;
            margin: 0 auto 25px auto;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-custom-yellow {
            background-color: #f6c23e;
            color: #333;
            border: none;
            border-radius: 25px;
            padding: 8px 25px;
            font-weight: 600;
            transition: filter 0.2s, transform 0.15s;
        }

        .btn-custom-yellow:hover {
            background-color: #e5b337;
            filter: brightness(1.05);
            transform: translateY(-1px);
        }

        .settings-container {
            max-width: 95%;
            margin: 0 auto;
        }

        .card-custom {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: background-color 0.3s, border-color 0.3s;
        }

        .nav-pills .nav-link {
            color: #5c3b99;
            border-radius: 10px;
            padding: 12px 20px;
            margin-bottom: 8px;
            font-weight: 500;
            transition: all 0.2s;
        }

        [data-theme="dark"] .nav-pills .nav-link {
            color: #d8ccee;
        }

        .nav-pills .nav-link:hover {
            background-color: var(--sidebar-hover);
        }

        .nav-pills .nav-link.active {
            background-color: #5c3b99;
            color: white !important;
        }

        [data-theme="dark"] .nav-pills .nav-link.active {
            background-color: #6a44b8;
        }

        .form-control,
        .form-select {
            background-color: var(--input-bg);
            color: var(--input-text);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 10px 15px;
        }

        .form-control:focus,
        .form-select:focus {
            background-color: var(--input-bg);
            color: var(--input-text);
            border-color: #a980ff;
            box-shadow: 0 0 0 0.2rem rgba(169, 128, 255, 0.25);
        }

        .form-control::placeholder {
            color: var(--input-placeholder);
            opacity: 1;
        }

        [data-theme="dark"] select option {
            background-color: #252542;
            color: white;
        }

        .avatar-preview {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ede4f8, #dfd0f4);
            border: 2px solid #5c3b99;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            font-weight: 700;
            color: #5c3b99;
            box-shadow: 0 4px 12px rgba(92, 59, 153, 0.15);
        }

        [data-theme="dark"] .avatar-preview {
            background: #442a75;
            border-color: #a980ff;
            color: #eaddf5;
        }

        .text-dark-custom {
            color: var(--text-color) !important;
        }

        .text-muted-custom {
            color: var(--text-muted) !important;
        }
    </style>
</head>

<body>

    <div class="top-navbar-card d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <a href="<?= htmlspecialchars($backUrl) ?>" class="text-decoration-none d-flex align-items-center">
                <div class="bg-white rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 55px; height: 55px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                    <img src="pea-logo.png" alt="Logo" style="width: 38px; height: 38px; object-fit: contain;" onerror="this.src='https://upload.wikimedia.org/wikipedia/th/thumb/a/a4/Provincial_Electricity_Authority_Logo.svg/800px-Provincial_Electricity_Authority_Logo.svg.png'">
                </div>
                <div>
                    <h4 class="m-0 fw-bold text-white">ตั้งค่าระบบ</h4>
                    <div style="font-size: 0.8rem; color: #d8ccee;">จัดการข้อมูลส่วนตัวและการแสดงผล (<?= htmlspecialchars($currentUser['dept_name']) ?>)</div>
                </div>
            </a>
        </div>
        <div>
            <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-light fw-bold" style="border-radius: 25px; padding: 8px 20px; color: #4b2f89;">กลับหน้าหลัก</a>
        </div>
    </div>

    <div class="settings-container">
        <?php if (!empty($msgSuccess)): ?>
            <div class="alert alert-success alert-dismissible fade show fw-bold" role="alert">
                <?= htmlspecialchars($msgSuccess) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($msgError)): ?>
            <div class="alert alert-danger alert-dismissible fade show fw-bold" role="alert">
                <?= htmlspecialchars($msgError) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Sidebar Menu -->
            <div class="col-md-3 mb-4">
                <div class="card card-custom p-3">
                    <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                        <button class="nav-link active text-start fw-bold" data-bs-toggle="pill" data-bs-target="#v-pills-profile" type="button" role="tab">👤 ข้อมูลส่วนตัว</button>
                        <button class="nav-link text-start fw-bold" data-bs-toggle="pill" data-bs-target="#v-pills-display" type="button" role="tab">🎨 การแสดงผล</button>
                        <button class="nav-link text-start fw-bold" data-bs-toggle="pill" data-bs-target="#v-pills-security" type="button" role="tab">🔒 ความปลอดภัย</button>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="col-md-9">
                <div class="card card-custom p-4">
                    <div class="tab-content">

                        <!-- 1. ข้อมูลส่วนตัว -->
                        <div class="tab-pane fade show active" id="v-pills-profile" role="tabpanel">
                            <h4 class="fw-bold mb-4 text-dark-custom">ข้อมูลส่วนตัว (Profile)</h4>
                            <div class="d-flex align-items-center mb-4 pb-3" style="border-bottom: 1px solid var(--border-color);">
                                <div class="avatar-preview me-4"><?= htmlspecialchars($avatarChar) ?></div>
                                <div>
                                    <h5 class="fw-bold mb-1 text-dark-custom"><?= htmlspecialchars($currentUser['full_name']) ?></h5>
                                    <div class="text-muted-custom" style="font-size: 0.9rem;">สถานะ: <span class="badge bg-purple" style="background:#5c3b99;"><?= htmlspecialchars($currentUser['role_title']) ?></span></div>
                                </div>
                            </div>
                            <form method="POST" action="setting.php">
                                <input type="hidden" name="action" value="update_profile">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label text-muted-custom fw-bold">ชื่อ-นามสกุล</label>
                                        <input type="text" name="full_name" class="form-control fw-bold" value="<?= htmlspecialchars($currentUser['full_name']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-muted-custom fw-bold">ชื่อผู้ใช้งาน (Username / รหัส)</label>
                                        <input type="text" class="form-control fw-bold" value="<?= htmlspecialchars($currentUser['username']) ?>" readonly style="background-color: rgba(0,0,0,0.05);">
                                    </div>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label text-muted-custom fw-bold">เบอร์โทรศัพท์ (สำหรับติดต่อรับของ)</label>
                                        <input type="text" name="phone" class="form-control fw-bold" value="<?= htmlspecialchars($currentUser['phone']) ?>" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')" placeholder="เช่น 0812345678" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-muted-custom fw-bold">สังกัดแผนก / หน่วยงาน</label>
                                        <input type="text" class="form-control fw-bold" value="<?= htmlspecialchars($currentUser['dept_name']) ?>" readonly style="background-color: rgba(0,0,0,0.05);">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-custom-yellow px-4 fw-bold">บันทึกการเปลี่ยนแปลง</button>
                            </form>
                        </div>

                        <!-- 2. การแสดงผล (Theme) -->
                        <div class="tab-pane fade" id="v-pills-display" role="tabpanel">
                            <h4 class="fw-bold mb-4 text-dark-custom">การแสดงผล (Display)</h4>
                            <div class="mb-4 pb-3" style="border-bottom: 1px solid var(--border-color);">
                                <label class="form-label text-muted-custom fw-bold mb-3">เลือกโหมดหน้าจอ (Theme)</label>
                                <div class="d-flex gap-3">
                                    <div class="border rounded p-3 text-center" id="theme-light-btn" style="width: 140px; cursor: pointer; background-color: #f8f9fa; border-color: #dee2e6; transition: 0.2s;">
                                        <div class="fs-2 mb-2">☀️</div>
                                        <div class="fw-bold text-dark">Light mode</div>
                                    </div>
                                    <div class="border rounded p-3 text-center" id="theme-dark-btn" style="width: 140px; cursor: pointer; background-color: #212529; color: white; border-color: #212529; transition: 0.2s;">
                                        <div class="fs-2 mb-2">🌙</div>
                                        <div class="fw-bold">Dark mode</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. ความปลอดภัย -->
                        <div class="tab-pane fade" id="v-pills-security" role="tabpanel">
                            <h4 class="fw-bold mb-4 text-dark-custom">ความปลอดภัย (Security)</h4>
                            <form method="POST" action="setting.php">
                                <input type="hidden" name="action" value="change_password">
                                <div class="mb-4">
                                    <h6 class="fw-bold text-muted-custom mb-3">เปลี่ยนรหัสผ่าน</h6>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label text-muted-custom fw-bold">รหัสผ่านปัจจุบัน</label>
                                            <input type="password" name="current_password" class="form-control mb-2 fw-bold" placeholder="รหัสผ่านปัจจุบัน" required>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label text-muted-custom fw-bold">รหัสผ่านใหม่</label>
                                            <input type="password" name="new_password" class="form-control mb-2 fw-bold" placeholder="รหัสผ่านใหม่ (อย่างน้อย 4 ตัวอักษร)" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-muted-custom fw-bold">ยืนยันรหัสผ่านใหม่</label>
                                            <input type="password" name="confirm_password" class="form-control fw-bold" placeholder="ยืนยันรหัสผ่านใหม่" required>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-custom-yellow px-4 mt-2 fw-bold">อัปเดตรหัสผ่าน</button>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ระบบสลับโหมดหน้าจอ (Dark / Light Mode)
        const htmlEl = document.documentElement;
        const btnLight = document.getElementById('theme-light-btn');
        const btnDark = document.getElementById('theme-dark-btn');

        // โหลดค่า Theme เดิมที่บันทึกไว้
        const savedTheme = localStorage.getItem('itemflow_theme') || 'light';
        setTheme(savedTheme);

        btnLight.addEventListener('click', () => setTheme('light'));
        btnDark.addEventListener('click', () => setTheme('dark'));

        function setTheme(theme) {
            if (theme === 'dark') {
                htmlEl.setAttribute('data-theme', 'dark');
                btnDark.style.border = '2px solid #a980ff';
                btnDark.style.boxShadow = '0 0 10px rgba(169, 128, 255, 0.3)';
                btnLight.style.border = '1px solid #dee2e6';
                btnLight.style.boxShadow = 'none';
            } else {
                htmlEl.removeAttribute('data-theme');
                btnLight.style.border = '2px solid #5c3b99';
                btnLight.style.boxShadow = '0 0 10px rgba(92, 59, 153, 0.2)';
                btnDark.style.border = '1px solid #212529';
                btnDark.style.boxShadow = 'none';
            }
            localStorage.setItem('itemflow_theme', theme);
        }
    </script>
</body>

</html>