<?php
date_default_timezone_set('Asia/Bangkok');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth.php';

$currentUser = current_user();
// หากล็อกอินอยู่แล้วและเป็นผู้ยืม ให้พาไปหน้าผู้ยืม ยกเว้นมีพารามิเตอร์ force=1
if ($currentUser['is_logged_in'] && !isset($_GET['force'])) {
    if ($currentUser['role'] === 'borrower') {
        header('Location: borrower.php');
        exit;
    }
}

$errorMessage = '';
$successData = null;

// รองรับค่าเริ่มต้นจาก Query parameter เช่นแผนกที่ส่งต่อมา
$defaultDept = $_GET['dept'] ?? 'core_network';
if (!array_key_exists($defaultDept, DEPARTMENTS)) {
    $defaultDept = 'core_network';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_borrower') {
    $deptKey = trim($_POST['dept_key'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $autoLogin = !empty($_POST['auto_login']);

    if (empty($phone)) {
        $errorMessage = 'กรุณากรอกเบอร์โทรศัพท์ติดต่อ';
    } elseif ($password !== $confirmPassword) {
        $errorMessage = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน กรุณาตรวจสอบอีกครั้ง';
    } else {
        $regResult = register_borrower($conn, $deptKey, $fullName, '', $password, $phone);
        if ($regResult['success']) {
            $user = $regResult['user'];
            if ($autoLogin) {
                // ล็อกอินเข้าสู่ระบบทันที
                $_SESSION['dept_logged_in'] = true;
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['phone'] = $user['phone'] ?? '';
                $_SESSION['dept_key'] = $user['dept_key'];
                $_SESSION['dept_name'] = $user['dept_name'];
                $_SESSION['user_role'] = 'borrower';
                $_SESSION['is_super_admin'] = false;

                header('Location: borrower.php?registered=1');
                exit;
            } else {
                $successData = $user;
            }
        } else {
            $errorMessage = $regResult['error'];
        }
    }
}

if (!function_exists('e')) {
    function e($val) {
        return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียนผู้ยืมอุปกรณ์ | ITEMFLOW - PEA Asset Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --purple: #4b2683;
            --deep: #250c50;
            --gold: #d9ac3b;
            --gold-light: #fae8a6;
            --line: #e5dcef;
            --ink: #2b233a;
            --muted: #766989;
            --card-bg: rgba(255, 255, 255, 0.95);
            --danger: #c83f57;
            --success: #177b58;
            --borrower: #d9ac3b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Prompt', "Segoe UI", Tahoma, sans-serif;
            color: var(--ink);
            background: radial-gradient(circle at 10% 12%, rgba(249, 232, 172, 0.55), transparent 22rem),
                        radial-gradient(circle at 90% 88%, rgba(75, 38, 131, 0.22), transparent 26rem),
                        linear-gradient(135deg, #eee8f8 0%, #faf6ff 50%, #fffaf0 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 24px 14px;
            position: relative;
            overflow-x: hidden;
        }

        .bg-decor {
            position: fixed;
            pointer-events: none;
            border-radius: 50%;
            z-index: 0;
        }
        .bg-decor.top-right {
            width: 36rem;
            height: 36rem;
            top: -18rem;
            right: -12rem;
            border: 1px solid rgba(75, 38, 131, 0.12);
            box-shadow: 0 0 0 35px rgba(75, 38, 131, 0.04), 0 0 0 70px rgba(75, 38, 131, 0.02);
        }
        .bg-decor.bottom-left {
            width: 32rem;
            height: 32rem;
            bottom: -16rem;
            left: -14rem;
            border: 1px solid rgba(217, 172, 59, 0.18);
            box-shadow: 0 0 0 30px rgba(217, 172, 59, 0.06), 0 0 0 60px rgba(217, 172, 59, 0.03);
        }

        .register-shell {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 560px;
            margin: 0 auto;
        }

        .portal-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .pea-logo-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
        }
        .pea-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 50%;
            filter: drop-shadow(0 6px 14px rgba(37, 12, 80, 0.28));
        }
        .portal-brand-badge {
            display: inline-block;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .16em;
            color: #7b5da8;
            background: rgba(255, 255, 255, 0.75);
            border: 1px solid #e2d7ef;
            padding: 5px 14px;
            border-radius: 999px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .portal-title {
            margin: 0 0 6px;
            font-size: clamp(1.5rem, 3.6vw, 2rem);
            font-weight: 900;
            color: var(--deep);
            letter-spacing: -.01em;
        }
        .portal-title span {
            color: var(--purple);
            background: linear-gradient(135deg, var(--deep), var(--purple) 60%, #7653a7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .portal-desc {
            margin: 0;
            font-size: .88rem;
            color: var(--muted);
            font-weight: 500;
        }

        /* การ์ดฟอร์มลงทะเบียน */
        .auth-card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.95);
            border-radius: 24px;
            box-shadow: 0 20px 48px rgba(75, 38, 131, 0.12);
            backdrop-filter: blur(14px);
            padding: 30px 28px;
            position: relative;
            overflow: hidden;
            margin-bottom: 18px;
        }
        .auth-card:before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--gold), var(--purple));
        }
        .auth-card-title {
            font-size: 1.3rem;
            font-weight: 900;
            color: var(--deep);
            margin: 0 0 6px;
            text-align: center;
        }
        .auth-card-sub {
            font-size: .84rem;
            color: var(--muted);
            margin: 0 0 18px;
            text-align: center;
        }

        /* ป้ายแจ้งบทบาทผู้ยืม */
        .role-notice-banner {
            display: flex;
            align-items: center;
            gap: 12px;
            background: linear-gradient(135deg, #fdf8eb 0%, #fffdf8 100%);
            border: 1px solid #fae8a6;
            border-left: 4px solid var(--gold);
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .role-notice-icon {
            flex-shrink: 0;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #fae8a6;
            color: #8c6000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .role-notice-icon svg {
            width: 20px;
            height: 20px;
        }
        .role-notice-content {
            flex: 1;
        }
        .role-notice-title {
            font-size: .86rem;
            font-weight: 800;
            color: #795200;
            margin-bottom: 2px;
        }
        .role-notice-desc {
            font-size: .78rem;
            color: #8d7030;
            line-height: 1.4;
        }

        .auth-error {
            background: #fff0f3;
            border: 1px solid #f9bdc9;
            color: var(--danger);
            padding: 11px 14px;
            border-radius: 12px;
            font-size: .86rem;
            font-weight: 700;
            margin-bottom: 18px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: .86rem;
            font-weight: 800;
            color: var(--deep);
            margin-bottom: 6px;
        }
        .form-group label .req {
            color: var(--danger);
            margin-left: 3px;
        }
        .form-group label .hint {
            font-size: .76rem;
            font-weight: 500;
            color: var(--muted);
        }
        .form-control-wrap {
            position: relative;
        }
        .form-control {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px 14px;
            font-family: inherit;
            font-size: .94rem;
            color: var(--ink);
            background: #fff;
            transition: border-color .2s, box-shadow .2s;
        }
        .form-control:focus {
            outline: 0;
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(75, 38, 131, 0.15);
        }
        .form-control.has-toggle {
            padding-right: 44px;
        }
        .password-toggle-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: var(--muted);
            cursor: pointer;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }
        .password-toggle-btn:hover {
            color: var(--purple);
            background: #f4eff9;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        @media (max-width: 520px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }

        .form-checkbox-wrap {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 16px 0 20px;
            font-size: .84rem;
            color: var(--ink);
            line-height: 1.4;
            cursor: pointer;
        }
        .form-checkbox-wrap input[type="checkbox"] {
            margin-top: 2px;
            width: 17px;
            height: 17px;
            accent-color: var(--purple);
            cursor: pointer;
        }

        .btn-submit-register {
            width: 100%;
            border: 0;
            border-radius: 12px;
            padding: 14px;
            font-family: inherit;
            font-size: 1.02rem;
            font-weight: 800;
            color: #fff;
            background: linear-gradient(135deg, var(--deep), var(--purple) 65%, #7653a7);
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(75, 38, 131, 0.28);
            transition: transform .18s, filter .18s, box-shadow .18s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-submit-register:hover {
            filter: brightness(1.08);
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(75, 38, 131, 0.35);
        }

        /* ส่วนเชื่อมต่อไปหน้าเข้าสู่ระบบ */
        .auth-switch-box {
            text-align: center;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid var(--line);
            font-size: .88rem;
            color: var(--muted);
        }
        .auth-switch-link {
            color: var(--purple);
            font-weight: 800;
            text-decoration: none;
            margin-left: 6px;
            transition: color .18s;
        }
        .auth-switch-link:hover {
            color: var(--deep);
            text-decoration: underline;
        }

        /* การ์ดสำเร็จ */
        .success-card {
            text-align: center;
            padding: 10px 0;
        }
        .success-icon-badge {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            background: #e8f7f0;
            border: 2px solid #b7e5ce;
            color: var(--success);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
        }
        .success-icon-badge svg {
            width: 36px;
            height: 36px;
        }
        .success-title {
            font-size: 1.3rem;
            font-weight: 900;
            color: var(--deep);
            margin: 0 0 6px;
        }
        .success-desc {
            font-size: .88rem;
            color: var(--muted);
            margin: 0 0 20px;
        }
        .success-info-box {
            background: #faf7fd;
            border: 1px solid #ebdff8;
            border-radius: 14px;
            padding: 14px 18px;
            text-align: left;
            margin-bottom: 22px;
            font-size: .88rem;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px dashed #eee5f8;
        }
        .info-row:last-child {
            border-bottom: 0;
        }
        .info-label {
            color: var(--muted);
            font-weight: 600;
        }
        .info-val {
            font-weight: 800;
            color: var(--deep);
        }
        .btn-goto-login {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            border-radius: 12px;
            padding: 13px;
            font-family: inherit;
            font-size: .98rem;
            font-weight: 800;
            text-decoration: none;
            color: #fff;
            background: linear-gradient(135deg, var(--deep), var(--purple));
            box-shadow: 0 4px 14px rgba(75, 38, 131, 0.25);
            transition: transform .18s;
        }
        .btn-goto-login:hover {
            transform: translateY(-2px);
        }

        .portal-footer {
            text-align: center;
            font-size: .78rem;
            color: var(--muted);
            margin-top: 14px;
        }
        .portal-footer b { color: var(--purple); }
    </style>
</head>
<body>

<div class="bg-decor top-right"></div>
<div class="bg-decor bottom-left"></div>

<main class="register-shell">
    <header class="portal-header">
        <div class="pea-logo-wrap">
            <img class="pea-logo" src="pea-logo.png" alt="ตราสัญลักษณ์การไฟฟ้าส่วนภูมิภาค PEA">
        </div>
        <div class="portal-brand-badge">PROVINCIAL ELECTRICITY AUTHORITY • PEA ASSET CARE</div>
        <h1 class="portal-title">ระบบยืม-คืนอุปกรณ์ <span>ITEMFLOW</span></h1>
        <p class="portal-desc">ลงทะเบียนบัญชีสำหรับผู้ยืมอุปกรณ์</p>
    </header>

    <div class="auth-card">
        <?php if ($successData): ?>
            <!-- กล่องแสดงผลลงทะเบียนสำเร็จ -->
            <div class="success-card">
                <div class="success-icon-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 6L9 17l-5-5"></path>
                    </svg>
                </div>
                <h2 class="success-title">ลงทะเบียนผู้ยืมอุปกรณ์สำเร็จ</h2>
                <p class="success-desc">สร้างบัญชีผู้ใช้งานสำหรับยืมอุปกรณ์เรียบร้อยแล้ว คุณสามารถเข้าสู่ระบบเพื่อทำรายการได้ทันที</p>

                <div class="success-info-box">
                    <div class="info-row">
                        <span class="info-label">ชื่อ-นามสกุล:</span>
                        <span class="info-val"><?= e($successData['full_name']) ?></span>
                    </div>
                    <?php if (!empty($successData['phone'])): ?>
                    <div class="info-row">
                        <span class="info-label">เบอร์โทรศัพท์:</span>
                        <span class="info-val"><?= e($successData['phone']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">สังกัดแผนก:</span>
                        <span class="info-val"><?= e($successData['dept_name']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">บทบาทระบบ:</span>
                        <span class="info-val" style="color:#9c6c03;"><?= e($successData['role_title']) ?></span>
                    </div>
                </div>

                <a href="login.php?dept=<?= urlencode($successData['dept_key']) ?>&user=<?= urlencode($successData['username']) ?>" class="btn-goto-login">
                    <span>ไปยังหน้าเข้าสู่ระบบเพื่อเข้าใช้งาน</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </a>
            </div>
        <?php else: ?>
            <!-- ฟอร์มลงทะเบียน -->
            <h2 class="auth-card-title">ลงทะเบียนผู้ยืมอุปกรณ์ใหม่</h2>
            <p class="auth-card-sub">กรอกข้อมูลเพื่อสร้างบัญชีผู้ยืมสำหรับสังกัดแผนกของคุณ</p>


            <?php if ($errorMessage): ?>
                <div class="auth-error">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span><?= e($errorMessage) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" id="registerForm">
                <input type="hidden" name="action" value="register_borrower">

                <!-- 1. เลือกสังกัดแผนก -->
                <div class="form-group">
                    <label for="deptKey">
                        <span>1. สังกัดแผนก / หน่วยงาน<span class="req">*</span></span>
                    </label>
                    <select name="dept_key" id="deptKey" class="form-control" required>
                        <?php foreach (DEPARTMENTS as $key => $d): ?>
                            <option value="<?= e($key) ?>" <?= $key === $defaultDept ? 'selected' : '' ?>>
                                <?= e($d['name']) ?> (<?= e($d['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 2. ชื่อ-นามสกุลจริง -->
                <div class="form-group">
                    <label for="fullName">
                        <span>2. ชื่อ-นามสกุล ผู้ยืม<span class="req">*</span></span>
                        <span class="hint">ชื่อจริงที่ใช้ในการบันทึกเอกสาร</span>
                    </label>
                    <input type="text" name="full_name" id="fullName" class="form-control" required value="<?= e($_POST['full_name'] ?? '') ?>" autocomplete="name">
                </div>

                <!-- 3. เบอร์โทรศัพท์ติดต่อ -->
                <div class="form-group">
                    <label for="phone">
                        <span>3. เบอร์โทรศัพท์ติดต่อ<span class="req">*</span></span>
                    </label>
                    <input type="tel" name="phone" id="phone" class="form-control" required value="<?= e($_POST['phone'] ?? '') ?>" autocomplete="tel">
                </div>

                <!-- 4. รหัสผ่าน และ ยืนยันรหัสผ่าน -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">
                            <span>4. กำหนดรหัสผ่าน<span class="req">*</span></span>
                            <span class="hint">ขั้นต่ำ 4 ตัว</span>
                        </label>
                        <div class="form-control-wrap">
                            <input type="password" name="password" id="password" class="form-control has-toggle" minlength="4" required autocomplete="new-password">
                            <button type="button" class="password-toggle-btn" data-target="password" title="แสดง/ซ่อนรหัสผ่าน" aria-label="แสดงหรือซ่อนรหัสผ่าน">
                                <svg class="eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="eye-closed" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirmPassword">
                            <span>5. ยืนยันรหัสผ่าน<span class="req">*</span></span>
                        </label>
                        <div class="form-control-wrap">
                            <input type="password" name="confirm_password" id="confirmPassword" class="form-control has-toggle" minlength="4" required autocomplete="new-password">
                            <button type="button" class="password-toggle-btn" data-target="confirmPassword" title="แสดง/ซ่อนรหัสผ่าน" aria-label="แสดงหรือซ่อนรหัสผ่าน">
                                <svg class="eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="eye-closed" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- เข้าสู่ระบบทันที -->
                <label class="form-checkbox-wrap">
                    <input type="checkbox" name="auto_login" value="1" checked>
                    <span>เข้าสู่ระบบทันทีและนำทางไปยังหน้าบันทึกการยืมอุปกรณ์หลังลงทะเบียนสำเร็จ</span>
                </label>

                <!-- ปุ่มส่งฟอร์ม -->
                <button type="submit" class="btn-submit-register" id="btnRegister">
                    <span>ยืนยันการลงทะเบียนผู้ยืมอุปกรณ์</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
            </form>

            <div class="auth-switch-box">
                <span>มีบัญชีผู้ใช้งานอยู่แล้ว?</span>
                <a href="login.php" class="auth-switch-link">เข้าสู่ระบบที่นี่</a>
            </div>
        <?php endif; ?>
    </div>

    <footer class="portal-footer">
        © 2026 <b>การไฟฟ้าส่วนภูมิภาค (PEA)</b> • ระบบ ITEMFLOW Asset Care & Equipment Tracking
    </footer>
</main>

<script>
// สลับแสดง/ซ่อนรหัสผ่าน
document.querySelectorAll('.password-toggle-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const targetId = btn.getAttribute('data-target');
        const input = document.getElementById(targetId);
        if (!input) return;

        const isPass = input.type === 'password';
        input.type = isPass ? 'text' : 'password';

        const openIcon = btn.querySelector('.eye-open');
        const closedIcon = btn.querySelector('.eye-closed');
        if (openIcon && closedIcon) {
            openIcon.style.display = isPass ? 'none' : 'block';
            closedIcon.style.display = isPass ? 'block' : 'none';
        }
    });
});

// ตรวจสอบความถูกต้องก่อนส่ง
const regForm = document.getElementById('registerForm');
if (regForm) {
    regForm.addEventListener('submit', (e) => {
        const phone = document.getElementById('phone').value.trim();
        if (!phone) {
            e.preventDefault();
            alert('กรุณากรอกเบอร์โทรศัพท์ติดต่อ');
            document.getElementById('phone').focus();
            return;
        }

        const p1 = document.getElementById('password').value;
        const p2 = document.getElementById('confirmPassword').value;

        if (p1 !== p2) {
            e.preventDefault();
            alert('รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน กรุณากรอกใหม่อีกครั้ง');
            document.getElementById('confirmPassword').focus();
        }
    });
}
</script>

</body>
</html>
