<?php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/db.php';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function images_array($value) {
    if ($value === null || $value === '') {
        return [];
    }
    return array_values(array_filter(array_map('trim', explode(',', (string)$value))));
}

function format_elapsed_time($start, $end) {
    if (!$start || !$end) {
        return 'ยังไม่ครบ';
    }

    try {
        $borrow = new DateTimeImmutable($start);
        $return = new DateTimeImmutable($end);
    } catch (Exception $e) {
        return 'ไม่สามารถคำนวณได้';
    }

    $diff = $return->diff($borrow);
    $parts = [];

    if ((int)$diff->days > 0) {
        $parts[] = $diff->format('%a วัน');
    }

    if ((int)$diff->h > 0 || empty($parts)) {
        $parts[] = $diff->format('%h ชั่วโมง');
    }

    if ((int)$diff->i > 0 || empty($parts)) {
        $parts[] = $diff->format('%i นาที');
    }

    return implode(' ', $parts) ?: '0 นาที';
}

$id = (int)($_GET['id'] ?? 0);
if ($id < 1) {
    die('ไม่พบรายการที่ต้องการดู');
}

require_once __DIR__ . '/auth.php';
require_auth_and_role(['borrower', 'lender', 'admin', 'super_admin']);
$currentUser = current_user();
$deptInfo = $currentUser;

$stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ? AND action_type = 'Borrow' LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    die('ไม่พบรายการยืมที่ระบุ');
}

if (!$currentUser['is_super'] && !empty($row['department']) && $row['department'] !== $currentUser['dept_name']) {
    die('ท่านไม่มีสิทธิ์เข้าถึงข้อมูลของแผนกอื่น');
}

$isEdit = isset($_GET['edit']) && $_GET['edit'] === '1' && in_array($currentUser['role'], ['admin', 'super_admin'], true);
$borrowImages = images_array($row['image_path']);
$returnImages = images_array($row['return_image_path']);
$status = $row['return_time'] ? ($row['condition_status'] ?: 'needs_review') : 'pending';
$labels = [
    'pending' => 'กำลังยืมอยู่',
    'good' => 'ปกติ',
    'damaged' => 'พบความเสียหาย',
    'needs_review' => 'รอตรวจสอบ'
];
$displayStatus = $labels[$status] ?? 'รอตรวจสอบ';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ITEMFLOW | รายละเอียดรายการ</title>
    <style>
        :root {
            --purple: #4b2e83;
            --deep: #2a174d;
            --gold: #f5c75a;
            --line: #e9e0f7;
            --bg: #f7f4ff;
            --red: #cf4e60;
            --gray: #706486;
            --card: #ffffff;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 28px 18px 42px;
            background: linear-gradient(135deg, #f3f0ff, #fff7ea);
            font-family: 'Segoe UI', Tahoma, sans-serif;
            color: var(--deep);
        }
        .page {
            max-width: 94%;
            width: min(1600px, 94%);
            margin: 0 auto;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }
        .title {
            margin: 0;
            font-size: clamp(2rem, 5vw, 2.7rem);
        }
        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-block;
            padding: 13px 22px;
            border-radius: 13px;
            border: none;
            font-size: 1.02rem;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            transition: transform .18s, filter .18s;
        }
        .btn:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
        }
        .primary { background: var(--purple); color: #fff; }
        .secondary { background: #efe9ff; color: var(--purple); }
        .danger { background: linear-gradient(135deg, #ffe5ea, #ffd1d9); color: #982038; border: 1px solid #f5b0bd; }
        .danger:hover { background: linear-gradient(135deg, #ffd7df, #ffc0cb); color: #80142b; }
        .card {
            background: rgba(255,255,255,0.96);
            border: 1px solid var(--line);
            border-radius: 26px;
            padding: 28px;
            box-shadow: 0 18px 40px rgba(73, 48, 112, 0.12);
        }
        .session-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
            margin-top: 20px;
        }
        .session {
            background: linear-gradient(135deg, #f5f1ff 0%, #f1f8ff 100%);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 22px;
        }
        .session-title {
            margin: 0 0 16px;
            font-size: 1.35rem;
            color: var(--purple);
        }
        .meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 14px;
        }
        .item {
            background: linear-gradient(180deg, #ffffff 0%, #f9f5ff 100%);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 16px 18px;
        }
        .label {
            display: block;
            color: var(--gray);
            font-size: 0.92rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            margin-bottom: 8px;
        }
        .value {
            font-size: 1.15rem;
            font-weight: 700;
            word-break: break-word;
        }
        .gallery-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
            margin-top: 30px;
        }
        .panel {
            background: linear-gradient(180deg, #fffdf8 0%, #f7f4ff 100%);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 20px;
        }
        .panel h3 {
            margin: 0 0 16px;
            font-size: 1.25rem;
            color: var(--purple);
        }
        .img-list {
            display: grid;
            gap: 16px;
        }
        .img-list img {
            width: 100%;
            height: 320px;
            object-fit: cover;
            border-radius: 16px;
            border: 1px solid #f0e9ff;
            background: #f3eefc;
        }
        .none {
            color: var(--gray);
            padding: 20px 10px;
            text-align: center;
            border: 1px dashed var(--line);
            border-radius: 12px;
            background: #faf9ff;
        }
        @media (max-width: 640px) {
            .card { padding: 18px 14px; }
            body { padding: 18px 12px 28px; }
        }
        .edit-card { margin-bottom: 18px; background: linear-gradient(135deg, #fffdf6, #f6f1ff); border: 1px solid #eadba9; border-radius: 20px; padding: 20px; box-shadow: 0 12px 25px rgba(73, 48, 112, 0.08); }
        .edit-card h2 { margin: 0 0 14px; color: var(--deep); font-size: 1.25rem; }
        .edit-form { display: grid; grid-template-columns: minmax(260px, 1fr) minmax(360px, 1fr) auto; gap: 12px; align-items: end; }
        .edit-form label { display: block; margin-bottom: 6px; font-size: .85rem; font-weight: 800; color: var(--gray); }
        .edit-form select, .edit-form textarea { width: 100%; height: 54px; border: 1px solid var(--line); border-radius: 11px; padding: 10px 11px; font: inherit; color: var(--deep); background: #fff; }
        .edit-form textarea { min-height: 54px; resize: vertical; }
        .save-edit { height: 54px; background: linear-gradient(135deg, #f9db7a, var(--gold)); color: var(--deep); }
        @media (max-width: 640px) { .edit-form { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="page">
        <div class="topbar">
            <h1 class="title">รายละเอียดรายการ</h1>
            <div class="actions">
                <?php
                $backUrl = ($currentUser['role'] === 'lender') ? 'lender.php' : (($currentUser['role'] === 'borrower') ? 'borrower.php' : 'history.php');
                ?>
                <a class="btn secondary" href="<?= e($backUrl) ?>">ย้อนกลับ</a>
                <?php if ($currentUser['role'] !== 'borrower'): ?>
                    <a class="btn primary" href="<?= e($backUrl) ?>">ยืม-คืนอุปกรณ์</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isEdit): ?>
            <section class="edit-card">
                <h2>แก้ไขผลตรวจสภาพ</h2>
                <form class="edit-form" id="editConditionForm">
                    <input type="hidden" name="action" value="update_condition">
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    <div><label for="condition_status">สถานะ</label><select id="condition_status" name="condition_status"><option value="good" <?= $status === 'good' ? 'selected' : '' ?>>ปกติ</option><option value="damaged" <?= $status === 'damaged' ? 'selected' : '' ?>>พบความเสียหาย</option><option value="needs_review" <?= $status === 'needs_review' ? 'selected' : '' ?>>รอตรวจสอบ</option></select></div>
                    <div><label for="condition_note">หมายเหตุ</label><textarea id="condition_note" name="condition_note" placeholder="ระบุหมายเหตุ (ถ้ามี)"><?= e($row['condition_note']) ?></textarea></div>
                    <button class="btn save-edit" type="submit">บันทึก</button>
                </form>
            </section>
        <?php endif; ?>
        <div class="card">
            <div class="session-grid">
                <section class="session">
                    <h2 class="session-title">ข้อมูลการยืม</h2>
                    <div class="meta">
                        <?php if (!empty($row['borrower_image_path'])): ?>
                            <div class="item" style="grid-column: 1 / -1; display: flex; align-items: center; gap: 14px; background: #fffdf5; border: 1.5px solid #ebd9a2;">
                                <img src="<?= e($row['borrower_image_path']) ?>" alt="รูปถ่ายผู้ยืม" style="width: 72px; height: 72px; object-fit: cover; border-radius: 50%; border: 2.5px solid var(--gold); box-shadow: 0 4px 10px rgba(75, 38, 131, 0.15); flex: none;">
                                <div>
                                    <span class="label" style="color: #b97a06; font-weight: 800;">รูปถ่ายผู้ยืมอุปกรณ์ (ยืนยันตัวตน)</span>
                                    <div class="value"><?= e($row['borrower_name']) ?></div>
                                    <div style="font-size: .80rem; color: var(--gray); margin-top: 2px;">บันทึกภาพถ่ายยืนยันตัวตนขณะส่งมอบอุปกรณ์</div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="item"><span class="label">รหัสรายการ</span><span class="value"><?= (int)$row['id'] ?></span></div>
                        <div class="item"><span class="label">ชื่อผู้ยืม</span><span class="value"><?= e($row['borrower_name']) ?></span></div>
                        <div class="item"><span class="label">เบอร์ผู้ยืม</span><span class="value"><?= e($row['borrower_phone'] ?: '-') ?></span></div>
                        <div class="item"><span class="label">ผู้ให้ยืม</span><span class="value"><?= e($row['lender_name'] ?: '-') ?></span></div>
                        <div class="item"><span class="label">ชื่ออุปกรณ์</span><span class="value"><?= e($row['item_name']) ?></span></div>
                        <div class="item"><span class="label">รายละเอียด / อุปกรณ์ย่อย</span><span class="value"><?= e($row['sub_item_name'] ?: '-') ?></span></div>
                        <div class="item"><span class="label">เลขผลิตภัณฑ์</span><span class="value"><?= e($row['serial_number'] ?: '-') ?></span></div>
                        <div class="item"><span class="label">ระยะวัน-เวลาการยืม</span><span class="value"><?= e($row['borrow_duration'] ?: '-') ?></span></div>
                        <div class="item"><span class="label">วันและเวลาส่งมอบ</span><span class="value"><?= date('d/m/Y H:i', strtotime($row['record_time'])) ?></span></div>
                    </div>
                </section>

                <section class="session">
                    <h2 class="session-title">ข้อมูลการคืน</h2>
                    <div class="meta">
                        <div class="item"><span class="label">วันและเวลารับคืน</span><span class="value"><?= $row['return_time'] ? date('d/m/Y H:i', strtotime($row['return_time'])) : 'ยังไม่มีหลักฐานรับคืน' ?></span></div>
                        <div class="item"><span class="label">ระยะเวลาการยืมทั้งหมด</span><span class="value"><?= $row['return_time'] ? e(format_elapsed_time($row['record_time'], $row['return_time'])) : 'ยังไม่มีหลักฐานรับคืน' ?></span></div>
                        <div class="item"><span class="label">ผลตรวจสภาพ</span><span class="value"><?= e($displayStatus) ?></span></div>
                        <div class="item"><span class="label">หมายเหตุ</span><span class="value"><?= e($row['condition_note'] ?: '-') ?></span></div>
                    </div>
                </section>
            </div>

            <div class="gallery-grid">
                <div class="panel">
                    <h3>รูปสภาพการส่งมอบ</h3>
                    <?php if ($borrowImages): ?>
                        <div class="img-list">
                            <?php foreach ($borrowImages as $src): ?>
                                <img src="<?= e($src) ?>" alt="รูปการส่งมอบ">
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="none">ไม่มีรูปสภาพการส่งมอบ</div>
                    <?php endif; ?>
                </div>
                <div class="panel">
                    <h3>หลักฐานสภาพขณะรับคืน</h3>
                    <?php if ($returnImages): ?>
                        <div class="img-list">
                            <?php foreach ($returnImages as $src): ?>
                                <img src="<?= e($src) ?>" alt="รูปการรับคืน">
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="none">ยังไม่มีหลักฐานสภาพขณะรับคืน</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php if ($isEdit): ?>
<script>
document.getElementById('editConditionForm').addEventListener('submit',async event=>{event.preventDefault();const response=await fetch('save_record.php',{method:'POST',body:new FormData(event.currentTarget)});if(response.ok){location.href='history_detail.php?id=<?= (int)$row['id'] ?>';return;}alert(await response.text());});
</script>
<?php endif; ?>
</body>
</html>

