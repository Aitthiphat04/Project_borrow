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
    <link rel="stylesheet" href="theme.css">
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

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<main class="shell">
    <div class="top">
        <div>
            <div class="brand">
                <img class="pea-logo" src="pea-logo.png" alt="ตราสัญลักษณ์ PEA" onerror="this.src='https://upload.wikimedia.org/wikipedia/th/thumb/a/a4/Provincial_Electricity_Authority_Logo.svg/800px-Provincial_Electricity_Authority_Logo.svg.png'">
                <div>
                    <div class="brand-name">PEA ASSET CARE <b>ITEMFLOW</b></div>
                    <h1>ตั้งค่าระบบ</h1>
                    <p class="subtitle">จัดการข้อมูลส่วนตัวและการแสดงผล (<?= htmlspecialchars($currentUser['dept_name']) ?>)</p>
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
            <button type="button" class="nav-item active" data-tab="tab-profile">
                <span>ข้อมูลส่วนตัว</span>
            </button>
            <button type="button" class="nav-item" data-tab="tab-display">
                <span>การแสดงผล</span>
            </button>
            <button type="button" class="nav-item" data-tab="tab-security">
                <span>ความปลอดภัย</span>
            </button>
        </div>

        <!-- Settings Content Card -->
        <div class="settings-card">

            <!-- 1. ข้อมูลส่วนตัว (Profile) -->
            <div class="tab-pane active" id="tab-profile">
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

            <!-- 2. การแสดงผล (Display / Theme - No Emojis) -->
            <div class="tab-pane" id="tab-display">
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
            <div class="tab-pane" id="tab-security">
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
</script>
</body>
</html>