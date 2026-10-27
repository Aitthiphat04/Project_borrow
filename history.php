<?php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/auth.php';
require_auth_and_role(['admin', 'super_admin']);
$currentUser = current_user();
$deptInfo = $currentUser;
$deptSql = get_department_sql_condition($conn);
$columns=['department'=>"VARCHAR(150) NOT NULL DEFAULT 'แผนกโครงข่ายสื่อสารหลัก'",'borrower_username'=>"VARCHAR(100) NOT NULL DEFAULT ''",'borrower_dept_name'=>"VARCHAR(150) NOT NULL DEFAULT ''",'dept_seq'=>"INT NOT NULL DEFAULT 1",'sub_item_name'=>"VARCHAR(150) NOT NULL DEFAULT ''",'serial_number'=>"VARCHAR(100) NOT NULL DEFAULT ''",'borrower_phone'=>"VARCHAR(30) NOT NULL DEFAULT ''",'borrow_duration'=>"VARCHAR(100) NOT NULL DEFAULT ''",'lender_name'=>"VARCHAR(100) NOT NULL DEFAULT ''",'borrower_image_path'=>"TEXT NULL",'return_image_path'=>"TEXT NULL",'return_time'=>"DATETIME NULL",'condition_status'=>"VARCHAR(30) NOT NULL DEFAULT 'pending'",'condition_note'=>"TEXT NULL",'extension_status'=>"VARCHAR(20) NOT NULL DEFAULT 'none'",'extension_days'=>"INT NOT NULL DEFAULT 0",'extension_reason'=>"TEXT NULL",'extension_request_time'=>"DATETIME NULL"];
foreach($columns as $name=>$definition){$check=$conn->query("SHOW COLUMNS FROM transactions LIKE '$name'");if($check && !$check->num_rows)$conn->query("ALTER TABLE transactions ADD COLUMN `$name` $definition");}
$isAllDeptsView = empty($deptSql) || (!empty($currentUser['is_super']) && (($_SESSION['super_admin_view_dept'] ?? 'all') === 'all') && (empty($_GET['dept_filter']) || $_GET['dept_filter'] === 'all'));
$seqColumn = $isAllDeptsView ? 'id' : 'dept_seq';

$sort = strtolower($_GET['sort'] ?? 'asc');
$sort = in_array($sort, ['asc', 'desc'], true) ? $sort : 'asc';
$sortBy = strtolower($_GET['sort_by'] ?? 'id');
$sortableColumns = ['id' => $seqColumn, 'borrow' => 'record_time', 'return' => 'return_time'];
$sortBy = array_key_exists($sortBy, $sortableColumns) ? $sortBy : 'id';
$nextSort = $sort === 'asc' ? 'desc' : 'asc';
$sortIcon = $sort === 'asc' ? '↑' : '↓';
$result=$conn->query("SELECT * FROM transactions WHERE action_type='Borrow' $deptSql ORDER BY `".$sortableColumns[$sortBy]."` ".strtoupper($sort).", id ASC");
if (!function_exists('first_image')) { function first_image($value){$images=array_values(array_filter(array_map('trim',explode(',',(string)$value))));return$images[0]??'';} }
if (!function_exists('image_list')) { function image_list($value){return array_values(array_filter(array_map('trim',explode(',',(string)$value))));} }
if (!function_exists('e')) { function e($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');} }
if (!function_exists('loan_duration')) {
function loan_duration($borrowTime,$returnTime=null){
    try{
        $start=new DateTimeImmutable($borrowTime);
        $end=$returnTime?new DateTimeImmutable($returnTime):new DateTimeImmutable('now');
        $diff=$start->diff($end);
        $parts=[];
        if($diff->y)$parts[]=$diff->y.' ปี';
        if($diff->m)$parts[]=$diff->m.' เดือน';
        if($diff->d)$parts[]=$diff->d.' วัน';
        if($diff->h)$parts[]=$diff->h.' ชั่วโมง';
        if($diff->i)$parts[]=$diff->i.' นาที';
        return $parts?implode(' ',array_slice($parts,0,2)):'น้อยกว่า 1 นาที';
    }catch(Exception $exception){return '-';}
}
}

if (!function_exists('render_transaction_rows')) {
function render_transaction_rows($result, $currentUser = null, $isAllDeptsView = null) {
    if ($currentUser === null && function_exists('current_user')) {
        $currentUser = current_user();
    }
    if ($isAllDeptsView === null) {
        $isAllDeptsView = !empty($currentUser['is_super']) && (($_SESSION['super_admin_view_dept'] ?? 'all') === 'all') && (empty($_GET['dept_filter']) || $_GET['dept_filter'] === 'all');
    }
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
            $photoData = e(json_encode(['id' => (int)$row['id'], 'borrower_photo' => $row['borrower_image_path'] ?? '', 'borrow' => $borrowImages, 'return' => $returnImages, 'borrower' => $row['borrower_name'], 'item' => $row['item_name'], 'status' => $labels[$status] ?? 'รอตรวจสอบ', 'note' => $row['condition_note']], JSON_UNESCAPED_UNICODE));
            $detailData = e(json_encode(['id' => $row['id'], 'borrower' => $row['borrower_name'], 'borrowerPhoto' => $row['borrower_image_path'] ?? '', 'phone' => $row['borrower_phone'], 'lender' => $row['lender_name'], 'item' => $row['item_name'], 'subItem' => $row['sub_item_name'], 'duration' => $row['borrow_duration'], 'borrowTime' => date('d/m/Y H:i', strtotime($row['record_time'])), 'returnTime' => $row['return_time'] ? date('d/m/Y H:i', strtotime($row['return_time'])) : 'ยังไม่มีหลักฐานรับคืน', 'status' => $labels[$status] ?? 'รอตรวจสอบ', 'note' => $row['condition_note'], 'borrow' => $borrowImages, 'return' => $returnImages], JSON_UNESCAPED_UNICODE));
            $dispSeq = $isAllDeptsView ? (int)$row['id'] : (int)($row['dept_seq'] ?? $row['id']);
            $seqTitle = $isAllDeptsView
                ? 'รหัสลำดับระบบ #' . (int)$row['id'] . ' (ลำดับในแผนก: #' . (int)($row['dept_seq'] ?? $row['id']) . ')'
                : 'ลำดับในแผนก #' . (int)($row['dept_seq'] ?? $row['id']) . ' (รหัสระบบ: #' . (int)$row['id'] . ')';
            $searchCorpus = mb_strtolower(implode(' ', [($row['department'] ?? ''), ($row['borrower_dept_name'] ?? ''), $dispSeq, ($row['dept_seq'] ?? $row['id']), $row['id'], $row['borrower_name'], $row['borrower_phone'] ?: '', $row['item_name'], $row['sub_item_name'] ?: '', $row['serial_number'] ?: '', $row['lender_name'] ?: '', $row['condition_note'] ?: '', date('d/m/Y', strtotime($row['record_time'])), $row['return_time'] ? date('d/m/Y', strtotime($row['return_time'])) : '', $labels[$status] ?? '']));
            ?>
<tr class="transaction-row" data-status="<?= e($status) ?>" data-returned="<?= $row['return_time'] ? '1' : '0' ?>" data-search="<?= e($searchCorpus) ?>"><td class="col-checkbox"><input type="checkbox" class="item-checkbox row-checkbox" value="<?= (int)$row['id'] ?>" data-item="<?= e($row['item_name']) ?>" data-borrower="<?= e($row['borrower_name']) ?>"></td><td class="col-seq" title="<?= e($seqTitle) ?>"><?= $dispSeq ?></td><td><div class="borrower-cell"><?php if (!empty($row['borrower_image_path'])): ?><button type="button" class="borrower-avatar-btn view-borrower-photo" data-photo="<?= e($row['borrower_image_path']) ?>" data-name="<?= e($row['borrower_name']) ?>" title="คลิกเพื่อดูรูปผู้ยืม"><img src="<?= e($row['borrower_image_path']) ?>" alt="รูปผู้ยืม"></button><?php else: ?><div class="borrower-avatar-placeholder" title="ไม่มีรูปถ่ายผู้ยืม"><?= mb_substr($row['borrower_name'], 0, 1) ?></div><?php endif; ?><div class="borrower-info"><div class="person"><?=e($row['borrower_name'])?></div><?php if($row['borrower_phone']):?><div class="muted"><?=e($row['borrower_phone'])?></div><?php endif;?></div></div></td><td><div class="person"><?=e($row['item_name'])?></div><?php if($row['sub_item_name'] || $row['serial_number']):?><div class="muted"><?=e($row['sub_item_name'] ?: $row['serial_number'])?></div><?php endif;?><?php if(!empty($row['department'])):?><div class="row-dept-badge"><?=e($row['department'])?></div><?php endif;?></td><td class="duration-cell" data-start="<?=e(date('c',strtotime($row['record_time'])))?>" data-end="<?=e($row['return_time']?date('c',strtotime($row['return_time'])):'')?>" data-days="<?=$dueDays?>"><span class="duration-value"></span></td><td><?=date('d/m/Y H:i',strtotime($row['record_time']))?></td><td><?php if($borrowImage):?><button class="photo-btn view-one" data-comparison='<?=$photoData?>'><img src="<?=e($borrowImage)?>" alt="รูปการส่งมอบ"><span>ดูหลักฐานส่งมอบ<?=count($borrowImages)>1?' ('.count($borrowImages).')':''?></span></button><?php else:?><span class="none">ไม่มีรูป</span><?php endif;?></td><td><?= $row['return_time']?date('d/m/Y H:i',strtotime($row['return_time'])):'—' ?></td><td><?php if($returnImage):?><div class="return-photo-card"><button class="photo-btn view-one" data-comparison='<?=$photoData?>'><img src="<?=e($returnImage)?>" alt="รูปการรับคืน"><span>ดูหลักฐานรับคืน<?=count($returnImages)>1?' ('.count($returnImages).')':''?></span></button></div><?php else:?><span class="none">ยังไม่มีหลักฐานรับคืน</span><button type="button" class="return-entry" data-id="<?= (int)$row['id'] ?>" data-borrower="<?=e($row['borrower_name'])?>" data-item="<?=e($row['item_name'])?>">&#43; บันทึกการรับคืน</button><?php endif;?></td><td><span class="state <?=e($status)?>"><?=e($labels[$status]??'รอตรวจสอบ')?></span><?php if(!empty($row['extension_status']) && $row['extension_status']==='pending'):?><div class="ext-badge pending">ขอต่อ +<?=(int)$row['extension_days']?> วัน</div><?php elseif(!empty($row['extension_status']) && $row['extension_status']==='approved'):?><div class="ext-badge approved">ขยายเวลา +<?=(int)$row['extension_days']?> วัน</div><?php endif;?><?php if($row['condition_note']):?><div class="muted"><?=e($row['condition_note'])?></div><?php endif;?></td><td class="col-manage"><div class="manage"><a class="detail-action" href="history_detail.php?id=<?= (int)$row['id'] ?>">รายละเอียด</a><a class="edit-action" href="history_detail.php?id=<?= (int)$row['id'] ?>&amp;edit=1">แก้ไข</a></div></td></tr>
<?php
        }
    } else {
        echo '<tr class="empty-row"><td colspan="11">ยังไม่มีประวัติการยืม</td></tr>';
    }
}
}

if (isset($_GET['ajax_table'])) {
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Total-Count: ' . ($result ? (int)$result->num_rows : 0));
    render_transaction_rows($result, $currentUser, $isAllDeptsView);
    exit;
}

?>
<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>ITEMFLOW | ประวัติการยืม-คืน</title><script src="theme.js"></script>
<style>
/* PEA visual treatment - compact 80% scale */
:root{--purple:#4b2683;--deep:#250c50;--gold:#d9ac3b;--line:#e5dcef;--red:#ca4358;--green:#168457}
*{box-sizing:border-box}
body{min-height:100vh;margin:0;background:radial-gradient(circle at 5% 10%,#f9e8ac9c,transparent 19rem),radial-gradient(circle at 96% 87%,#4b268340,transparent 24rem),linear-gradient(135deg,#eee8f8 0%,#faf6ff 48%,#fffaf0 100%);font-family:"Segoe UI",Tahoma,sans-serif;color:#2e2840;padding:16px 10px}
main.shell{position:relative;overflow:hidden;max-width:98%;width:min(1680px,98%);margin:14px auto;background:#fffdfefa;border-radius:22px;padding:20px;border:1px solid #fff;box-shadow:0 20px 50px #3414571c}
main.shell:before{content:"";position:absolute;width:320px;height:320px;top:-240px;right:-90px;border:24px solid #4b26830b;border-radius:50%;box-shadow:0 0 0 24px #4b268307,0 0 0 48px #d9ac3b0b;pointer-events:none}
.shell .top{position:relative;margin:-20px -20px 18px;padding:18px 22px;background:linear-gradient(118deg,#250c50,#4b2683 58%,#7653a7);color:#fff;border-bottom:5px solid #d9ac3b;border-radius:20px 20px 0 0;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.brand{display:flex;align-items:center;gap:14px}
.pea-logo{width:76px;height:76px;flex:none;object-fit:contain;border-radius:50%;filter:drop-shadow(0 4px 7px #13062a55)}
.brand-name{font-size:.82rem;letter-spacing:.1em;color:#f8e9bb;font-weight:800}
.brand-name b{display:inline-block;font-size:1.25rem;letter-spacing:.06em;line-height:1;margin-left:8px;vertical-align:middle;text-shadow:0 2px 8px #1807314a;color:#fff}
.dept-badge{display:inline-block;background:rgba(217,172,59,0.22);border:1px solid rgba(217,172,59,0.45);color:#fae8a6;padding:3px 10px;border-radius:999px;font-size:.78rem;font-weight:700;margin-left:8px;vertical-align:middle}
.user-badge{display:inline-block;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.28);color:#fff;padding:3px 10px;border-radius:999px;font-size:.78rem;font-weight:700;margin-left:6px;vertical-align:middle}
.top h1{margin:4px 0 0;color:#fff;font-size:clamp(1.4rem,2.5vw,1.85rem);font-weight:900;letter-spacing:.02em}
.shell .subtitle{margin:4px 0 0;color:#eee5f9;font-size:.86rem}
.actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.borrow-open{border:0;border-radius:13px;padding:12px 22px;background:linear-gradient(135deg,#fae188,#d9ac3b);color:#2a174d;font:inherit;font-size:1.02rem;font-weight:900;letter-spacing:.02em;cursor:pointer;box-shadow:0 5px 14px #25103a30;display:inline-flex;align-items:center;justify-content:center;gap:6px;line-height:1;transition:transform .18s,filter .18s,box-shadow .18s}
.btn-select-mode{border:1px solid #fca5b0;border-radius:13px;padding:12px 22px;font:inherit;font-size:1.02rem;font-weight:900;letter-spacing:.02em;cursor:pointer;color:#fff;background:linear-gradient(135deg,#e54d68,#ba1e3d);box-shadow:0 5px 14px #ba1e3d3e;display:inline-flex;align-items:center;justify-content:center;gap:6px;line-height:1;transition:transform .18s,filter .18s,box-shadow .18s}
.select-mode .btn-select-mode{background:linear-gradient(135deg,#67587d,#403254);border-color:#8e7fa6;box-shadow:0 5px 14px #250c5030}
.actions .borrow-open:hover,.btn-select-mode:hover{filter:brightness(1.08);transform:translateY(-2px)}
.batch-bar{display:none;position:fixed;bottom:20px;left:50%;transform:translateX(-50%);width:min(960px,94%);z-index:99;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:12px 22px;border-radius:18px;background:rgba(255,255,255,0.96);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border:2px solid #f3b5c4;box-shadow:0 16px 42px rgba(122,21,50,0.22),0 4px 14px rgba(75,38,131,0.12);animation:slideUpFloat .26s cubic-bezier(0.16,1,0.3,1)}
.select-mode .batch-bar{display:flex}
.select-mode .table-wrap{margin-bottom:80px}
@keyframes slideUpFloat{from{opacity:0;transform:translate(-50%,24px) scale(0.96)}to{opacity:1;transform:translate(-50%,0) scale(1)}}
.batch-left,.batch-right{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.select-all-label{display:inline-flex;align-items:center;gap:8px;font-weight:800;color:var(--deep);cursor:pointer;user-select:none;font-size:.86rem}
.batch-count{display:inline-flex;align-items:center;padding:5px 12px;border-radius:16px;background:#fff;border:1px solid #e1d2ee;font-size:.80rem;color:var(--purple);font-weight:700}
.batch-count b{color:#982038;margin:0 4px;font-size:.96rem}
.batch-btn{border:0;border-radius:10px;padding:9px 16px;font:inherit;font-size:.84rem;font-weight:800;cursor:pointer;white-space:nowrap;transition:transform .18s,filter .18s,box-shadow .18s}
.batch-delete{background:linear-gradient(135deg,#ff4b68,#cf2044);color:#fff;box-shadow:0 3px 12px #cf204435}
.batch-delete:disabled{background:#e3dce8!important;color:#9b92a6!important;cursor:not-allowed!important;box-shadow:none!important;transform:none!important}
.batch-delete:not(:disabled):hover{transform:translateY(-2px);filter:brightness(1.08);box-shadow:0 6px 18px #cf204455}
.batch-delete:not(:disabled){animation:deletePulse 1.8s infinite}
@keyframes deletePulse{0%,100%{box-shadow:0 4px 12px #cf204435}50%{box-shadow:0 6px 20px #cf204460}}
.batch-cancel{background:#eee7fa;color:var(--deep);border:1px solid #d3c4ea}
.batch-cancel:hover{background:#e2d6f5}
.table-wrap{overflow-x:auto;border:1px solid var(--line);border-radius:14px;background:#fff;box-shadow:0 6px 18px #3414570a;max-width:100%}
table{border-collapse:collapse;width:100%;min-width:1360px;table-layout:fixed}
th,td{overflow-wrap:anywhere;vertical-align:middle}
th{background:linear-gradient(135deg,#4b2683,#65439a);color:#fff;padding:13px 8px;font-size:.88rem;font-weight:800;white-space:nowrap;letter-spacing:.01em}
td{border-top:1px solid var(--line);padding:12px 8px;font-size:.88rem;text-align:center}
tr:nth-child(even){background:#fcfaff}
.table-wrap tbody tr:hover{background:#fff9e9}
.sort-code{display:inline-flex;align-items:center;gap:4px;color:#fff;text-decoration:none;font-weight:900;font-size:.88rem}
.sort-code:hover{color:#fae8a6}
.sort-code .arrow{display:grid;place-items:center;width:17px;height:17px;border-radius:50%;background:#ffffff22;color:#fae8a6;font-size:.92rem}
th:nth-child(1){width:42px}
th:nth-child(2){width:65px}
th:nth-child(3){width:130px}
th:nth-child(4){width:135px}
th:nth-child(5){width:135px}
th:nth-child(6){width:125px}
th:nth-child(7){width:130px}
th:nth-child(8){width:125px}
th:nth-child(9){width:155px}
th:nth-child(10){width:155px}
th:nth-child(11),th.col-manage,td.col-manage{width:165px;min-width:165px}
.col-checkbox{display:none;width:42px;text-align:center;vertical-align:middle;padding:6px 3px}
.select-mode .col-checkbox{display:table-cell}
.row-checkbox{width:17px;height:17px;cursor:pointer;accent-color:var(--purple);vertical-align:middle;margin:0}
.select-mode tr.row-selected{background:#fff1f4!important}
.select-mode tbody tr{cursor:pointer}
.col-seq{font-size:1.15rem;font-weight:900;color:var(--purple);letter-spacing:-.02em}
.row-dept-badge{display:inline-block;padding:2px 7px;background:#f1e9fc;color:var(--purple);border:1px solid #d9cded;border-radius:6px;font-size:.70rem;font-weight:800;margin-top:3px;max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.borrower-dept-badge{display:inline-flex;align-items:center;gap:4px;font-size:.72rem;font-weight:700;color:#533e70;background:#f3ecfb;border:1px solid #dfd0f2;border-radius:6px;padding:2px 6px;margin-top:4px;max-width:100%;line-height:1.25}
.borrower-dept-badge.cross-dept{color:#9c4100;background:#fff5e6;border-color:#ffd8a8}
.cross-dept-tag{background:linear-gradient(135deg,#e65100,#c83200);color:#fff;font-size:.62rem;font-weight:800;padding:1px 5px;border-radius:4px;letter-spacing:.02em;white-space:nowrap}
.person{font-size:.95rem;font-weight:800;color:var(--deep)}
.muted{font-size:.80rem;color:#766a88;margin-top:3px}
.duration-cell{font-size:.88rem;font-weight:800;color:var(--deep);white-space:normal;line-height:1.32}
.duration-value{display:block;font-size:.88rem;font-variant-numeric:tabular-nums}
.duration-hint{display:block;margin-top:4px;color:#75658b;font-size:.76rem}
.photo-btn{position:relative;border:1px solid #d8cdea;border-radius:10px;background:#fff;padding:3px;cursor:pointer;box-shadow:0 3px 8px #32184c10;transition:transform .18s,box-shadow .18s,filter .18s,border-color .18s}
.photo-btn:hover{transform:translateY(-2px);filter:brightness(1.05);box-shadow:0 6px 14px #25103a28;border-color:#d9ac3b}
.photo-btn img{display:block;width:98px;height:74px;object-fit:cover;border-radius:7px;margin:0 auto}
.photo-btn span{display:block;font-size:.72rem;font-weight:800;padding:4px 2px 2px;color:var(--purple);white-space:nowrap}
.none{display:inline-block;color:#897d9b;font-size:.80rem;padding:6px}
.return-photo-card{width:124px;margin:0 auto;padding:5px;border:1px solid #d8cdea;border-radius:12px;background:#fff;box-shadow:0 3px 8px #32184c10}
.return-photo-card .photo-btn{display:block;width:100%;padding:0;border:0;background:transparent;box-shadow:none}
.return-photo-card .photo-btn img{width:100%;height:74px;border-radius:8px}
.return-photo-card .photo-btn span{padding:4px 2px 2px;font-size:.72rem}
.return-photo-card .return-entry{display:none}
.return-entry{border:0;border-radius:8px;padding:7px 11px;background:linear-gradient(135deg,#f9df83,#d9ac3b);color:#2a174d;font:inherit;font-size:.78rem;font-weight:900;cursor:pointer;box-shadow:0 4px 10px #25103a1e;display:block;margin:7px auto 0;transition:transform .18s,filter .18s}
.return-entry:hover{filter:brightness(1.08);transform:translateY(-2px)}
.state{width:min(100%,145px);min-height:44px;padding:8px 10px;display:inline-flex;align-items:center;justify-content:center;border-radius:11px;font-size:.86rem;font-weight:800;line-height:1.25;box-shadow:0 4px 10px #32184c16;animation:status-button-pulse 1.6s ease-in-out infinite}
.state.pending{background:linear-gradient(135deg,#ffe99c,#f2c746);color:#654400}
.state.good{background:linear-gradient(135deg,#28b677,#087648);color:white}
.state.damaged,.state.needs_review{background:linear-gradient(135deg,#e55353,#a81830);color:white}
.state.overdue{background:linear-gradient(135deg,#ff7373,#b31330);color:#fff;animation:overdue-alert .85s ease-in-out infinite}
@keyframes status-button-pulse{0%,100%{transform:scale(1);filter:brightness(1);box-shadow:0 3px 8px #32184c14}50%{transform:scale(1.015);filter:brightness(1.06);box-shadow:0 5px 12px #32184c24}}
@keyframes overdue-alert{0%,100%{filter:brightness(1);transform:scale(1);box-shadow:0 3px 8px #8d102c44}50%{filter:brightness(.78);transform:scale(1.035);box-shadow:0 0 0 4px #e83d5b38,0 6px 14px #8d102c66}}
@media(prefers-reduced-motion:reduce){.state,.state.overdue{animation:none}}
.ext-badge{display:inline-block;margin-top:5px;padding:4px 8px;border-radius:7px;font-size:.74rem;font-weight:800;line-height:1.25}
.ext-badge.pending{background:#fff6e5;color:#a05c00;border:1px solid #f6cf75;animation:status-button-pulse 1.6s ease-in-out infinite}
.ext-badge.approved{background:#eefbf4;color:#12794b;border:1px solid #a8e5c8}
.ext-badge.rejected{background:#fff0f3;color:#a81c34;border:1px solid #f9b7c3}
.manage{display:flex;gap:7px;justify-content:center;align-items:center;white-space:nowrap}
.manage a{flex:0 0 76px;width:76px;min-height:36px;display:inline-flex;justify-content:center;align-items:center;border-radius:9px;font-size:.80rem;font-weight:800;text-decoration:none;white-space:nowrap;box-shadow:0 2px 7px #25103a20;border:1px solid #d7c5ef;box-sizing:border-box;transition:transform .18s,filter .18s,box-shadow .18s}
.manage a:hover{transform:translateY(-2px);filter:brightness(1.06);box-shadow:0 5px 12px #25103a2f}
.detail-action{background:#eee7fa;color:#4b2683}
.edit-action{background:linear-gradient(135deg,#f9db7a,#d9ac3b);color:#250c50;border-color:#e1b82f}
.edit-link{display:inline-flex;align-items:center;justify-content:center;margin:0 0 0 5px;padding:8px 10px;border-radius:8px;background:linear-gradient(135deg,#f9db7a,#d9ac3b);color:#250c50;font-size:.78rem;font-weight:800;line-height:1;text-decoration:none;vertical-align:middle;box-shadow:0 3px 8px #9a702426;transition:transform .18s}
.edit-link:hover{transform:translateY(-1px);filter:brightness(1.04)}
.compare{display:inline-flex;align-items:center;vertical-align:middle}
.actions button,.actions a,.photo-btn,.manage a,.close,.sort-code{transition:transform .18s ease,box-shadow .18s ease,filter .18s ease,border-color .18s ease;transform-origin:center}
.actions button:focus-visible,.actions a:focus-visible,.photo-btn:focus-visible,.manage a:focus-visible,.close:focus-visible,.sort-code:focus-visible{outline:2.5px solid #f5c75a;outline-offset:2.5px}
.modal{position:fixed;inset:0;background:#160d2599;display:none;align-items:center;justify-content:center;padding:14px;z-index:10}
.modal.show{display:flex}
.modal-box{width:min(880px,96%);max-height:90vh;overflow:auto;background:white;border-radius:18px;padding:18px;position:relative}
.close{position:absolute;right:12px;top:10px;width:32px;height:32px;border:0;border-radius:50%;background:var(--purple);color:#fff;font-size:20px;cursor:pointer}
.modal-box h2{margin:0 38px 6px 0;color:var(--deep);font-size:1.15rem}
.compare-grid{display:grid;grid-template-columns:1fr 1fr;gap:13px;margin-top:13px}
.image-panel{background:#f7f4fc;border:1px solid var(--line);border-radius:12px;padding:10px}
.image-panel h3{margin:0 0 8px;color:var(--purple);font-size:.92rem}
.image-panel img{width:100%;height:240px;object-fit:contain;background:#eee;border-radius:8px}
.image-panel .none{min-height:140px;display:grid;place-items:center}
.note{margin-top:12px;padding:10px;background:#fff5d8;border-radius:9px;color:#6a5216;font-size:.84rem}
.detail-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-top:13px}
.detail-item{padding:9px;background:#f7f4fc;border-radius:9px;text-align:left}
.detail-item b{display:block;color:#74648a;font-size:.74rem;margin-bottom:3px}
.detail-item span{font-weight:700;font-size:.86rem;color:var(--deep)}
.modal-add-return{display:block;margin:8px 0 0 auto;border:1px solid #dfbb49;border-radius:8px;padding:7px 12px;background:linear-gradient(135deg,#f9df83,#d9ac3b);color:#29164c;font:inherit;font-size:.80rem;font-weight:900;cursor:pointer;box-shadow:0 3px 8px #25103a1c;transition:transform .18s ease,filter .18s ease,box-shadow .18s ease}
.modal-add-return:hover{transform:translateY(-2px);filter:brightness(1.06);box-shadow:0 5px 12px #25103a28}
#formModal{z-index:10}
.form-modal-box{width:min(860px,96vw);height:min(780px,94vh);padding:16px;overflow:hidden}
.form-modal-box h2{padding-right:38px;margin-bottom:10px;font-size:1.15rem}
.form-modal-box iframe{display:block;width:100%;height:calc(100% - 42px);border:0;border-radius:12px;background:#fff}
.form-close{z-index:2;font-size:0;position:absolute;width:32px;height:32px;display:grid;place-items:center}
.form-close:before,.form-close:after{content:"";position:absolute;width:15px;height:2px;border-radius:2px;background:#fff;transform:rotate(45deg)}
.form-close:after{transform:rotate(-45deg)}

/* Filter Toolbar & Corner Search */
.filter-toolbar{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;padding:9px 13px;background:#fffffff8;border:1px solid var(--line);border-radius:14px;box-shadow:0 3px 10px #3414570a}
.filter-left{display:flex;align-items:center;gap:7px;flex-wrap:wrap}
.filter-label-wrap{display:inline-flex;align-items:center;gap:5px;color:var(--deep);font-weight:800;font-size:.82rem}
.filter-icon{font-size:.9rem}
.filter-chips{display:inline-flex;align-items:center;gap:5px;flex-wrap:wrap}
.chip{border:1px solid #d9cded;border-radius:18px;padding:5px 11px;background:#fbf9ff;color:#56466e;font:inherit;font-size:.76rem;font-weight:800;cursor:pointer;transition:transform .16s,filter .16s,background .16s,color .16s,box-shadow .16s}
.chip:hover{background:#f1e9fc;color:var(--purple);transform:translateY(-1px)}
.chip.active{background:linear-gradient(135deg,var(--purple),#6b499e);color:#fff;border-color:var(--purple);box-shadow:0 2px 7px #4b268335}
.chip.chip-pending.active{background:linear-gradient(135deg,#e6b225,#ca920b);border-color:#ca920b;box-shadow:0 2px 7px #ca920b35}
.chip.chip-overdue.active{background:linear-gradient(135deg,#e24b64,#b51a37);border-color:#b51a37;box-shadow:0 2px 7px #b51a3735}
.chip.chip-good.active{background:linear-gradient(135deg,#22a86c,#0d794b);border-color:#0d794b;box-shadow:0 2px 7px #0d794b35}
.chip.chip-damaged.active{background:linear-gradient(135deg,#df4d5b,#a51829);border-color:#a51829;box-shadow:0 2px 7px #a5182935}
.filter-select-wrap{display:inline-flex;align-items:center}
.filter-select{border:1px solid #d9cded;border-radius:9px;padding:5px 8px;background:#fff;color:var(--deep);font:inherit;font-size:.78rem;font-weight:800;cursor:pointer;outline:none;transition:border-color .18s,box-shadow .18s}
.filter-select:focus{border-color:var(--gold);box-shadow:0 0 0 3px #d9ac3b25}
.filter-counter{font-size:.76rem;color:#766a88;margin-left:4px;white-space:nowrap;font-weight:700}
.filter-counter b{color:var(--purple);font-size:.84rem;font-weight:900}
.filter-right{display:flex;align-items:center;gap:8px;margin-left:auto}
.search-box{position:relative;display:inline-flex;align-items:center;width:270px;max-width:100%}
.search-icon{position:absolute;left:10px;display:grid;place-items:center;color:var(--purple);pointer-events:none;opacity:.85}
.search-input{width:100%;border:1.5px solid #d9cded;border-radius:10px;padding:7px 28px 7px 32px;background:#fff;color:var(--deep);font:inherit;font-size:.80rem;font-weight:700;outline:none;transition:border-color .18s,box-shadow .18s}
.search-input::placeholder{color:#9d91ae;font-weight:600}
.search-input:focus{border-color:var(--gold);box-shadow:0 0 0 3px #d9ac3b25}
.search-clear{position:absolute;right:8px;border:0;background:#ece5f7;color:var(--purple);width:17px;height:17px;border-radius:50%;display:none;place-items:center;font-size:10px;font-weight:900;cursor:pointer;padding:0;line-height:1}
.search-clear:hover{background:#d6c6f0;color:var(--deep)}
.no-results-cell{padding:38px 16px!important;text-align:center;background:#fff!important}
.no-results{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:#766a88}
.no-results-icon{font-size:2rem;line-height:1;opacity:.6}
.no-results-text{font-size:.92rem;font-weight:800;color:var(--deep)}
.no-results-reset{margin-top:2px;border:1px solid #d4b045;border-radius:8px;padding:6px 14px;background:linear-gradient(135deg,#fae8a6,var(--gold));color:var(--deep);font:inherit;font-size:.78rem;font-weight:800;cursor:pointer;box-shadow:0 2px 6px #d9ac3b24;transition:filter .18s,transform .18s}
.no-results-reset:hover{filter:brightness(1.06);transform:translateY(-1px)}
.borrower-cell{display:flex;align-items:center;gap:9px}
.borrower-avatar-btn{flex:none;width:42px;height:42px;padding:2px;border-radius:50%;border:2px solid var(--gold);background:#fff;cursor:pointer;position:relative;box-shadow:0 2px 7px rgba(75,38,131,0.15);transition:transform .18s,box-shadow .18s,border-color .18s}
.borrower-avatar-btn:hover{transform:scale(1.08);border-color:var(--purple);box-shadow:0 4px 12px rgba(75,38,131,0.3)}
.borrower-avatar-btn img{width:100%;height:100%;object-fit:cover;border-radius:50%;display:block}
.borrower-avatar-placeholder{flex:none;width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#ede4f8,#dfd0f4);border:1.5px solid #d8cdea;color:var(--purple);display:grid;place-items:center;font-weight:900;font-size:15px}
.borrower-info{min-width:0}
.modal-borrower-badge{display:flex;align-items:center;gap:12px;padding:10px 14px;background:#fbf9ff;border:1.5px solid #e1d3f3;border-radius:12px;margin-bottom:12px}
.modal-borrower-badge img{width:48px;height:48px;object-fit:cover;border-radius:50%;border:2px solid var(--gold);flex:none}
@media(max-width:650px){html body{padding:8px 6px}.shell .top{margin:-16px -12px 14px;padding:14px 12px}.pea-logo{width:56px;height:56px}.compare-grid,.detail-grid{grid-template-columns:1fr}.image-panel img{height:180px}.filter-toolbar{flex-direction:column;align-items:stretch;gap:8px}.filter-right{margin-left:0;width:100%}.search-box{width:100%}.batch-bar{bottom:10px;width:96%;padding:10px 12px;gap:8px}.batch-left,.batch-right{width:100%;justify-content:space-between}.select-mode .table-wrap{margin-bottom:120px}}
</style><link rel="stylesheet" href="theme.css?v=2"></head><body><main class="shell"><div class="top"><div><div class="brand"><img class="pea-logo" src="pea-logo.png" alt="ตราสัญลักษณ์ PEA"><div><div class="brand-name">PEA ASSET CARE <b>ITEMFLOW</b></div><h1>&#3611;&#3619;&#3632;&#3623;&#3633;&#3605;&#3636;&#3585;&#3634;&#3619;&#3618;&#3639;&#3617;&#45;&#3588;&#3639;&#3609;</h1><p class="subtitle"><?= !empty($currentUser['is_super']) ? 'ระบบสำหรับผู้ดูแลระบบส่วนกลาง (Super Admin)' : 'ระบบสำหรับผู้ดูแลระบบ (' . e($currentUser['dept_name']) . ')' ?></p></div></div></div><div class="actions"><button class="borrow-open" id="borrowOpen">&#65291;&#32;&#3618;&#3639;&#3617;&#3629;&#3640;&#3611;&#3585;&#3619;&#3603;&#3660;</button><button type="button" class="btn-select-mode" id="btnSelectMode">ลบอุปกรณ์</button><a href="setting.php" class="btn-select-mode" style="text-decoration:none;background:rgba(255,255,255,0.18);border-color:rgba(255,255,255,0.35);box-shadow:none;padding:12px 16px;" title="ตั้งค่าระบบ">ตั้งค่า</a><a href="index.php?logout=1" class="btn-select-mode" style="text-decoration:none;background:rgba(255,255,255,0.15);border-color:rgba(255,255,255,0.3);box-shadow:none;padding:12px 16px;" title="ออกจากระบบ / ย้อนกลับ">ย้อนกลับ</a></div></div>
<div class="batch-bar" id="batchBar" aria-hidden="true"><div class="batch-left"><label class="select-all-label"><input type="checkbox" id="selectAllCheckbox" class="row-checkbox"><span>เลือกทั้งหมด (<span id="totalItemsCount"><?= $result ? $result->num_rows : 0 ?></span> รายการ)</span></label><span class="batch-count">เลือกแล้ว <b id="selectedCountBadge">0</b> รายการ</span></div><div class="batch-right"><button type="button" class="batch-btn batch-delete" id="batchDeleteBtn" disabled>ลบรายการที่เลือก (<span id="batchDeleteCount">0</span>)</button><button type="button" class="batch-btn batch-cancel" id="batchCancelBtn">ยกเลิกการลบ</button></div></div>
<div class="filter-toolbar">
    <div class="filter-left">
        <div class="filter-label-wrap">
            <span class="filter-title">ตัวกรอง:</span>
        </div>
        <div class="filter-chips" id="filterChips">
            <button type="button" class="chip active" data-filter="all">ทั้งหมด</button>
            <button type="button" class="chip chip-pending" data-filter="pending">กำลังยืมอยู่</button>
            <button type="button" class="chip chip-overdue" data-filter="overdue">เกินกำหนด</button>
            <button type="button" class="chip chip-good" data-filter="good">ปกติ</button>
            <button type="button" class="chip chip-damaged" data-filter="damaged">พบความเสียหาย</button>
        </div>
        <div class="filter-select-wrap">
            <select id="statusFilterSelect" class="filter-select" aria-label="เลือกตัวกรองสถานะ">
                <option value="all">สถานะทั้งหมด</option>
                <option value="pending">กำลังยืมอยู่</option>
                <option value="overdue">เกินกำหนดส่ง</option>
                <option value="not_returned">ยังไม่คืน (กำลังยืม + เกินกำหนด)</option>
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
<div class="table-wrap"><table><thead><tr><th class="col-checkbox"></th><th data-sort="id">&#3621;&#3635;&#3604;&#3633;&#3610;</th><th>&#3612;&#3641;&#3657;&#3618;&#3639;&#3617;</th><th>&#3629;&#3640;&#3611;&#3585;&#3619;&#3603;&#3660;</th><th>&#3619;&#3632;&#3618;&#3632;&#3648;&#3623;&#3621;&#3634;&#3585;&#3634;&#3619;&#3618;&#3639;&#3617;</th><th data-sort="borrow">&#3623;&#3633;&#3609;&#3649;&#3621;&#3632;&#3648;&#3623;&#3621;&#3634;&#3626;&#3656;&#3591;&#3617;&#3629;&#3610;</th><th>&#3627;&#3621;&#3633;&#3585;&#3600;&#3634;&#3609;&#3626;&#3656;&#3591;&#3617;&#3629;&#3610;</th><th data-sort="return">&#3623;&#3633;&#3609;&#3649;&#3621;&#3632;&#3648;&#3623;&#3621;&#3634;&#3619;&#3633;&#3610;&#3588;&#3639;&#3609;</th><th>&#3627;&#3621;&#3633;&#3585;&#3600;&#3634;&#3609;&#3619;&#3633;&#3610;&#3588;&#3639;&#3609;</th><th>&#3612;&#3621;&#3585;&#3634;&#3619;&#3605;&#3619;&#3623;&#3592;&#3626;&#3616;&#3634;&#3614;</th><th class="col-manage">&#3585;&#3634;&#3619;&#3592;&#3633;&#3604;&#3585;&#3634;&#3619;</th></tr></thead><tbody>
<tr id="noResultsRow" style="display:none;"><td colspan="11" class="no-results-cell"><div class="no-results"><div class="no-results-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></div><div class="no-results-text">ไม่พบรายการที่ตรงกับคำค้นหาหรือตัวกรอง</div><button type="button" class="no-results-reset" id="resetFilterBtn">ล้างตัวกรองและการค้นหา</button></div></td></tr>
<?php render_transaction_rows($result, $currentUser, $isAllDeptsView); ?></tbody></table></div></main>
<div class="modal" id="formModal" aria-hidden="true"><div class="modal-box form-modal-box"><button class="close form-close" type="button" aria-label="ปิด">×</button><h2 id="formModalTitle">ยืมอุปกรณ์</h2><iframe id="formFrame" title="ItemFlow transaction form" src="about:blank" allow="camera; microphone"></iframe></div></div>
<div class="modal" id="modal"><div class="modal-box"><button class="close" aria-label="ปิด">×</button><h2 id="modalTitle">รายละเอียดรูปภาพอุปกรณ์</h2><div id="modalBorrowerBadge" class="modal-borrower-badge" style="display:none;"></div><div class="compare-grid"><section class="image-panel"><h3>การส่งมอบ</h3><div id="borrowPanel"></div></section><section class="image-panel"><h3>การรับคืน</h3><div id="returnPanel"></div></section></div><div class="note" id="modalNote"></div></div></div>
<div class="modal" id="detailModal"><div class="modal-box"><button class="close" aria-label="ปิด">×</button><h2 id="detailTitle">รายละเอียดรายการ</h2><div class="detail-grid" id="detailGrid"></div><div class="compare-grid"><section class="image-panel"><h3>รูปการส่งมอบ</h3><div id="detailBorrowPhotos"></div></section><section class="image-panel"><h3>รูปการรับคืน</h3><div id="detailReturnPhotos"></div></section></div></div></div>
<script>
const activeSortBy='<?= $sortBy ?>',activeSort='<?= $sort ?>',nextSort='<?= $nextSort ?>',activeSortIcon='<?= $sortIcon ?>';
const sortKeys={'id':'ลำดับ','borrow':'วันและเวลาส่งมอบ','return':'วันและเวลารับคืน'};
document.querySelectorAll('.table-wrap thead th[data-sort]').forEach(header=>{
    const key=header.dataset.sort,label=sortKeys[key]||header.textContent.trim(),isActive=key===activeSortBy,direction=isActive?nextSort:'asc';
    header.innerHTML=`<a class="sort-code" href="?sort_by=${key}&sort=${direction}" title="เรียงตาม${label}">${label} <span class="arrow">${isActive?activeSortIcon:'↕'}</span></a>`;
});
const modal=document.getElementById('modal'),title=document.getElementById('modalTitle'),borrowPanel=document.getElementById('borrowPanel'),returnPanel=document.getElementById('returnPanel'),note=document.getElementById('modalNote');
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

document.querySelectorAll('.compare').forEach(link=>{const id=new URL(link.href,location.href).searchParams.get('id');if(id)link.insertAdjacentHTML('afterend',`<a class="edit-link" href="history_detail.php?id=${id}&edit=1">\u0e41\u0e01\u0e49\u0e44\u0e02</a>`);});
function panel(el,images){el.innerHTML=images&&images.length?`<img src="${images[0]}" alt="รูปภาพอุปกรณ์">${images.length>1?`<div class="muted">แสดงรูปแรกจากทั้งหมด ${images.length} รูป</div>`:''}`:'<div class="none">ยังไม่มีรูปภาพ</div>'}
function openModal(data){
    title.textContent=`\u0e23\u0e32\u0e22\u0e25\u0e30\u0e40\u0e2d\u0e35\u0e22\u0e14\u0e23\u0e39\u0e1b\u0e20\u0e32\u0e1e: ${data.item} \u2014 ${data.borrower}`;
    const bBadge = document.getElementById('modalBorrowerBadge');
    if (bBadge) {
        if (data.borrower_photo) {
            bBadge.innerHTML = `<img src="${data.borrower_photo}" alt="รูปผู้ยืม"><div><b>ผู้ยืมอุปกรณ์:</b> ${data.borrower} <span style="color:#74648a;font-size:0.8rem;">(มีรูปถ่ายยืนยันตัวตน)</span></div>`;
            bBadge.style.display = 'flex';
        } else {
            bBadge.style.display = 'none';
        }
    }
    panel(borrowPanel,data.borrow);
    panel(returnPanel,data.return);
    note.textContent=`\u0e1c\u0e25\u0e15\u0e23\u0e27\u0e08\u0e2a\u0e20\u0e32\u0e1e: ${data.status}${data.note?' | \u0e2b\u0e21\u0e32\u0e22\u0e40\u0e2b\u0e15\u0e38: '+data.note:''}`;
    if(data.return&&data.return.length){
        const add=document.createElement('button');
        add.type='button';
        add.className='modal-add-return';
        add.textContent='\u002b \u0e40\u0e1e\u0e34\u0e48\u0e21\u0e2b\u0e25\u0e31\u0e01\u0e10\u0e32\u0e19';
        add.onclick=()=>{
            modal.classList.remove('show');
            const params=new URLSearchParams({form:'return',id:data.id,borrower:data.borrower,item:data.item});
            openTransactionForm('borrow_form.php?'+params.toString(),'\u0e40\u0e1e\u0e34\u0e48\u0e21\u0e2b\u0e25\u0e31\u0e01\u0e10\u0e32\u0e19\u0e04\u0e37\u0e19');
        };
        returnPanel.appendChild(add);
    }
    modal.classList.add('show');
}
function openBorrowerPhotoModal(photoSrc, borrowerName) {
    title.textContent = `รูปถ่ายผู้ยืม: ${borrowerName}`;
    const bBadge = document.getElementById('modalBorrowerBadge');
    if (bBadge) bBadge.style.display = 'none';
    borrowPanel.innerHTML = `<img src="${photoSrc}" alt="รูปถ่ายผู้ยืม" style="height:320px;object-fit:contain;border-radius:12px;">`;
    returnPanel.innerHTML = '<div class="none">รูปถ่ายยืนยันตัวตนผู้ยืมอุปกรณ์ขณะส่งมอบ</div>';
    note.textContent = `ผู้ยืม: ${borrowerName}`;
    modal.classList.add('show');
}
document.querySelectorAll('.modal .close:not(.form-close)').forEach(button=>button.addEventListener('click',()=>button.closest('.modal').classList.remove('show')));document.querySelectorAll('.modal:not(#formModal)').forEach(overlay=>overlay.addEventListener('click',event=>{if(event.target===overlay)overlay.classList.remove('show')}));

const shell=document.querySelector('.shell'),btnSelectMode=document.getElementById('btnSelectMode'),selectAllCheckbox=document.getElementById('selectAllCheckbox'),batchDeleteBtn=document.getElementById('batchDeleteBtn'),batchCancelBtn=document.getElementById('batchCancelBtn'),selectedCountBadge=document.getElementById('selectedCountBadge'),batchDeleteCount=document.getElementById('batchDeleteCount');
function updateBatchState(){
    const allVisibleRows=Array.from(document.querySelectorAll('.table-wrap tbody tr')).filter(tr=>tr.style.display!=='none'&&tr.id!=='noResultsRow'&&!tr.classList.contains('empty-row'));
    const allCbs=allVisibleRows.map(tr=>tr.querySelector('.item-checkbox')).filter(Boolean);
    const checked=allCbs.filter(cb=>cb.checked),count=checked.length,total=allCbs.length;
    if(selectedCountBadge)selectedCountBadge.textContent=count;
    if(batchDeleteCount)batchDeleteCount.textContent=count;
    if(batchDeleteBtn)batchDeleteBtn.disabled=count===0;
    const isAllChecked=total>0&&count===total;
    if(selectAllCheckbox)selectAllCheckbox.checked=isAllChecked;
    document.querySelectorAll('.item-checkbox').forEach(cb=>{const tr=cb.closest('tr');if(tr)tr.classList.toggle('row-selected',cb.checked);});
}
function setSelectMode(active){
    if(active){shell.classList.add('select-mode');if(btnSelectMode)btnSelectMode.textContent='ยกเลิกการลบ';}
    else{shell.classList.remove('select-mode');if(btnSelectMode)btnSelectMode.textContent='ลบอุปกรณ์';document.querySelectorAll('.item-checkbox, #selectAllCheckbox').forEach(cb=>cb.checked=false);updateBatchState();}
}
if(btnSelectMode)btnSelectMode.addEventListener('click',()=>setSelectMode(!shell.classList.contains('select-mode')));
if(batchCancelBtn)batchCancelBtn.addEventListener('click',()=>setSelectMode(false));
function toggleAll(checked){
    document.querySelectorAll('.table-wrap tbody tr').forEach(tr=>{
        if(tr.style.display!=='none'&&tr.id!=='noResultsRow'&&!tr.classList.contains('empty-row')){
            const cb=tr.querySelector('.item-checkbox');
            if(cb)cb.checked=checked;
        }
    });
    updateBatchState();
}
if(selectAllCheckbox)selectAllCheckbox.addEventListener('change',()=>toggleAll(selectAllCheckbox.checked));

/* Event Delegation บน tbody สำหรับ dynamic rows */
const tableBody = document.querySelector('.table-wrap tbody');
if (tableBody) {
    tableBody.addEventListener('click', e => {
        const borrowerPhotoBtn = e.target.closest('.view-borrower-photo');
        if (borrowerPhotoBtn && borrowerPhotoBtn.dataset.photo) {
            e.preventDefault();
            e.stopPropagation();
            openBorrowerPhotoModal(borrowerPhotoBtn.dataset.photo, borrowerPhotoBtn.dataset.name);
            return;
        }

        const photoBtn = e.target.closest('.view-one');
        if (photoBtn && photoBtn.dataset.comparison) {
            e.preventDefault();
            e.stopPropagation();
            try { openModal(JSON.parse(photoBtn.dataset.comparison)); } catch(err){}
            return;
        }

        const returnBtn = e.target.closest('.return-entry');
        if (returnBtn) {
            e.preventDefault();
            e.stopPropagation();
            const params = new URLSearchParams({
                form: 'return',
                id: returnBtn.dataset.id,
                borrower: returnBtn.dataset.borrower,
                item: returnBtn.dataset.item,
                department: '<?= e($currentUser['dept_name']) ?>',
                lender: '<?= e($currentUser['full_name']) ?>'
            });
            const formTitle = returnBtn.classList.contains('add-return-image') ? 'เพิ่มหลักฐานคืน' : 'บันทึกการรับคืน';
            openTransactionForm('borrow_form.php?' + params.toString(), formTitle);
            return;
        }

        if (shell && shell.classList.contains('select-mode')) {
            if (e.target.closest('a, button, input[type="checkbox"]')) return;
            const tr = e.target.closest('tr');
            if (tr && tr.id !== 'noResultsRow' && !tr.classList.contains('empty-row')) {
                const cb = tr.querySelector('.item-checkbox');
                if (cb) {
                    cb.checked = !cb.checked;
                    updateBatchState();
                }
            }
        }
    });

    tableBody.addEventListener('change', e => {
        if (e.target.classList.contains('item-checkbox')) {
            updateBatchState();
        }
    });
}

/* Realtime Search & Filter */
const searchInput=document.getElementById('searchInput'),searchClearBtn=document.getElementById('searchClearBtn'),filterChips=document.querySelectorAll('.chip[data-filter]'),statusFilterSelect=document.getElementById('statusFilterSelect'),visibleCountEl=document.getElementById('visibleCount'),totalFilterCountEl=document.getElementById('totalFilterCount'),totalItemsCountEl=document.getElementById('totalItemsCount'),noResultsRow=document.getElementById('noResultsRow'),resetFilterBtn=document.getElementById('resetFilterBtn');
let currentFilter='all',currentSearch='';
function applyFilter(){
    const rows=document.querySelectorAll('.table-wrap tbody tr:not(#noResultsRow):not(.empty-row)');
    const totalRows=rows.length;
    let visibleCount=0;
    const query=currentSearch.trim().toLowerCase();
    rows.forEach(row=>{
        if(!row.dataset.search)return;
        const status=row.dataset.status||'',isReturned=row.dataset.returned==='1',searchText=row.dataset.search;
        let statusMatch=true;
        if(currentFilter==='all')statusMatch=true;
        else if(currentFilter==='pending')statusMatch=status==='pending';
        else if(currentFilter==='overdue')statusMatch=status==='overdue';
        else if(currentFilter==='not_returned')statusMatch=!isReturned;
        else if(currentFilter==='returned')statusMatch=isReturned;
        else if(currentFilter==='good')statusMatch=isReturned&&status==='good';
        else if(currentFilter==='damaged')statusMatch=isReturned&&(status==='damaged'||status==='needs_review');
        let searchMatch=true;
        if(query)searchMatch=searchText.includes(query);
        const isVisible=statusMatch&&searchMatch;
        row.style.display=isVisible?'':'none';
        if(isVisible)visibleCount++;
    });
    if(visibleCountEl)visibleCountEl.textContent=visibleCount;
    if(totalFilterCountEl)totalFilterCountEl.textContent=totalRows;
    if(totalItemsCountEl)totalItemsCountEl.textContent=totalRows;
    if(noResultsRow)noResultsRow.style.display=(totalRows>0 && visibleCount===0)?'':'none';
    if(searchClearBtn)searchClearBtn.style.display=query?'grid':'none';
    updateBatchState();
}
function resetFilters(){
    currentFilter='all';currentSearch='';
    if(searchInput)searchInput.value='';
    if(statusFilterSelect)statusFilterSelect.value='all';
    filterChips.forEach(chip=>chip.classList.toggle('active',chip.dataset.filter==='all'));
    applyFilter();
}
if(resetFilterBtn)resetFilterBtn.addEventListener('click',resetFilters);
if(searchClearBtn){searchClearBtn.addEventListener('click',()=>{if(searchInput){searchInput.value='';currentSearch='';searchInput.focus();applyFilter();}});}
if(searchInput){searchInput.addEventListener('input',e=>{currentSearch=e.target.value;applyFilter();});}
filterChips.forEach(chip=>{
    chip.addEventListener('click',()=>{
        filterChips.forEach(c=>c.classList.remove('active'));
        chip.classList.add('active');
        currentFilter=chip.dataset.filter;
        if(statusFilterSelect)statusFilterSelect.value=currentFilter;
        applyFilter();
    });
});
if(statusFilterSelect){
    statusFilterSelect.addEventListener('change',e=>{
        currentFilter=e.target.value;
        filterChips.forEach(c=>c.classList.toggle('active',c.dataset.filter===currentFilter));
        applyFilter();
    });
}
if(batchDeleteBtn){
    batchDeleteBtn.addEventListener('click',async()=>{
        const checked=Array.from(document.querySelectorAll('.item-checkbox:checked'));
        if(!checked.length){alert('กรุณาเลือกรายการที่ต้องการลบอย่างน้อย 1 รายการ');return;}
        let preview=checked.slice(0,5).map(cb=>`• ${cb.dataset.item||'อุปกรณ์'} (${cb.dataset.borrower||'ผู้ยืม'})`).join('\n');
        if(checked.length>5)preview+=`\n... และอีก ${checked.length-5} รายการ`;
        if(!confirm(`ต้องการลบรายการที่เลือกจำนวน ${checked.length} รายการใช่หรือไม่?\n\n${preview}\n\n(เมื่อลบแล้วข้อมูลและรูปภาพจะไม่สามารถกู้คืนได้)`))return;
        try{
            const fd=new FormData();
            fd.append('action','delete_batch');
            checked.forEach(cb=>fd.append('ids[]',cb.value));
            const res=await fetch('save_record.php',{method:'POST',body:fd});
            const t=await res.text();
            alert(t);
            if(res.ok)location.reload();
        }catch(err){alert('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์เพื่อลบข้อมูลได้');}
    });
}

const formModal=document.getElementById('formModal'),formFrame=document.getElementById('formFrame'),formTitle=document.getElementById('formModalTitle');
function openTransactionForm(url,titleText){formTitle.textContent=titleText;formModal.classList.add('show');formModal.setAttribute('aria-hidden','false');formFrame.src=url;}
document.getElementById('borrowOpen').addEventListener('click',()=>openTransactionForm('borrow_form.php?form=borrow&department=' + encodeURIComponent('<?= e($currentUser['dept_name']) ?>') + '&lender=' + encodeURIComponent('<?= e($currentUser['full_name']) ?>'),'ยืมอุปกรณ์'));
document.querySelector('.form-close').addEventListener('click',()=>{formModal.classList.remove('show');formModal.setAttribute('aria-hidden','true');formFrame.src='about:blank';});
formModal.addEventListener('click',event=>{if(event.target===formModal){formModal.classList.remove('show');formModal.setAttribute('aria-hidden','true');formFrame.src='about:blank';}});

/* ========================================================
   ระบบ Real-time AJAX Polling (อัปเดตอัตโนมัติทุก 3 วินาที)
   ======================================================== */
let pollTimeout = null;
let isPolling = true;
let isFetching = false;

async function pollTableData() {
    if (!isPolling || isFetching) return;

    // ตรวจสอบสถานะ: หากเปิด Modal หรืออยู่ในโหมดเลือกหลายรายการลบ ให้ข้ามรอบนี้ไปก่อน
    const isModalOpen = document.querySelector('.modal.show') !== null;
    const isSelectMode = shell && shell.classList.contains('select-mode');
    if (isModalOpen || isSelectMode) {
        scheduleNextPoll(3000);
        return;
    }

    // เก็บรายการ ID ที่ถูกติ๊กเลือกไว้ เพื่อคงสถานะหลังอัปเดต
    const checkedIds = new Set(Array.from(document.querySelectorAll('.item-checkbox:checked')).map(cb => cb.value));

    try {
        isFetching = true;
        const pollUrl = new URL(location.href);
        pollUrl.searchParams.set('ajax_table', '1');
        pollUrl.searchParams.set('_t', Date.now()); // ป้องกัน browser cache

        const res = await fetch(pollUrl.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store'
        });

        if (res.status === 401 || res.status === 403 || res.redirected) {
            window.location.href = 'index.php';
            return;
        }

        if (res.ok) {
            const html = await res.text();
            if (html.includes('<!DOCTYPE') || html.includes('<html') || html.includes('portal-header') || html.includes('login.php') || html.includes('auth-card')) {
                window.location.href = 'index.php';
                return;
            }
            const tbodyEl = document.querySelector('.table-wrap tbody');
            const noResults = document.getElementById('noResultsRow');

            if (tbodyEl) {
                // แทนที่เนื้อหาตาราง
                tbodyEl.innerHTML = '';
                if (noResults) tbodyEl.appendChild(noResults);
                tbodyEl.insertAdjacentHTML('beforeend', html);

                // คืนค่า Checkbox ที่ผู้ใช้เคยติ๊กเลือกไว้
                if (checkedIds.size > 0) {
                    tbodyEl.querySelectorAll('.item-checkbox').forEach(cb => {
                        if (checkedIds.has(cb.value)) cb.checked = true;
                    });
                }

                // อัปเดตการนับเวลา และตัวกรองทันที
                updateDurations();
                applyFilter();
            }
        }
    } catch (err) {
        console.warn('Real-time Polling Warning (จะลองใหม่ใน 3 วินาที):', err);
    } finally {
        isFetching = false;
        scheduleNextPoll(3000);
    }
}

function scheduleNextPoll(delay = 3000) {
    clearTimeout(pollTimeout);
    if (isPolling && !document.hidden) {
        pollTimeout = setTimeout(pollTableData, delay);
    }
}

// หยุด Polling เมื่อสลับแท็บ/ย่อหน้าจอ เพื่อประหยัดทรัพยากร และดึงทันทีเมื่อเปิดกลับมา
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        clearTimeout(pollTimeout);
    } else {
        pollTableData();
    }
});

// เริ่ม Polling รอบแรกหลังจากเปิดหน้าเว็บแล้ว 3 วินาที
scheduleNextPoll(3000);

window.addEventListener('message', event => {
    if (event.origin !== location.origin || event.data?.type !== 'itemflow-saved') return;
    formModal.classList.remove('show');
    formModal.setAttribute('aria-hidden', 'true');
    formFrame.src = 'about:blank';
    pollTableData(); // อัปเดตตารางทันทีไม่ต้องรีเฟรชหน้าทั้งหน้า
});
</script></body></html>









