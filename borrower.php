<?php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/auth.php';
require_auth_and_role(['borrower', 'admin', 'super_admin']);
$currentUser = current_user();
$deptInfo = $currentUser;
$deptSql = get_department_sql_condition($conn);

// ตรวจสอบและสร้างคอลัมน์ที่จำเป็นอัตโนมัติ
$columns = [
    'department' => "VARCHAR(150) NOT NULL DEFAULT 'แผนกโครงข่ายสื่อสารหลัก'",
    'dept_seq' => "INT NOT NULL DEFAULT 1",
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
foreach ($columns as $name => $definition) {
    $check = $conn->query("SHOW COLUMNS FROM transactions LIKE '$name'");
    if ($check && !$check->num_rows) $conn->query("ALTER TABLE transactions ADD COLUMN `$name` $definition");
}

$sort = strtolower($_GET['sort'] ?? 'desc');
$sort = in_array($sort, ['asc', 'desc'], true) ? $sort : 'desc';
$sortBy = strtolower($_GET['sort_by'] ?? 'id');
$sortableColumns = ['id' => 'dept_seq', 'borrow' => 'record_time', 'return' => 'return_time'];
$sortBy = array_key_exists($sortBy, $sortableColumns) ? $sortBy : 'id';
$nextSort = $sort === 'asc' ? 'desc' : 'asc';
$sortIcon = $sort === 'asc' ? '↑' : '↓';

$result = $conn->query("SELECT * FROM transactions WHERE action_type='Borrow' $deptSql ORDER BY `" . $sortableColumns[$sortBy] . "` " . strtoupper($sort) . ", id DESC");

if (!function_exists('first_image')) {
    function first_image($value) {
        $images = array_values(array_filter(array_map('trim', explode(',', (string)$value))));
        return $images[0] ?? '';
    }
}
if (!function_exists('image_list')) {
    function image_list($value) {
        return array_values(array_filter(array_map('trim', explode(',', (string)$value))));
    }
}
if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('render_borrower_rows')) {
function render_borrower_rows($result) {
    if ($result && $result->num_rows) {
        while ($row = $result->fetch_assoc()) {
            $borrowImages = image_list($row['image_path']);
            $returnImages = image_list($row['return_image_path']);
            $borrowImage = first_image($row['image_path']);
            $returnImage = first_image($row['return_image_path']);
            $dueDays = (int)$row['borrow_duration'];
            if ($dueDays < 1) $dueDays = 7;
            $isOverdue = !$row['return_time'] && (time() - strtotime($row['record_time']) >= $dueDays * 86400);
            $status = $row['return_time'] ? ($row['condition_status'] ?: 'needs_review') : ($isOverdue ? 'overdue' : 'pending');
            $labels = [
                'pending' => 'กำลังยืมอยู่',
                'good' => 'ปกติ',
                'damaged' => 'พบความเสียหาย',
                'needs_review' => 'รอตรวจสอบ',
                'overdue' => html_entity_decode('&#3648;&#3585;&#3636;&#3609;&#3619;&#3632;&#3618;&#3632;&#3648;&#3623;&#3621;&#3634;&#3585;&#3634;&#3619;&#3618;&#3639;&#3617;&#3607;&#3637;&#3656;&#3585;&#3635;&#3627;&#3609;&#3604;', ENT_QUOTES, 'UTF-8')
            ];

            $extStatus = $row['extension_status'] ?? 'none';
            $extDays = (int)($row['extension_days'] ?? 0);
            $extReason = $row['extension_reason'] ?? '';

            $photoData = e(json_encode([
                'id' => (int)$row['id'],
                'borrower_photo' => $row['borrower_image_path'] ?? '',
                'borrow' => $borrowImages,
                'return' => $returnImages,
                'borrower' => $row['borrower_name'],
                'item' => $row['item_name'],
                'status' => $labels[$status] ?? 'รอตรวจสอบ',
                'note' => $row['condition_note']
            ], JSON_UNESCAPED_UNICODE));

            $searchCorpus = mb_strtolower(implode(' ', [
                $row['dept_seq'] ?? $row['id'],
                $row['id'],
                $row['borrower_name'],
                $row['borrower_phone'] ?: '',
                $row['item_name'],
                $row['sub_item_name'] ?: '',
                $row['serial_number'] ?: '',
                $row['lender_name'] ?: '',
                $row['condition_note'] ?: '',
                $extReason,
                date('d/m/Y', strtotime($row['record_time'])),
                $row['return_time'] ? date('d/m/Y', strtotime($row['return_time'])) : '',
                $labels[$status] ?? ''
            ]));
            ?>
            <tr class="transaction-row" data-id="<?= (int)$row['id'] ?>" data-status="<?= e($status) ?>" data-returned="<?= $row['return_time'] ? '1' : '0' ?>" data-extension="<?= e($extStatus) ?>" data-search="<?= e($searchCorpus) ?>">
                <td class="col-seq"><?= (int)($row['dept_seq'] ?? $row['id']) ?></td>
                <td>
                    <div class="borrower-cell">
                        <?php if (!empty($row['borrower_image_path'])): ?>
                            <button type="button" class="borrower-avatar-btn view-borrower-photo" data-photo="<?= e($row['borrower_image_path']) ?>" data-name="<?= e($row['borrower_name']) ?>" title="คลิกเพื่อดูรูปผู้ยืม">
                                <img src="<?= e($row['borrower_image_path']) ?>" alt="รูปผู้ยืม">
                            </button>
                        <?php else: ?>
                            <div class="borrower-avatar-placeholder" title="ไม่มีรูปถ่ายผู้ยืม">
                                <?= mb_substr($row['borrower_name'], 0, 1) ?>
                            </div>
                        <?php endif; ?>
                        <div class="borrower-info">
                            <div class="person"><?= e($row['borrower_name']) ?></div>
                            <?php if ($row['borrower_phone']): ?>
                                <div class="muted"><?= e($row['borrower_phone']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="person"><?= e($row['item_name']) ?></div>
                    <?php if ($row['sub_item_name'] || $row['serial_number']): ?>
                        <div class="muted"><?= e($row['sub_item_name'] ?: $row['serial_number']) ?></div>
                    <?php endif; ?>
                </td>
                <td class="duration-cell" data-start="<?= e(date('c', strtotime($row['record_time']))) ?>" data-end="<?= e($row['return_time'] ? date('c', strtotime($row['return_time'])) : '') ?>" data-days="<?= $dueDays ?>">
                    <span class="duration-value"></span>
                </td>
                <td><?= date('d/m/Y H:i', strtotime($row['record_time'])) ?></td>
                <td>
                    <?php if ($borrowImage): ?>
                        <button class="photo-btn view-one" data-comparison='<?= $photoData ?>'>
                            <img src="<?= e($borrowImage) ?>" alt="รูปการส่งมอบ">
                            <span>ดูรูปส่งมอบ<?= count($borrowImages) > 1 ? ' (' . count($borrowImages) . ')' : '' ?></span>
                        </button>
                    <?php else: ?>
                        <span class="none">ไม่มีรูป</span>
                    <?php endif; ?>
                </td>
                <td><?= $row['return_time'] ? date('d/m/Y H:i', strtotime($row['return_time'])) : '—' ?></td>
                <td>
                    <?php if ($returnImage): ?>
                        <div class="return-photo-card">
                            <button class="photo-btn view-one" data-comparison='<?= $photoData ?>'>
                                <img src="<?= e($returnImage) ?>" alt="รูปการรับคืน">
                                <span>ดูรูปรับคืน<?= count($returnImages) > 1 ? ' (' . count($returnImages) . ')' : '' ?></span>
                            </button>
                        </div>
                    <?php else: ?>
                        <span class="none">ยังไม่มีหลักฐานรับคืน</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="state <?= e($status) ?>"><?= e($labels[$status] ?? 'รอตรวจสอบ') ?></span>
                    <?php if ($extStatus === 'pending'): ?>
                        <div class="ext-badge pending" title="<?= e($extReason) ?>">รออนุมัติ (+<?= $extDays ?> วัน)</div>
                    <?php elseif ($extStatus === 'approved'): ?>
                        <div class="ext-badge approved">ได้รับต่อเวลา +<?= $extDays ?> วัน</div>
                    <?php elseif ($extStatus === 'rejected'): ?>
                        <div class="ext-badge rejected">ไม่อนุมัติคำขอ</div>
                    <?php endif; ?>
                    <?php if ($row['condition_note']): ?>
                        <div class="muted"><?= e($row['condition_note']) ?></div>
                    <?php endif; ?>
                </td>
                <td class="col-manage">
                    <div class="manage">
                        <a class="detail-action" href="history_detail.php?id=<?= (int)$row['id'] ?>">รายละเอียด</a>
                        <?php if ($row['return_time']): ?>
                            <button type="button" class="edit-action btn-disabled" disabled>คืนแล้ว</button>
                        <?php elseif ($extStatus === 'pending'): ?>
                            <button type="button" class="edit-action btn-open-extend btn-pending" data-id="<?= (int)$row['id'] ?>" data-item="<?= e($row['item_name']) ?>" data-borrower="<?= e($row['borrower_name']) ?>" title="ส่งคำขอแล้ว รอเจ้าหน้าที่อนุมัติ (คลิกเพื่อแก้ไข/ส่งซ้ำ)">รออนุมัติ</button>
                        <?php else: ?>
                            <button type="button" class="edit-action btn-open-extend" data-id="<?= (int)$row['id'] ?>" data-item="<?= e($row['item_name']) ?>" data-borrower="<?= e($row['borrower_name']) ?>">ขอต่อเวลา</button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php
        }
    } else {
        echo '<tr class="empty-row"><td colspan="10">ยังไม่มีประวัติการยืม</td></tr>';
    }
}
}

if (isset($_GET['ajax_table'])) {
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Total-Count: ' . ($result ? (int)$result->num_rows : 0));
    render_borrower_rows($result);
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ITEMFLOW | ประวัติการยืมอุปกรณ์ (สำหรับผู้ยืม)</title>
    <style>
        /* PEA visual treatment - compact 80% scale (เหมือน history.php 100%) */
        :root {
            --purple: #4b2683;
            --deep: #250c50;
            --gold: #d9ac3b;
            --line: #e5dcef;
            --red: #ca4358;
            --green: #168457;
        }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            background: radial-gradient(circle at 5% 10%, #f9e8ac9c, transparent 19rem),
                        radial-gradient(circle at 96% 87%, #4b268340, transparent 24rem),
                        linear-gradient(135deg, #eee8f8 0%, #faf6ff 48%, #fffaf0 100%);
            font-family: "Segoe UI", Tahoma, sans-serif;
            color: #2e2840;
            padding: 16px 10px;
        }
        main.shell {
            position: relative;
            overflow: hidden;
            max-width: 98%;
            width: min(1680px, 98%);
            margin: 14px auto;
            background: #fffdfefa;
            border-radius: 22px;
            padding: 20px;
            border: 1px solid #fff;
            box-shadow: 0 20px 50px #3414571c;
        }
        main.shell:before {
            content: "";
            position: absolute;
            width: 320px;
            height: 320px;
            top: -240px;
            right: -90px;
            border: 24px solid #4b26830b;
            border-radius: 50%;
            box-shadow: 0 0 0 24px #4b268307, 0 0 0 48px #d9ac3b0b;
            pointer-events: none;
        }
        .shell .top {
            position: relative;
            margin: -20px -20px 18px;
            padding: 18px 22px;
            background: linear-gradient(118deg, #250c50, #4b2683 58%, #7653a7);
            color: #fff;
            border-bottom: 5px solid #d9ac3b;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .brand { display: flex; align-items: center; gap: 14px; }
        .pea-logo { width: 76px; height: 76px; flex: none; object-fit: contain; border-radius: 50%; filter: drop-shadow(0 4px 7px #13062a55); }
        .brand-name { font-size: .82rem; letter-spacing: .1em; color: #f8e9bb; font-weight: 800; }
        .brand-name b { display: inline-block; font-size: 1.25rem; letter-spacing: .06em; line-height: 1; margin-left: 8px; vertical-align: middle; text-shadow: 0 2px 8px #1807314a; color: #fff; }
        .dept-badge { display: inline-block; background: rgba(217, 172, 59, 0.22); border: 1px solid rgba(217, 172, 59, 0.45); color: #fae8a6; padding: 3px 10px; border-radius: 999px; font-size: .78rem; font-weight: 700; margin-left: 8px; vertical-align: middle; }
        .user-badge { display: inline-block; background: rgba(255, 255, 255, 0.15); border: 1px solid rgba(255, 255, 255, 0.28); color: #fff; padding: 3px 10px; border-radius: 999px; font-size: .78rem; font-weight: 700; margin-left: 6px; vertical-align: middle; }
        .top h1 { margin: 4px 0 0; color: #fff; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 900; letter-spacing: .02em; }
        .shell .subtitle { margin: 4px 0 0; color: #eee5f9; font-size: .86rem; }
        .actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .borrow-open {
            border: 0;
            border-radius: 13px;
            padding: 12px 22px;
            background: linear-gradient(135deg, #fae188, #d9ac3b);
            color: #2a174d;
            font: inherit;
            font-size: 1.02rem;
            font-weight: 900;
            letter-spacing: .02em;
            cursor: pointer;
            box-shadow: 0 5px 14px #25103a30;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            line-height: 1;
            transition: transform .18s, filter .18s, box-shadow .18s;
        }
        .actions .borrow-open:hover { filter: brightness(1.08); transform: translateY(-2px); }
        .btn-select-mode {
            border: 1px solid #fca5b0; border-radius: 13px; padding: 12px 22px; font: inherit; font-size: 1.02rem; font-weight: 900; letter-spacing: .02em; cursor: pointer; color: #fff; background: linear-gradient(135deg, #e54d68, #ba1e3d); box-shadow: 0 5px 14px #ba1e3d3e; display: inline-flex; align-items: center; justify-content: center; gap: 6px; line-height: 1; transition: transform .18s, filter .18s, box-shadow .18s;
        }
        .btn-select-mode:hover { filter: brightness(1.08); transform: translateY(-2px); }

        .table-wrap { overflow-x: auto; border: 1px solid var(--line); border-radius: 14px; background: #fff; box-shadow: 0 6px 18px #3414570a; max-width: 100%; }
        table { border-collapse: collapse; width: 100%; min-width: 1360px; table-layout: fixed; }
        th, td { overflow-wrap: anywhere; vertical-align: middle; }
        th { background: linear-gradient(135deg, #4b2683, #65439a); color: #fff; padding: 13px 8px; font-size: .88rem; font-weight: 800; white-space: nowrap; letter-spacing: .01em; }
        td { border-top: 1px solid var(--line); padding: 12px 8px; font-size: .88rem; text-align: center; }
        tr:nth-child(even) { background: #fcfaff; }
        .table-wrap tbody tr:hover { background: #fff9e9; }
        .sort-code { display: inline-flex; align-items: center; gap: 4px; color: #fff; text-decoration: none; font-weight: 900; font-size: .88rem; }
        .sort-code:hover { color: #fae8a6; }
        .sort-code .arrow { display: grid; place-items: center; width: 17px; height: 17px; border-radius: 50%; background: #ffffff22; color: #fae8a6; font-size: .92rem; }

        th:nth-child(1) { width: 65px; }
        th:nth-child(2) { width: 130px; }
        th:nth-child(3) { width: 135px; }
        th:nth-child(4) { width: 135px; }
        th:nth-child(5) { width: 125px; }
        th:nth-child(6) { width: 130px; }
        th:nth-child(7) { width: 125px; }
        th:nth-child(8) { width: 155px; }
        th:nth-child(9) { width: 155px; }
        th:nth-child(10), th.col-manage, td.col-manage { width: 165px; min-width: 165px; }

        .col-seq { font-size: 1.15rem; font-weight: 900; color: var(--purple); letter-spacing: -.02em; }
        .person { font-size: .95rem; font-weight: 800; color: var(--deep); }
        .muted { font-size: .80rem; color: #766a88; margin-top: 3px; }
        .duration-cell { font-size: .88rem; font-weight: 800; color: var(--deep); white-space: normal; line-height: 1.32; }
        .duration-value { display: block; font-size: .88rem; font-variant-numeric: tabular-nums; }
        .photo-btn { position: relative; border: 1px solid #d8cdea; border-radius: 10px; background: #fff; padding: 3px; cursor: pointer; box-shadow: 0 3px 8px #32184c10; transition: transform .18s, box-shadow .18s, filter .18s, border-color .18s; }
        .photo-btn:hover { transform: translateY(-2px); filter: brightness(1.05); box-shadow: 0 6px 14px #25103a28; border-color: #d9ac3b; }
        .photo-btn img { display: block; width: 98px; height: 74px; object-fit: cover; border-radius: 7px; margin: 0 auto; }
        .photo-btn span { display: block; font-size: .72rem; font-weight: 800; padding: 4px 2px 2px; color: var(--purple); white-space: nowrap; }
        .none { display: inline-block; color: #897d9b; font-size: .80rem; padding: 6px; }

        .return-photo-card { width: 124px; margin: 0 auto; padding: 5px; border: 1px solid #d8cdea; border-radius: 12px; background: #fff; box-shadow: 0 3px 8px #32184c10; }
        .return-photo-card .photo-btn { display: block; width: 100%; padding: 0; border: 0; background: transparent; box-shadow: none; }
        .return-photo-card .photo-btn img { width: 100%; height: 74px; border-radius: 8px; }
        .return-photo-card .photo-btn span { padding: 4px 2px 2px; font-size: .72rem; }

        .state {
            width: min(100%, 145px); min-height: 44px; padding: 8px 10px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 11px; font-size: .86rem; font-weight: 800; line-height: 1.25;
            box-shadow: 0 4px 10px #32184c16; animation: status-button-pulse 1.6s ease-in-out infinite;
        }
        .state.pending { background: linear-gradient(135deg, #ffe99c, #f2c746); color: #654400; }
        .state.good { background: linear-gradient(135deg, #28b677, #087648); color: white; }
        .state.damaged, .state.needs_review { background: linear-gradient(135deg, #e55353, #a81830); color: white; }
        .state.overdue { background: linear-gradient(135deg, #ff7373, #b31330); color: #fff; animation: overdue-alert .85s ease-in-out infinite; }
        @keyframes status-button-pulse { 0%, 100% { transform: scale(1); filter: brightness(1); box-shadow: 0 3px 8px #32184c14; } 50% { transform: scale(1.015); filter: brightness(1.06); box-shadow: 0 5px 12px #32184c24; } }
        @keyframes overdue-alert { 0%, 100% { filter: brightness(1); transform: scale(1); box-shadow: 0 3px 8px #8d102c44; } 50% { filter: brightness(.78); transform: scale(1.035); box-shadow: 0 0 0 4px #e83d5b38, 0 6px 14px #8d102c66; } }

        /* Manage Buttons (เหมือน history.php 100%) */
        .manage { display: flex; gap: 7px; justify-content: center; align-items: center; white-space: nowrap; }
        .manage a, .manage button {
            flex: 0 0 76px; width: 76px; min-height: 36px; display: inline-flex;
            justify-content: center; align-items: center; border-radius: 9px;
            font-size: .80rem; font-weight: 800; text-decoration: none; white-space: nowrap;
            box-shadow: 0 2px 7px #25103a20; border: 1px solid #d7c5ef; box-sizing: border-box;
            transition: transform .18s, filter .18s, box-shadow .18s; cursor: pointer;
        }
        .manage a:hover, .manage button:hover { transform: translateY(-2px); filter: brightness(1.06); box-shadow: 0 5px 12px #25103a2f; }
        .detail-action { background: #eee7fa; color: #4b2683; }
        .edit-action { background: linear-gradient(135deg, #f9db7a, #d9ac3b); color: #250c50; border-color: #e1b82f; }
        .edit-action.btn-pending { background: linear-gradient(135deg, #f0b830, #d4900a); color: #fff; border-color: #c48000; }
        .edit-action.btn-disabled { background: #eee7fa !important; color: #8e83a2 !important; border-color: #d8cde9 !important; cursor: not-allowed !important; opacity: .7 !important; transform: none !important; box-shadow: none !important; }

        /* Extension Badge */
        .ext-badge {
            display: inline-block; margin-top: 5px; padding: 4px 8px; border-radius: 7px;
            font-size: .74rem; font-weight: 800; line-height: 1.25;
        }
        .ext-badge.pending { background: #fff6e5; color: #a05c00; border: 1px solid #f6cf75; animation: status-button-pulse 1.6s ease-in-out infinite; }
        .ext-badge.approved { background: #eefbf4; color: #12794b; border: 1px solid #a8e5c8; }
        .ext-badge.rejected { background: #fff0f3; color: #a81c34; border: 1px solid #f9b7c3; }

        /* Filter Toolbar (เหมือน history.php) */
        .filter-toolbar {
            display: flex; justify-content: space-between; align-items: center;
            gap: 10px; flex-wrap: wrap; margin-bottom: 12px; padding: 9px 13px;
            background: #fffffff8; border: 1px solid var(--line); border-radius: 14px;
            box-shadow: 0 3px 10px #3414570a;
        }
        .filter-left { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
        .filter-label-wrap { display: inline-flex; align-items: center; gap: 5px; color: var(--deep); font-weight: 800; font-size: .82rem; }
        .filter-chips { display: inline-flex; align-items: center; gap: 5px; flex-wrap: wrap; }
        .chip {
            border: 1px solid #d9cded; border-radius: 18px; padding: 5px 11px;
            background: #fbf9ff; color: #56466e; font: inherit; font-size: .76rem;
            font-weight: 800; cursor: pointer; transition: transform .16s, filter .16s, background .16s, color .16s, box-shadow .16s;
        }
        .chip:hover { background: #f1e9fc; color: var(--purple); transform: translateY(-1px); }
        .chip.active { background: linear-gradient(135deg, var(--purple), #6b499e); color: #fff; border-color: var(--purple); box-shadow: 0 2px 7px #4b268335; }
        .chip.chip-pending.active { background: linear-gradient(135deg, #e6b225, #ca920b); border-color: #ca920b; box-shadow: 0 2px 7px #ca920b35; }
        .chip.chip-overdue.active { background: linear-gradient(135deg, #e24b64, #b51a37); border-color: #b51a37; box-shadow: 0 2px 7px #b51a3735; }
        .chip.chip-good.active { background: linear-gradient(135deg, #22a86c, #0d794b); border-color: #0d794b; box-shadow: 0 2px 7px #0d794b35; }
        .chip.chip-damaged.active { background: linear-gradient(135deg, #df4d5b, #a51829); border-color: #a51829; box-shadow: 0 2px 7px #a5182935; }
        .chip.chip-ext.active { background: linear-gradient(135deg, #e67e22, #ca640a); border-color: #ca640a; color: #fff; box-shadow: 0 2px 7px #ca640a35; }

        .filter-select {
            border: 1px solid #d9cded; border-radius: 9px; padding: 5px 8px;
            background: #fff; color: var(--deep); font: inherit; font-size: .78rem;
            font-weight: 800; cursor: pointer; outline: none; transition: border-color .18s, box-shadow .18s;
        }
        .filter-counter { font-size: .76rem; color: #766a88; margin-left: 4px; white-space: nowrap; font-weight: 700; }
        .filter-counter b { color: var(--purple); font-size: .84rem; font-weight: 900; }
        .filter-right { display: flex; align-items: center; gap: 8px; margin-left: auto; }

        .search-box { position: relative; display: inline-flex; align-items: center; width: 270px; max-width: 100%; }
        .search-icon { position: absolute; left: 10px; display: grid; place-items: center; color: var(--purple); pointer-events: none; opacity: .85; }
        .search-input {
            width: 100%; border: 1.5px solid #d9cded; border-radius: 10px; padding: 7px 28px 7px 32px;
            background: #fff; color: var(--deep); font: inherit; font-size: .80rem; font-weight: 700;
            outline: none; transition: border-color .18s, box-shadow .18s;
        }
        .search-input::placeholder { color: #9d91ae; font-weight: 600; }
        .search-input:focus { border-color: var(--gold); box-shadow: 0 0 0 3px #d9ac3b25; }
        .search-clear {
            position: absolute; right: 8px; border: 0; background: #ece5f7; color: var(--purple);
            width: 17px; height: 17px; border-radius: 50%; display: none; place-items: center;
            font-size: 10px; font-weight: 900; cursor: pointer; padding: 0; line-height: 1;
        }
        .no-results-cell { padding: 38px 16px !important; text-align: center; background: #fff !important; }
        .no-results { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; color: #766a88; }
        .no-results-icon { font-size: 2rem; line-height: 1; opacity: .6; }
        .no-results-text { font-size: .92rem; font-weight: 800; color: var(--deep); }
        .no-results-reset {
            margin-top: 2px; border: 1px solid #d4b045; border-radius: 8px; padding: 6px 14px;
            background: linear-gradient(135deg, #fae8a6, var(--gold)); color: var(--deep);
            font: inherit; font-size: .78rem; font-weight: 800; cursor: pointer;
            box-shadow: 0 2px 6px #d9ac3b24; transition: filter .18s, transform .18s;
        }

        /* Modal (เหมือน history.php) */
        .modal { position: fixed; inset: 0; background: #160d2599; display: none; align-items: center; justify-content: center; padding: 14px; z-index: 10; }
        .modal.show { display: flex; }
        .modal-box { width: min(880px, 96%); max-height: 90vh; overflow: auto; background: white; border-radius: 18px; padding: 18px; position: relative; }
        .close { position: absolute; right: 12px; top: 10px; width: 32px; height: 32px; border: 0; border-radius: 50%; background: var(--purple); color: #fff; font-size: 20px; cursor: pointer; }
        .modal-box h2 { margin: 0 38px 6px 0; color: var(--deep); font-size: 1.15rem; }
        .compare-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 13px; margin-top: 13px; }
        .image-panel { background: #f7f4fc; border: 1px solid var(--line); border-radius: 12px; padding: 10px; }
        .image-panel h3 { margin: 0 0 8px; color: var(--purple); font-size: .92rem; }
        .image-panel img { width: 100%; height: 240px; object-fit: contain; background: #eee; border-radius: 8px; }
        .image-panel .none { min-height: 140px; display: grid; place-items: center; }
        .note { margin-top: 12px; padding: 10px; background: #fff5d8; border-radius: 9px; color: #6a5216; font-size: .84rem; }

        /* Extend Modal */
        .extend-modal-box { width: min(480px, 94%); padding: 22px; }
        .form-label { display: block; font-weight: 800; font-size: .86rem; color: var(--deep); margin-bottom: 6px; text-align: left; }
        .input-days-group {
            display: flex;
            align-items: center;
            position: relative;
            width: 100%;
            margin-bottom: 8px;
        }
        .days-input {
            width: 100%;
            height: 48px;
            border: 1.5px solid #d9cded;
            border-radius: 11px;
            padding: 0 54px 0 14px;
            font: inherit;
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--deep);
            background: #fff;
            outline: none;
            transition: all .18s ease;
            box-sizing: border-box;
        }
        .days-input:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px #d9ac3b25;
        }
        .days-unit {
            position: absolute;
            right: 16px;
            font-size: .95rem;
            font-weight: 800;
            color: var(--purple);
            pointer-events: none;
        }
        .quick-days-wrap {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }
        .quick-days-label {
            font-size: .78rem;
            font-weight: 700;
            color: #796c90;
        }
        .btn-quick-day {
            border: 1px solid #d9cded;
            border-radius: 8px;
            padding: 5px 11px;
            background: #fbf9ff;
            color: var(--deep);
            font: inherit;
            font-size: .80rem;
            font-weight: 700;
            cursor: pointer;
            transition: all .14s ease;
        }
        .btn-quick-day:hover {
            background: #ede4f8;
            border-color: var(--purple);
            color: var(--purple);
        }
        .reason-textarea {
            width: 100%; border: 1.5px solid #d9cded; border-radius: 10px; padding: 10px;
            font: inherit; font-size: .86rem; outline: none; resize: vertical; box-sizing: border-box;
        }
        .reason-textarea:focus { border-color: var(--gold); box-shadow: 0 0 0 3px #d9ac3b25; }
        .btn-send-extend {
            width: 100%; border: 0; border-radius: 11px; padding: 12px;
            background: linear-gradient(135deg, #fae188, #d9ac3b); color: #2a174d;
            font: inherit; font-size: .95rem; font-weight: 900; cursor: pointer;
            box-shadow: 0 4px 12px #25103a20; margin-top: 14px; transition: all .18s;
        }
        /* Borrower Photo Avatar in Table */
        .borrower-cell { display: flex; align-items: center; gap: 9px; }
        .borrower-avatar-btn {
            flex: none; width: 42px; height: 42px; padding: 2px;
            border-radius: 50%; border: 2px solid var(--gold);
            background: #fff; cursor: pointer; position: relative;
            box-shadow: 0 2px 7px rgba(75, 38, 131, 0.15);
            transition: transform .18s, box-shadow .18s, border-color .18s;
        }
        .borrower-avatar-btn:hover {
            transform: scale(1.08); border-color: var(--purple);
            box-shadow: 0 4px 12px rgba(75, 38, 131, 0.3);
        }
        .borrower-avatar-btn img {
            width: 100%; height: 100%; object-fit: cover; border-radius: 50%; display: block;
        }
        .borrower-avatar-placeholder {
            flex: none; width: 42px; height: 42px; border-radius: 50%;
            background: linear-gradient(135deg, #ede4f8, #dfd0f4);
            border: 1.5px solid #d8cdea; color: var(--purple);
            display: grid; place-items: center; font-weight: 900; font-size: 15px;
        }
        .borrower-info { min-width: 0; }
        .modal-borrower-badge {
            display: flex; align-items: center; gap: 12px; padding: 10px 14px;
            background: #fbf9ff; border: 1.5px solid #e1d3f3; border-radius: 12px; margin-bottom: 12px;
        }
        .modal-borrower-badge img {
            width: 48px; height: 48px; object-fit: cover; border-radius: 50%; border: 2px solid var(--gold); flex: none;
        }

        @media(max-width: 650px) {
            html body { padding: 8px 6px; }
            .shell .top { margin: -16px -12px 14px; padding: 14px 12px; }
            .pea-logo { width: 56px; height: 56px; }
            .compare-grid { grid-template-columns: 1fr; }
            .image-panel img { height: 180px; }
            .filter-toolbar { flex-direction: column; align-items: stretch; gap: 8px; }
            .filter-right { margin-left: 0; width: 100%; }
            .search-box { width: 100%; }
        }
    </style>
</head>
<body>
<main class="shell">
    <div class="top">
        <div>
            <div class="brand">
                <img class="pea-logo" src="pea-logo.png" alt="ตราสัญลักษณ์ PEA">
                <div>
                    <div class="brand-name">PEA ASSET CARE <b>ITEMFLOW</b></div>
                    <h1>ประวัติการยืม-คืน</h1>
                    <p class="subtitle">ระบบสำหรับผู้ยืม (<?= e($currentUser['dept_name']) ?>)</p>
                </div>
            </div>
        </div>
        <div class="actions">
            <a href="login.php?logout=1" class="btn-select-mode" style="text-decoration:none;background:rgba(255,255,255,0.15);border-color:rgba(255,255,255,0.3);box-shadow:none;padding:12px 16px;" title="ออกจากระบบ / ย้อนกลับ">ย้อนกลับ</a>
        </div>
    </div>

    <?php if (!empty($_GET['registered'])): ?>
        <div style="background:linear-gradient(135deg, #e8f7f0, #f0fbf5);border:1px solid #b7e5ce;border-left:4px solid #177b58;padding:12px 18px;border-radius:12px;margin-bottom:16px;color:#177b58;font-weight:700;display:flex;align-items:center;justify-content:space-between;box-shadow:0 3px 10px rgba(23,123,88,0.08);">
            <span>ยินดีต้อนรับคุณ <?= e($currentUser['full_name']) ?>! ลงทะเบียนเข้าสู่ระบบผู้ยืมอุปกรณ์เรียบร้อยแล้ว</span>
            <button type="button" onclick="this.parentElement.remove()" style="border:0;background:transparent;color:#177b58;font-size:20px;cursor:pointer;font-weight:800;line-height:1;">&times;</button>
        </div>
    <?php endif; ?>

    <div class="filter-toolbar">
        <div class="filter-left">
            <div class="filter-label-wrap">
                <span class="filter-title">ตัวกรอง:</span>
            </div>
            <div class="filter-chips" id="filterChips">
                <button type="button" class="chip active" data-filter="all">ทั้งหมด</button>
                <button type="button" class="chip chip-pending" data-filter="pending">กำลังยืมอยู่</button>
                <button type="button" class="chip chip-overdue" data-filter="overdue">เกินกำหนด</button>
                <button type="button" class="chip chip-ext" data-filter="extension_pending">คำขอต่อเวลา</button>
                <button type="button" class="chip chip-good" data-filter="good">ปกติ</button>
                <button type="button" class="chip chip-damaged" data-filter="damaged">พบความเสียหาย</button>
            </div>
            <div class="filter-select-wrap">
                <select id="statusFilterSelect" class="filter-select" aria-label="เลือกตัวกรองสถานะ">
                    <option value="all">สถานะทั้งหมด</option>
                    <option value="pending">กำลังยืมอยู่</option>
                    <option value="overdue">เกินกำหนดส่ง</option>
                    <option value="extension_pending">คำขอต่อเวลาที่รออนุมัติ</option>
                    <option value="returned">คืนแล้วทั้งหมด</option>
                    <option value="good">คืนแล้ว (ปกติ)</option>
                    <option value="damaged">คืนแล้ว (พบความเสียหาย/รอตรวจ)</option>
                </select>
            </div>
            <span class="filter-counter" id="filterCounter">แสดง <b id="visibleCount"><?= $result ? $result->num_rows : 0 ?></b> จาก <span id="totalFilterCount"><?= $result ? $result->num_rows : 0 ?></span> รายการ</span>
        </div>
        <div class="filter-right">
            <div class="search-box" id="searchBox">
                <span class="search-icon" aria-hidden="true">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                </span>
                <input type="text" id="searchInput" class="search-input" placeholder="ค้นหาชื่อผู้ยืม, อุปกรณ์, เบอร์โทร..." autocomplete="off">
                <button type="button" id="searchClearBtn" class="search-clear" title="ล้างคำค้นหา" aria-label="ล้างคำค้นหา">&times;</button>
            </div>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th data-sort="id">ลำดับ</th>
                    <th>ผู้ยืม</th>
                    <th>อุปกรณ์</th>
                    <th>ระยะเวลาการยืม</th>
                    <th data-sort="borrow">วันและเวลาส่งมอบ</th>
                    <th>หลักฐานส่งมอบ</th>
                    <th data-sort="return">วันและเวลารับคืน</th>
                    <th>หลักฐานรับคืน</th>
                    <th>ผลการตรวจสภาพ</th>
                    <th class="col-manage">การจัดการ</th>
                </tr>
            </thead>
            <tbody id="borrowerTableBody">
                <tr id="noResultsRow" style="display:none;"><td colspan="10" class="no-results-cell"><div class="no-results"><div class="no-results-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></div><div class="no-results-text">ไม่พบรายการที่ตรงกับคำค้นหาหรือตัวกรอง</div><button type="button" class="no-results-reset" id="resetFilterBtn">ล้างตัวกรองและการค้นหา</button></div></td></tr>
                <?php render_borrower_rows($result); ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Modal: ยื่นคำขอต่อเวลา (เหมือน history.php) -->
<div class="modal" id="extendModal" aria-hidden="true">
    <div class="modal-box extend-modal-box">
        <button type="button" class="close" id="closeExtendModal">×</button>
        <h2>ยื่นคำขอต่อเวลายืมอุปกรณ์</h2>
        <form id="extendForm">
            <input type="hidden" id="extendTransactionId" name="id" value="">
            <div style="font-weight: 800; color: var(--purple); font-size: 1rem; margin-bottom: 14px; padding: 10px 14px; background: #f6f0fd; border-radius: 10px; border: 1px solid #e1d3f3;" id="extendItemName">-</div>
            
            <label class="form-label" for="extensionDaysInput">ระบุจำนวนวันที่ต้องการขอต่อเวลา:</label>
            <div class="input-days-group">
                <input type="number" id="extensionDaysInput" name="extension_days" class="days-input" min="1" max="365" step="1" value="1" placeholder="กรอกจำนวนวัน เช่น 3" required>
                <span class="days-unit">วัน</span>
            </div>
            <div class="quick-days-wrap">
                <span class="quick-days-label">เลือกด่วน:</span>
                <button type="button" class="btn-quick-day" data-days="1">+1 วัน</button>
                <button type="button" class="btn-quick-day" data-days="3">+3 วัน</button>
                <button type="button" class="btn-quick-day" data-days="5">+5 วัน</button>
                <button type="button" class="btn-quick-day" data-days="7">+7 วัน</button>
                <button type="button" class="btn-quick-day" data-days="14">+14 วัน</button>
                <button type="button" class="btn-quick-day" data-days="30">+30 วัน</button>
            </div>

            <label class="form-label" for="extensionReasonInput">เหตุผลในการขอต่อเวลา:</label>
            <textarea id="extensionReasonInput" name="extension_reason" class="reason-textarea" rows="3" placeholder="ระบุเหตุผล เช่น ติดภารกิจภาคสนามยังไม่เสร็จ, รอตรวจเช็คอุปกรณ์เพิ่มเติม..." required></textarea>
            
            <button type="submit" class="btn-send-extend" id="submitExtendBtn">ส่งคำขอต่อเวลาให้เจ้าหน้าที่</button>
        </form>
    </div>
</div>

<!-- Modal: ดูรูปภาพอุปกรณ์ (เหมือน history.php) -->
<div class="modal" id="modal">
    <div class="modal-box">
        <button class="close" aria-label="ปิด">×</button>
        <h2 id="modalTitle">รายละเอียดรูปภาพอุปกรณ์</h2>
        <div id="modalBorrowerBadge" class="modal-borrower-badge" style="display:none;"></div>
        <div class="compare-grid">
            <section class="image-panel">
                <h3>การส่งมอบ</h3>
                <div id="borrowPanel"></div>
            </section>
            <section class="image-panel">
                <h3>การรับคืน</h3>
                <div id="returnPanel"></div>
            </section>
        </div>
        <div class="note" id="modalNote"></div>
    </div>
</div>

<script>
// ==================== Sort Headers ====================
const activeSortBy = '<?= $sortBy ?>', activeSort = '<?= $sort ?>', nextSort = '<?= $nextSort ?>', activeSortIcon = '<?= $sortIcon ?>';
const sortKeys = { 'id': 'ลำดับ', 'borrow': 'วันและเวลาส่งมอบ', 'return': 'วันและเวลารับคืน' };
document.querySelectorAll('.table-wrap thead th[data-sort]').forEach(header => {
    const key = header.dataset.sort, label = sortKeys[key] || header.textContent.trim(), isActive = key === activeSortBy, direction = isActive ? nextSort : 'asc';
    header.innerHTML = `<a class="sort-code" href="?sort_by=${key}&sort=${direction}" title="เรียงตาม${label}">${label} <span class="arrow">${isActive ? activeSortIcon : '↕'}</span></a>`;
});

// ==================== Ticker คำนวณเวลา ====================
function updateDurations() {
    document.querySelectorAll('.duration-cell').forEach(cell => {
        const start = Date.parse(cell.dataset.start);
        const end = cell.dataset.end ? Date.parse(cell.dataset.end) : null;
        if (Number.isNaN(start)) return;
        let seconds = Math.max(0, Math.floor(((end ?? Date.now()) - start) / 1000));
        const days = Math.floor(seconds / 86400);
        seconds %= 86400;
        const hours = Math.floor(seconds / 3600);
        seconds %= 3600;
        const minutes = Math.floor(seconds / 60);
        seconds %= 60;
        const dayText = days ? `${days} วัน ` : '';
        const valEl = cell.querySelector('.duration-value');
        if (valEl) {
            valEl.innerHTML = `${dayText}${hours} ชม<br>${minutes} นาที ${seconds} วินาที`;
        }
        if (!end) {
            const row = cell.closest('tr');
            const pill = cell.parentElement ? cell.parentElement.querySelector('.state') : null;
            const overdue = Date.now() >= start + (Number(cell.dataset.days) || 7) * 86400000;
            if (row) row.dataset.status = overdue ? 'overdue' : 'pending';
            if (pill) {
                pill.classList.toggle('pending', !overdue);
                pill.classList.toggle('overdue', overdue);
                pill.textContent = overdue ? 'เกินระยะเวลาการยืมที่กำหนด' : 'กำลังยืมอยู่';
            }
        }
    });
}
updateDurations();
setInterval(updateDurations, 1000);

// ==================== Search & Filter ====================
const searchInput = document.getElementById('searchInput');
const searchClearBtn = document.getElementById('searchClearBtn');
const filterChips = document.querySelectorAll('.chip[data-filter]');
const statusFilterSelect = document.getElementById('statusFilterSelect');
const visibleCountEl = document.getElementById('visibleCount');
const totalFilterCountEl = document.getElementById('totalFilterCount');
const noResultsRow = document.getElementById('noResultsRow');
const resetFilterBtn = document.getElementById('resetFilterBtn');
let currentFilter = 'all', currentSearch = '';

function applyFilter() {
    const rows = document.querySelectorAll('#borrowerTableBody tr.transaction-row');
    let visibleCount = 0;
    const query = currentSearch.trim().toLowerCase();

    rows.forEach(row => {
        const status = row.dataset.status || '';
        const isReturned = row.dataset.returned === '1';
        const extStatus = row.dataset.extension || '';
        const searchText = row.dataset.search || '';

        let statusMatch = true;
        if (currentFilter === 'all') statusMatch = true;
        else if (currentFilter === 'pending') statusMatch = status === 'pending';
        else if (currentFilter === 'overdue') statusMatch = status === 'overdue';
        else if (currentFilter === 'extension_pending') statusMatch = extStatus === 'pending';
        else if (currentFilter === 'returned') statusMatch = isReturned;
        else if (currentFilter === 'good') statusMatch = isReturned && status === 'good';
        else if (currentFilter === 'damaged') statusMatch = isReturned && (status === 'damaged' || status === 'needs_review');

        let searchMatch = true;
        if (query) searchMatch = searchText.includes(query);

        const isVisible = statusMatch && searchMatch;
        row.style.display = isVisible ? '' : 'none';
        if (isVisible) visibleCount++;
    });

    if (visibleCountEl) visibleCountEl.textContent = visibleCount;
    if (totalFilterCountEl) totalFilterCountEl.textContent = rows.length;
    if (noResultsRow) noResultsRow.style.display = (rows.length > 0 && visibleCount === 0) ? '' : 'none';
    if (searchClearBtn) searchClearBtn.style.display = query ? 'grid' : 'none';
}

function resetFilters() {
    currentFilter = 'all'; currentSearch = '';
    if (searchInput) searchInput.value = '';
    if (statusFilterSelect) statusFilterSelect.value = 'all';
    filterChips.forEach(chip => chip.classList.toggle('active', chip.dataset.filter === 'all'));
    applyFilter();
}
if (resetFilterBtn) resetFilterBtn.addEventListener('click', resetFilters);
if (searchClearBtn) searchClearBtn.addEventListener('click', () => { searchInput.value = ''; currentSearch = ''; searchInput.focus(); applyFilter(); });
if (searchInput) searchInput.addEventListener('input', e => { currentSearch = e.target.value; applyFilter(); });
filterChips.forEach(chip => {
    chip.addEventListener('click', () => {
        filterChips.forEach(c => c.classList.remove('active'));
        chip.classList.add('active');
        currentFilter = chip.dataset.filter;
        if (statusFilterSelect) statusFilterSelect.value = currentFilter;
        applyFilter();
    });
});
if (statusFilterSelect) {
    statusFilterSelect.addEventListener('change', e => {
        currentFilter = e.target.value;
        filterChips.forEach(c => c.classList.toggle('active', c.dataset.filter === currentFilter));
        applyFilter();
    });
}

// ==================== Modal (ดูรูปภาพ) ====================
const modal = document.getElementById('modal');
const modalTitle = document.getElementById('modalTitle');
const borrowPanel = document.getElementById('borrowPanel');
const returnPanel = document.getElementById('returnPanel');
const modalNote = document.getElementById('modalNote');

function panel(el, images) {
    el.innerHTML = images && images.length ? `<img src="${images[0]}" alt="รูปภาพอุปกรณ์">${images.length > 1 ? `<div class="muted">แสดงรูปแรกจากทั้งหมด ${images.length} รูป</div>` : ''}` : '<div class="none">ยังไม่มีรูปภาพ</div>';
}
function openPhotoModal(data) {
    modalTitle.textContent = `รายละเอียดรูปภาพ: ${data.item} — ${data.borrower}`;
    const bBadge = document.getElementById('modalBorrowerBadge');
    if (bBadge) {
        if (data.borrower_photo) {
            bBadge.innerHTML = `<img src="${data.borrower_photo}" alt="รูปผู้ยืม"><div><b>ผู้ยืมอุปกรณ์:</b> ${data.borrower} <span style="color:#74648a;font-size:0.8rem;">(มีรูปถ่ายยืนยันตัวตน)</span></div>`;
            bBadge.style.display = 'flex';
        } else {
            bBadge.style.display = 'none';
        }
    }
    panel(borrowPanel, data.borrow);
    panel(returnPanel, data.return);
    modalNote.textContent = `ผลตรวจสภาพ: ${data.status}${data.note ? ' | หมายเหตุ: ' + data.note : ''}`;
    modal.classList.add('show');
}
function openBorrowerPhotoModal(photoSrc, borrowerName) {
    modalTitle.textContent = `รูปถ่ายผู้ยืม: ${borrowerName}`;
    const bBadge = document.getElementById('modalBorrowerBadge');
    if (bBadge) bBadge.style.display = 'none';
    borrowPanel.innerHTML = `<img src="${photoSrc}" alt="รูปถ่ายผู้ยืม" style="height:320px;object-fit:contain;border-radius:12px;">`;
    returnPanel.innerHTML = '<div class="none">รูปถ่ายยืนยันตัวตนผู้ยืมอุปกรณ์ขณะส่งมอบ</div>';
    modalNote.textContent = `ผู้ยืม: ${borrowerName}`;
    modal.classList.add('show');
}
document.querySelectorAll('.modal .close').forEach(btn => btn.addEventListener('click', () => btn.closest('.modal').classList.remove('show')));
document.querySelectorAll('.modal').forEach(overlay => overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('show'); }));

// ==================== Extend Modal ====================
const extendModal = document.getElementById('extendModal');
const extendForm = document.getElementById('extendForm');
const extendTransactionId = document.getElementById('extendTransactionId');
const extendItemName = document.getElementById('extendItemName');
const extensionDaysInput = document.getElementById('extensionDaysInput');
const extensionReasonInput = document.getElementById('extensionReasonInput');
const closeExtendModal = document.getElementById('closeExtendModal');

document.querySelectorAll('.btn-quick-day').forEach(btn => {
    btn.addEventListener('click', () => {
        extensionDaysInput.value = btn.dataset.days;
        extensionDaysInput.focus();
        extensionDaysInput.select();
    });
});

function openExtendModal(id, item, borrower) {
    extendTransactionId.value = id;
    extendItemName.textContent = `${item} — ผู้ยืม: ${borrower}`;
    extensionDaysInput.value = '1';
    extensionReasonInput.value = '';
    extendModal.classList.add('show');
    extensionDaysInput.focus();
    extensionDaysInput.select();
}
if (closeExtendModal) closeExtendModal.addEventListener('click', () => extendModal.classList.remove('show'));

if (extendForm) {
    extendForm.addEventListener('submit', async e => {
        e.preventDefault();
        const id = extendTransactionId.value;
        const days = parseInt(extensionDaysInput.value, 10);
        const reason = extensionReasonInput.value.trim();
        if (!days || days < 1) {
            alert('กรุณากรอกจำนวนวันที่ต้องการขอต่อเวลาอย่างน้อย 1 วัน');
            extensionDaysInput.focus();
            return;
        }
        if (!reason) {
            alert('กรุณากรอกเหตุผลในการขอต่อเวลา');
            extensionReasonInput.focus();
            return;
        }

        const submitBtn = document.getElementById('submitExtendBtn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'กำลังส่งคำขอ...';

        try {
            const fd = new FormData();
            fd.append('action', 'request_extension');
            fd.append('id', id);
            fd.append('extension_days', days);
            fd.append('extension_reason', reason);

            const res = await fetch('save_record.php', { method: 'POST', body: fd });
            const msg = await res.text();
            alert(msg);
            if (res.ok) {
                extendModal.classList.remove('show');
                pollBorrowerData(true);
            }
        } catch (err) {
            alert('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'ส่งคำขอต่อเวลาให้เจ้าหน้าที่';
        }
    });
}

// ==================== Event Delegation บนตาราง ====================
const tableBody = document.getElementById('borrowerTableBody');
if (tableBody) {
    tableBody.addEventListener('click', e => {
        const borrowerPhotoBtn = e.target.closest('.view-borrower-photo');
        if (borrowerPhotoBtn && borrowerPhotoBtn.dataset.photo) {
            e.preventDefault();
            openBorrowerPhotoModal(borrowerPhotoBtn.dataset.photo, borrowerPhotoBtn.dataset.name);
            return;
        }

        const photoBtn = e.target.closest('.view-one');
        if (photoBtn && photoBtn.dataset.comparison) {
            e.preventDefault();
            try { openPhotoModal(JSON.parse(photoBtn.dataset.comparison)); } catch (err) {}
            return;
        }

        const extendBtn = e.target.closest('.btn-open-extend');
        if (extendBtn) {
            e.preventDefault();
            openExtendModal(extendBtn.dataset.id, extendBtn.dataset.item, extendBtn.dataset.borrower);
            return;
        }
    });
}

// ==================== Real-time AJAX Polling (ทุก 3 วินาที) ====================
let pollTimeout = null;
async function pollBorrowerData(force = false) {
    if (!force && document.querySelector('.modal.show') !== null) {
        scheduleNextPoll(3000);
        return;
    }
    try {
        const url = new URL(location.href);
        url.searchParams.set('ajax_table', '1');
        url.searchParams.set('_t', Date.now());

        const res = await fetch(url.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store'
        });
        if (res.status === 401 || res.status === 403 || res.redirected) {
            window.location.href = 'login.php';
            return;
        }
        if (res.ok) {
            const html = await res.text();
            if (html.includes('<!DOCTYPE') || html.includes('<html') || html.includes('portal-header') || html.includes('login.php')) {
                window.location.href = 'login.php';
                return;
            }
            const tbody = document.getElementById('borrowerTableBody');
            const noResults = document.getElementById('noResultsRow');
            if (tbody) {
                tbody.innerHTML = '';
                if (noResults) tbody.appendChild(noResults);
                tbody.insertAdjacentHTML('beforeend', html);
                updateDurations();
                applyFilter();
            }
        }
    } catch (err) {
        console.warn('Poll error:', err);
    } finally {
        scheduleNextPoll(3000);
    }
}
function scheduleNextPoll(delay = 3000) {
    clearTimeout(pollTimeout);
    if (!document.hidden) {
        pollTimeout = setTimeout(pollBorrowerData, delay);
    }
}
document.addEventListener('visibilitychange', () => {
    if (document.hidden) clearTimeout(pollTimeout);
    else pollBorrowerData();
});
scheduleNextPoll(3000);
</script>
</body>
</html>
