<?php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/auth.php';

// จัดการการออกจากระบบ
if (isset($_GET['logout'])) {
    logout_user();
    header('Location: index.php');
    exit;
}

$currentUser = current_user();

// หากล็อกอินอยู่แล้วและไม่มีคำสั่ง logout ให้พาไปยังหน้าตามบทบาททันที
if ($currentUser['is_logged_in'] && !isset($_GET['stay'])) {
    if ($currentUser['role'] === 'borrower') {
        header('Location: borrower.php');
        exit;
    } elseif ($currentUser['role'] === 'lender') {
        header('Location: lender.php');
        exit;
    } else {
        header('Location: history.php');
        exit;
    }
}

$usersGrouped = get_all_users_grouped($conn);
$errorMessage = '';

// จัดการการล็อกอิน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'user_login') {
    $deptKey = trim($_POST['dept_key'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $loginRes = login_user($conn, $deptKey, $username, $password);
    if ($loginRes['success']) {
        header('Location: ' . $loginRes['target_url']);
        exit;
    } else {
        $errorMessage = $loginRes['error'];
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
    <title>เข้าสู่ระบบตามบทบาทและแผนก | ITEMFLOW - PEA Asset Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="theme.css">
    <script src="theme.js"></script>
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
            --borrower: #d9ac3b;
            --lender: #7653a7;
            --admin: #ba1e3d;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Prompt', "Segoe UI", Tahoma, sans-serif;
            color: var(--ink);
            background: radial-gradient(circle at 10% 15%, rgba(249, 232, 172, 0.55), transparent 22rem),
                        radial-gradient(circle at 90% 85%, rgba(75, 38, 131, 0.22), transparent 26rem),
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

        .login-shell {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 540px;
            margin: 0 auto;
        }

        .portal-header {
            text-align: center;
            margin-bottom: 22px;
        }
        .pea-logo-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
        }
        .pea-logo {
            width: 84px;
            height: 84px;
            object-fit: contain;
            border-radius: 50%;
            filter: drop-shadow(0 6px 14px rgba(37, 12, 80, 0.28));
        }
        .portal-brand-badge {
            display: inline-block;
            font-size: .74rem;
            font-weight: 800;
            letter-spacing: .16em;
            color: #7b5da8;
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid #e2d7ef;
            padding: 5px 14px;
            border-radius: 999px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .portal-title {
            margin: 0 0 6px;
            font-size: clamp(1.6rem, 3.8vw, 2.1rem);
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

        /* กล่องฟอร์มล็อกอิน */
        .auth-card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.95);
            border-radius: 24px;
            box-shadow: 0 20px 48px rgba(75, 38, 131, 0.12);
            backdrop-filter: blur(14px);
            padding: 30px 28px;
            position: relative;
            overflow: hidden;
            margin-bottom: 20px;
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
            font-size: 1.25rem;
            font-weight: 900;
            color: var(--deep);
            margin: 0 0 6px;
            text-align: center;
        }
        .auth-card-sub {
            font-size: .84rem;
            color: var(--muted);
            margin: 0 0 22px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: .86rem;
            font-weight: 800;
            color: var(--deep);
            margin-bottom: 7px;
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

        /* ป้ายแจ้งบทบาทที่ผูกกับชื่อ */
        .role-preview-box {
            background: #f7f3fb;
            border: 1px solid #e5dbf2;
            border-radius: 12px;
            padding: 10px 14px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: .84rem;
            transition: all .2s ease;
        }
        .role-preview-label {
            color: var(--muted);
            font-weight: 600;
        }
        .role-preview-badge {
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 8px;
            font-size: .8rem;
            letter-spacing: .01em;
        }
        .role-preview-badge.borrower { background: #fdf3d9; color: #9c6c03; border: 1px solid #fae3a6; }
        .role-preview-badge.lender { background: #f1e9fa; color: var(--purple); border: 1px solid #d9c8ee; }
        .role-preview-badge.admin { background: #fff0f3; color: #b81e3c; border: 1px solid #f9bdc9; }
        .role-preview-badge.super_admin { background: #ece3f7; color: #250c50; border: 1px solid #d4c0ea; }

        .btn-submit-login {
            width: 100%;
            border: 0;
            border-radius: 12px;
            padding: 14px;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 800;
            color: #fff;
            background: linear-gradient(135deg, var(--deep), var(--purple) 65%, #7653a7);
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(75, 38, 131, 0.28);
            transition: transform .18s, filter .18s, box-shadow .18s;
            margin-top: 10px;
        }
        .btn-submit-login:hover {
            filter: brightness(1.08);
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(75, 38, 131, 0.35);
        }

        .auth-error {
            background: #fff0f3;
            border: 1px solid #f9bdc9;
            color: var(--danger);
            padding: 10px 14px;
            border-radius: 10px;
            font-size: .84rem;
            font-weight: 700;
            margin-bottom: 16px;
            text-align: center;
        }

        .auth-success-alert {
            background: #e8f7f0;
            border: 1px solid #b7e5ce;
            color: #177b58;
            padding: 11px 14px;
            border-radius: 12px;
            font-size: .86rem;
            font-weight: 700;
            margin-bottom: 16px;
            text-align: center;
        }

        .register-prompt-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background: linear-gradient(135deg, #fdf8eb 0%, #fffdf8 100%);
            border: 1px solid #fae8a6;
            border-left: 4px solid var(--gold);
            padding: 12px 14px;
            border-radius: 14px;
            margin-top: 18px;
        }
        .register-prompt-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .register-prompt-title {
            font-size: .86rem;
            font-weight: 800;
            color: #795200;
        }
        .register-prompt-sub {
            font-size: .78rem;
            color: #8d7030;
        }
        .btn-register-link {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #f9db7a, var(--gold));
            color: var(--deep);
            padding: 8px 14px;
            border-radius: 10px;
            font-size: .84rem;
            font-weight: 800;
            text-decoration: none;
            box-shadow: 0 3px 10px rgba(217, 172, 59, 0.28);
            transition: transform .18s, filter .18s;
        }
        .btn-register-link:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
        }

        /* ตารางสรุปรายชื่อและบทบาทที่กำหนดไว้ */
        .role-reference-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 18px;
            font-size: .8rem;
            color: var(--muted);
            line-height: 1.6;
        }
        .role-reference-title {
            font-size: .84rem;
            font-weight: 800;
            color: var(--deep);
            margin: 0 0 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .role-ref-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 8px;
        }
        .role-ref-row {
            background: #faf7fd;
            border: 1px solid #eee5f8;
            padding: 8px 12px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .role-ref-name {
            font-weight: 700;
            color: var(--deep);
        }
        .role-ref-dept {
            font-size: .74rem;
            color: var(--muted);
        }
        .role-ref-tag {
            font-weight: 800;
            font-size: .74rem;
            padding: 2px 8px;
            border-radius: 6px;
        }
        .role-ref-tag.borrower { background: #fdf3d9; color: #8f6200; }
        .role-ref-tag.lender { background: #f1e9fa; color: var(--purple); }
        .role-ref-tag.admin { background: #fff0f3; color: #a31530; }

        .portal-footer {
            text-align: center;
            font-size: .78rem;
            color: var(--muted);
            margin-top: 18px;
        }
        .portal-footer b { color: var(--purple); }
    </style>
</head>
<body>

<div class="bg-decor top-right"></div>
<div class="bg-decor bottom-left"></div>

<main class="login-shell">
    <header class="portal-header">
        <div class="pea-logo-wrap">
            <img class="pea-logo" src="pea-logo.png" alt="ตราสัญลักษณ์การไฟฟ้าส่วนภูมิภาค PEA">
        </div>
        <div class="portal-brand-badge">PROVINCIAL ELECTRICITY AUTHORITY • PEA ASSET CARE</div>
        <h1 class="portal-title">ระบบยืม-คืนอุปกรณ์ <span>ITEMFLOW</span></h1>
        <p class="portal-desc">เข้าสู่ระบบตามบทบาทและแผนกที่กำหนด</p>
    </header>

    <div class="auth-card">
        <h2 class="auth-card-title">เข้าสู่ระบบการทำงาน</h2>
        <p class="auth-card-sub">เลือกแผนกและชื่อผู้ใช้งาน ระบบจะนำเข้าสู่หน้างานตามบทบาททันที</p>

        <?php if (!empty($_GET['registered'])): ?>
            <div class="auth-success-alert">
                ลงทะเบียนผู้ยืมอุปกรณ์สำเร็จแล้ว กรุณากรอกรหัสผ่านเพื่อเข้าใช้งาน
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="auth-error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php" id="loginForm">
            <input type="hidden" name="action" value="user_login">

            <!-- 1. เลือกแผนก -->
            <div class="form-group">
                <label for="deptSelect">1. เลือกแผนก / หน่วยงาน:</label>
                <select name="dept_key" id="deptSelect" class="form-control" required>
                    <option value="core_network">แผนกโครงข่ายสื่อสารหลัก</option>
                    <option value="power_telecom">แผนกระบบสื่อสารสนับสนุนงานระบบไฟฟ้า</option>
                    <option value="corporate_comm">แผนกเทคโนโลยีการสื่อสารองค์กร</option>
                    <option value="super_admin">ผู้ดูแลระบบส่วนกลาง (Super Admin)</option>
                </select>
            </div>

            <!-- 2. เลือกชื่อผู้ใช้งาน -->
            <div class="form-group">
                <label for="userSelect">2. เลือกชื่อผู้ใช้งาน:</label>
                <select name="username" id="userSelect" class="form-control" required>
                    <!-- JavaScript จะอัปเดตตัวเลือกตามแผนกที่เลือก -->
                </select>

                <!-- แสดงบทบาทที่ผูกไว้กับชื่อที่เลือก -->
                <div class="role-preview-box" id="rolePreviewBox">
                    <span class="role-preview-label">บทบาทที่กำหนด:</span>
                    <span class="role-preview-badge borrower" id="rolePreviewBadge">ผู้ยืมอุปกรณ์</span>
                </div>
            </div>

            <!-- 3. กรอกรหัสผ่าน -->
            <div class="form-group">
                <label for="password">3. รหัสผ่าน:</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="กรอกรหัสผ่าน..." value="1234" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn-submit-login" id="submitLoginBtn">
                เข้าสู่ระบบ
            </button>
        </form>

        <!-- กล่องทางลัดไปหน้าลงทะเบียนผู้ยืม -->
        <div class="register-prompt-card">
            <div class="register-prompt-info">
                <span class="register-prompt-title">ยังไม่มีบัญชีผู้ยืมอุปกรณ์?</span>
                <span class="register-prompt-sub">ลงทะเบียนสร้างบัญชีสำหรับผู้ยืมของแผนกคุณได้ทันที</span>
            </div>
            <a href="register.php" class="btn-register-link" id="gotoRegisterLink">
                <span>ลงทะเบียนผู้ยืมใหม่</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </a>
        </div>
    </div>

    <!-- ข้อมูลแนะนำรายชื่อและบทบาทสำหรับทดสอบ -->
    <div class="role-reference-card">
        <div class="role-reference-title">
            <span>รายชื่อและบทบาทที่กำหนดไว้ในระบบ</span>
            <span>(รหัสผ่านเริ่มต้น: <code>1234</code>)</span>
        </div>
        <div class="role-ref-grid" id="roleRefGrid">
            <!-- JavaScript จะแสดงรายชื่อของแผนกปัจจุบัน -->
        </div>
    </div>

    <footer class="portal-footer">
        © 2026 <b>การไฟฟ้าส่วนภูมิภาค (PEA)</b> • ระบบ ITEMFLOW Asset Care & Equipment Tracking
    </footer>
</main>

<script>
// ข้อมูลผู้ใช้งานที่จัดกลุ่มตามแผนกจากฐานข้อมูล
const usersData = <?= json_encode($usersGrouped['by_dept'], JSON_UNESCAPED_UNICODE) ?>;

const deptSelect = document.getElementById('deptSelect');
const userSelect = document.getElementById('userSelect');
const rolePreviewBadge = document.getElementById('rolePreviewBadge');
const roleRefGrid = document.getElementById('roleRefGrid');
const passwordInput = document.getElementById('password');
const submitLoginBtn = document.getElementById('submitLoginBtn');

function updateUsersDropdown() {
    const selectedDept = deptSelect.value;
    const users = usersData[selectedDept] || [];

    userSelect.innerHTML = '';
    roleRefGrid.innerHTML = '';

    users.forEach((u, index) => {
        const opt = document.createElement('option');
        opt.value = u.username;
        opt.textContent = `${u.full_name} (${u.role_title})`;
        opt.dataset.role = u.role;
        opt.dataset.roleTitle = u.role_title;
        opt.dataset.deptName = u.dept_name;
        userSelect.appendChild(opt);

        // รายการในกล่องอ้างอิงด้านล่าง
        const row = document.createElement('div');
        row.className = 'role-ref-row';
        const displayTag = (u.username.startsWith('u_') || u.role === 'borrower')
            ? (u.phone ? `(${u.phone})` : '')
            : `(${u.username})`;
        row.innerHTML = `
            <div>
                <span class="role-ref-name">${u.full_name}</span>
                ${displayTag ? `<span class="role-ref-dept">${displayTag}</span>` : ''}
            </div>
            <span class="role-ref-tag ${u.role}">${u.role_title}</span>
        `;
        roleRefGrid.appendChild(row);
    });

    if (selectedDept === 'super_admin') {
        passwordInput.value = 'admin1234';
    } else {
        passwordInput.value = '1234';
    }

    onUserChange();
}

function onUserChange() {
    const selectedOpt = userSelect.options[userSelect.selectedIndex];
    if (selectedOpt) {
        const role = selectedOpt.dataset.role;
        const roleTitle = selectedOpt.dataset.roleTitle;

        rolePreviewBadge.textContent = roleTitle;
        rolePreviewBadge.className = 'role-preview-badge ' + role;

        let targetPageName = 'หน้าผู้ยืม';
        if (role === 'lender') targetPageName = 'หน้าผู้ให้ยืม';
        else if (role === 'admin' || role === 'super_admin') targetPageName = 'หน้าผู้ดูแลระบบ';

        submitLoginBtn.textContent = `เข้าสู่ระบบ → ${targetPageName}`;
    }
}

deptSelect.addEventListener('change', () => {
    updateUsersDropdown();
    const gotoLink = document.getElementById('gotoRegisterLink');
    if (gotoLink) {
        const val = deptSelect.value;
        if (val !== 'super_admin') {
            gotoLink.href = 'register.php?dept=' + encodeURIComponent(val);
        } else {
            gotoLink.href = 'register.php';
        }
    }
});
userSelect.addEventListener('change', onUserChange);

// เริ่มต้นโหลดตัวเลือกครั้งแรก
updateUsersDropdown();

// ตรวจสอบ Query parameters เพื่อเลือกอัตโนมัติ (เช่น หลังลงทะเบียนเสร็จ)
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.has('dept')) {
    const deptVal = urlParams.get('dept');
    if (deptSelect.querySelector(`option[value="${deptVal}"]`)) {
        deptSelect.value = deptVal;
        updateUsersDropdown();
    }
}
if (urlParams.has('user')) {
    const userVal = urlParams.get('user');
    for (let i = 0; i < userSelect.options.length; i++) {
        if (userSelect.options[i].value === userVal) {
            userSelect.selectedIndex = i;
            onUserChange();
            passwordInput.value = '';
            passwordInput.focus();
            break;
        }
    }
}
</script>

</body>
</html>
