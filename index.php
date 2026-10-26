<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    if (!$isHttps) {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $hostname = parse_url('http://' . $host, PHP_URL_HOST);
        if ($host && !in_array($hostname, ['localhost', '127.0.0.1'], true)) {
            header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? ''), true, 301);
            exit;
        }
    }
}
require_once __DIR__ . '/auth.php';
$currentUser = current_user();
if (!$currentUser['is_logged_in']) {
    header('Location: login.php');
    exit;
}
if (!isset($_GET['form'])) {
    if ($currentUser['role'] === 'lender') {
        header('Location: lender.php');
    } elseif ($currentUser['role'] === 'borrower') {
        header('Location: borrower.php');
    } else {
        header('Location: history.php');
    }
    exit;
}
$displayDept = $_GET['department'] ?? ($currentUser['dept_name'] ?: 'แผนกโครงข่ายสื่อสารหลัก');

// ดึงรายชื่อผู้ยืมที่สมัครสมาชิกไว้ในระบบ จัดกลุ่มตามแผนก
$registeredBorrowers = [];
$borrowerRes = $conn->query("SELECT id, username, full_name, phone, dept_key, dept_name FROM department_users WHERE role = 'borrower' ORDER BY dept_name ASC, full_name ASC");
if ($borrowerRes) {
    while ($bRow = $borrowerRes->fetch_assoc()) {
        $registeredBorrowers[] = $bRow;
    }
}
$borrowersByDept = [];
foreach ($registeredBorrowers as $b) {
    $borrowersByDept[$b['dept_name']][] = $b;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ITEMFLOW | PEA Asset Care</title>
<style>
:root{--pea-purple:#4b2683;--pea-deep:#250c50;--pea-violet:#7653a7;--pea-gold:#d9ac3b;--pea-gold-light:#fae8a6;--paper:#fffefa;--ink:#2e2341;--muted:#75658b;--line:#e5dcef;--danger:#c83f57;--green:#177b58}*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:"Segoe UI",Tahoma,sans-serif;color:var(--ink);background:radial-gradient(circle at 5% 10%,#f9e8ac9c,transparent 19rem),radial-gradient(circle at 96% 87%,#4b268340,transparent 24rem),linear-gradient(135deg,#eee8f8 0%,#faf6ff 48%,#fffaf0 100%)}body:before,body:after{content:"";position:fixed;pointer-events:none;z-index:-1;border-radius:50%;border:1px solid #4b268312}body:before{width:38rem;height:38rem;top:-22rem;right:-14rem;box-shadow:0 0 0 32px #4b268308,0 0 0 64px #4b268305}body:after{width:31rem;height:31rem;bottom:-18rem;left:-16rem;box-shadow:0 0 0 26px #d9ac3b10,0 0 0 52px #d9ac3b08}.app{width:min(820px,calc(100% - 26px));margin:26px auto 42px}.hero{position:relative;overflow:hidden;padding:26px 28px 29px;border-radius:28px 28px 0 0;color:white;background:linear-gradient(118deg,var(--pea-deep),var(--pea-purple) 58%,#7653a7);box-shadow:0 22px 48px #3512672e}.hero:before{content:"";position:absolute;width:310px;height:310px;right:-120px;top:-186px;border:26px solid #ffffff15;border-radius:50%;box-shadow:0 0 0 26px #ffffff0c,0 0 0 52px #ffffff08}.hero:after{content:"";position:absolute;inset:auto 0 0;height:6px;background:linear-gradient(90deg,#f6d368,var(--pea-gold),#fff0b8)}.brand{position:relative;z-index:1;display:flex;align-items:center;gap:16px}.logo{width:76px;height:76px;flex:none;display:grid;place-items:center;position:relative;border-radius:23px;background:linear-gradient(145deg,#fff 0%,#f9e7a4 100%);border:2px solid #fff9;box-shadow:0 11px 23px #10002552}.logo:before{content:"";position:absolute;width:47px;height:47px;border-radius:15px;background:linear-gradient(145deg,#321060,#7651a6);transform:rotate(45deg);box-shadow:inset 0 0 0 2px #f8db7d}.logo:after{content:"";position:absolute;color:#ffe78c;font-size:28px;font-weight:900;transform:rotate(0)}.logo span{position:absolute;bottom:-13px;padding:2px 8px;border-radius:999px;background:var(--pea-gold);color:var(--pea-deep);font-size:.58rem;font-weight:900;letter-spacing:.12em;box-shadow:0 3px 9px #21083c55}.brand small{display:block;letter-spacing:.18em;font-size:.67rem;font-weight:800;color:#fbe8a6}.brand h1{margin:3px 0 0;font-size:clamp(1.65rem,5vw,2.4rem);letter-spacing:.01em}.brand p{margin:6px 0 0;color:#eee5f9;font-size:.91rem}.body-card{padding:26px 28px 29px;background:#fffffff2;border:1px solid #fff;border-top:0;border-radius:0 0 28px 28px;box-shadow:0 24px 60px #34145719}.mode{display:grid;grid-template-columns:1fr 1fr;gap:6px;padding:6px;border:1px solid #e4d9ef;background:#f4eff9;border-radius:16px;margin-bottom:23px}.mode button{border:0;border-radius:11px;padding:12px 16px;background:transparent;color:#6b5a80;font:inherit;font-weight:800;cursor:pointer;transition:.2s}.mode button.active{background:linear-gradient(135deg,var(--pea-purple),#6e49a0);color:#fff;box-shadow:0 7px 14px #4b268333}.form-title{display:flex;align-items:center;gap:9px;margin:0 0 17px;color:var(--pea-deep);font-size:1.04rem;font-weight:900}.form-title:before{content:"";width:8px;height:8px;border-radius:50%;background:var(--pea-gold);box-shadow:0 0 0 4px #fae8a680}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}.field{margin-bottom:15px}.wide{grid-column:1/-1}label{display:block;margin-bottom:7px;color:#473264;font-size:.9rem;font-weight:800}.required{color:var(--danger)}input,textarea,select{width:100%;border:1px solid var(--line);border-radius:12px;padding:12px 13px;background:#fff;color:var(--ink);font:inherit;transition:border .2s,box-shadow .2s}input::placeholder,textarea::placeholder{color:#a69aae}input:focus,textarea:focus,select:focus{outline:0;border-color:var(--pea-gold);box-shadow:0 0 0 4px #d9ac3b2a}textarea{min-height:82px;resize:vertical}.return-help{display:none;margin:-3px 0 17px;padding:12px 14px;border-left:4px solid var(--pea-gold);border-radius:0 12px 12px 0;background:#fff6da;color:#71561a;font-size:.88rem;line-height:1.55}.return-only{display:none}.status-choice{display:flex;gap:10px}.status-choice label{flex:1;margin:0;padding:11px;border:1px solid var(--line);border-radius:12px;background:#fff;cursor:pointer;font-weight:700;color:#544269}.status-choice input{width:auto;margin-right:5px;accent-color:var(--pea-purple)}.gallery{position:relative;margin-top:5px;padding:15px;border:1px dashed #aa92c7;border-radius:18px;background:linear-gradient(145deg,#faf7ff,#fffdf5)}.gallery:before{content:"";position:absolute;inset:5px;border:1px solid #d9ac3b24;border-radius:14px;pointer-events:none}.gallery-head{position:relative;display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px}.gallery-title{font-weight:900;color:var(--pea-deep)}.gallery-caption{font-size:.76rem;color:var(--muted)}.photos{position:relative;display:grid;grid-template-columns:repeat(auto-fit,minmax(118px,1fr));gap:10px;min-height:124px}.empty{display:grid;place-items:center;min-height:124px;border-radius:12px;background:#f0eaf8;color:#806f94;font-weight:700}.photo img{width:100%;height:118px;display:block;object-fit:cover;border:1px solid #e3d9ee;border-radius:10px}.photo button{width:100%;border:0;border-radius:8px;margin-top:5px;padding:6px;background:#fde4e8;color:#982b43;font:inherit;font-size:.78rem;font-weight:800;cursor:pointer}.photo-actions{position:relative;display:flex;gap:10px;margin-top:13px}.photo-actions button,.submit{border:0;border-radius:12px;font:inherit;font-weight:900;cursor:pointer;transition:transform .2s,filter .2s}.photo-actions button{flex:1;padding:12px;background:#ece3f7;color:var(--pea-purple)}.photo-actions button:last-child{background:linear-gradient(135deg,#f9db7a,var(--pea-gold));color:var(--pea-deep)}.photo-actions button:hover,.submit:hover,.history-link:hover{transform:translateY(-2px);filter:brightness(1.02)}.submit{width:100%;margin-top:22px;padding:15px;color:white;background:linear-gradient(135deg,var(--pea-deep),var(--pea-purple) 58%,#6f4ca0);box-shadow:0 13px 21px #41206e32;font-size:1rem}.history-link{display:flex;justify-content:center;align-items:center;gap:8px;margin:15px auto 0;width:max-content;color:var(--pea-purple);font-size:.93rem;font-weight:900;text-decoration:none;transition:.2s}.history-link:before{content:"⌁";display:grid;place-items:center;width:20px;height:20px;border-radius:50%;background:#eee5f8;color:var(--pea-purple)}@media(max-width:600px){.app{width:calc(100% - 18px);margin:10px auto 28px}.hero{padding:22px 18px 25px;border-radius:23px 23px 0 0}.body-card{padding:22px 17px 24px;border-radius:0 0 23px 23px}.logo{width:64px;height:64px;border-radius:19px}.logo:before{width:40px;height:40px}.brand{gap:13px}.brand p{font-size:.82rem}.form-grid{grid-template-columns:1fr}.status-choice{flex-direction:column}.mode button{padding:11px 8px;font-size:.9rem}}

.brand-mark{width:76px;height:82px;flex:none;display:grid;place-items:center;clip-path:polygon(50% 0,94% 15%,89% 68%,50% 100%,11% 68%,6% 15%);background:linear-gradient(145deg,#f8dc78,#b9831d 46%,#fff0b0 50%,#4b2683 52%);filter:drop-shadow(0 4px 5px #13062a66);color:#f9d96e;font-size:2.6rem;font-weight:900}.brand-mark:before{content:"";position:absolute;width:60px;height:68px;clip-path:polygon(50% 0,94% 15%,89% 68%,50% 100%,11% 68%,6% 15%);background:linear-gradient(145deg,#563090,#250c50);z-index:-1}.brand-mark{isolation:isolate}@media(max-width:600px){.brand-mark{width:58px;height:64px;font-size:2rem}}
.pea-logo{width:82px;height:82px;flex:none;object-fit:contain;border-radius:50%;filter:drop-shadow(0 5px 8px #13062a55)}@media(max-width:600px){.pea-logo{width:64px;height:64px}}.brand h1{letter-spacing:.075em;font-weight:900;text-shadow:0 2px 8px #1807314a}.brand small{margin-bottom:3px;letter-spacing:.2em}.brand p{margin-top:7px}.camera-modal{position:fixed;inset:0;z-index:20;display:grid;place-items:center;padding:16px;background:#160d25c9}.camera-modal[hidden]{display:none}.camera-box{position:relative;width:min(620px,100%);padding:20px;border-radius:20px;background:#fff;color:var(--pea-deep);box-shadow:0 20px 55px #12072355}.camera-box h2{margin:0 44px 14px 0}.camera-box video{display:block;width:100%;max-height:65vh;aspect-ratio:4/3;object-fit:cover;border-radius:14px;background:#190c2c}.camera-capture{width:100%;margin-top:14px;border:0;border-radius:12px;padding:13px;background:linear-gradient(135deg,var(--pea-deep),var(--pea-purple));color:#fff;font:inherit;font-weight:900;cursor:pointer}.camera-close{position:absolute;right:14px;top:13px;width:38px;height:38px;border:0;border-radius:50%;background:#eee7fa;color:var(--pea-deep);font-size:24px;cursor:pointer}
.borrower-photo-display{position:relative;display:flex;justify-content:center;min-height:124px;padding:6px;border-radius:12px;background:#f9f5ff}
.borrower-photo-card{position:relative;width:130px;text-align:center}
.borrower-photo-card img{width:130px;height:130px;display:block;object-fit:cover;border-radius:14px;border:2px solid var(--pea-gold);box-shadow:0 4px 12px #35126724}
.borrower-photo-card button{width:100%;border:0;border-radius:8px;margin-top:6px;padding:6px;background:#fde4e8;color:#982b43;font:inherit;font-size:.78rem;font-weight:800;cursor:pointer}
</style></head>
<body><main class="app"><section class="hero"><header class="brand"><img class="pea-logo" src="pea-logo.png" alt="ตราสัญลักษณ์ PEA"><div><small>PEA ASSET CARE <span style="background:var(--pea-gold);color:var(--pea-deep);padding:2px 8px;border-radius:6px;font-weight:900;margin-left:6px;"><?= htmlspecialchars($displayDept, ENT_QUOTES) ?></span></small><h1>ITEMFLOW</h1><p>จัดการการยืม–คืน พร้อมตรวจสภาพอุปกรณ์</p></div></header></section><section class="body-card"><div class="mode"><button class="active" type="button" data-mode="Borrow">⌁ บันทึกการส่งมอบ</button><button type="button" data-mode="Return">↩ บันทึกการรับคืน</button></div><h2 class="form-title">ข้อมูลรายการ</h2><p id="returnHelp" class="return-help">กรอกข้อมูลรายการที่นำมาคืนด้วยตนเอง ระบบจะค้นหาจาก <b>ชื่อผู้ยืม + ชื่ออุปกรณ์</b> ให้อัตโนมัติ หากคืนไม่ครบ สามารถบันทึกคืนซ้ำได้ รูปและหมายเหตุจะถูกรวมไว้ในรายการเดิม</p>
<div class="form-grid">
    <div class="field wide" id="borrowerSelectWrap">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:7px;">
            <label for="borrower_select" style="margin-bottom:0;">ชื่อผู้ยืม (เลือกตามที่สมัครสมาชิก) <span class="required">*</span></label>
            <a href="register.php" target="_blank" style="font-size:.76rem;color:var(--pea-purple);text-decoration:none;font-weight:800;display:inline-flex;align-items:center;gap:3px;" title="เปิดหน้าลงทะเบียนผู้ยืมใหม่ในแท็บใหม่">
                <span>＋ สมัครสมาชิกลงทะเบียนผู้ยืม</span>
            </a>
        </div>
        <select id="borrower_select">
            <option value="">-- กรุณาเลือกชื่อผู้ยืมที่สมัครสมาชิกไว้ --</option>
            <?php foreach ($borrowersByDept as $dName => $users): ?>
                <optgroup label="<?= htmlspecialchars($dName, ENT_QUOTES) ?>">
                    <?php foreach ($users as $u): ?>
                        <option value="<?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?>"
                                data-phone="<?= htmlspecialchars($u['phone'] ?? '', ENT_QUOTES) ?>"
                                data-username="<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>"
                                data-dept="<?= htmlspecialchars($u['dept_name'], ENT_QUOTES) ?>">
                            <?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?><?= !empty($u['phone']) ? ' (' . htmlspecialchars($u['phone'], ENT_QUOTES) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
            <option value="__custom__">-- ระบุชื่ออื่นด้วยตนเอง (กรณียังไม่ได้สมัครสมาชิก) --</option>
        </select>
    </div>
    <div class="field wide" id="manualBorrowerWrap" style="display:none;">
        <label for="borrower_name">ชื่อ-นามสกุล ผู้ยืม (ระบุเอง) <span class="required">*</span></label>
        <input id="borrower_name" placeholder="ชื่อ-นามสกุล">
    </div>
    <div class="field">
        <label for="borrower_phone">เบอร์ผู้ยืม</label>
        <input id="borrower_phone" type="tel" placeholder="ถ้ามี">
    </div>
    <div class="field">
        <label for="item_name">ชื่ออุปกรณ์ <span class="required">*</span></label>
        <input id="item_name" placeholder="เช่น กล้อง Canon">
    </div>
    <div class="field">
        <label for="sub_item_name">รายละเอียด / เลขครุภัณฑ์</label>
        <input id="sub_item_name" placeholder="รุ่น หรืออุปกรณ์ย่อย">
    </div>
    <div class="field">
        <label for="borrow_duration">ระยะเวลาการยืม</label>
        <input id="borrow_duration" type="number" min="1" step="1" value="7" required>
        <small class="duration-hint">&#3623;&#3633;&#3609; (&#3585;&#3635;&#3627;&#3609;&#3604; 7 &#3623;&#3633;&#3609;)</small>
    </div>
    <div class="field">
        <label for="lender_name">ผู้ให้ยืม</label>
        <input id="lender_name" placeholder="ชื่อผู้ส่งมอบอุปกรณ์">
    </div>
</div>
<section class="gallery borrower-gallery" id="borrowerGallery" style="margin-top:4px;margin-bottom:16px;border-color:#d9ac3b;">
    <div class="gallery-head"><div class="gallery-title" id="borrowerPhotoTitle">รูปถ่ายผู้ยืมอุปกรณ์ (1 รูป) <span class="required">*</span></div><div class="gallery-caption">ถ่ายรูปหน้าผู้ยืมเพื่อยืนยันตัวตนการรับอุปกรณ์</div></div>
    <div class="borrower-photo-display" id="borrowerPhotoDisplay"><div class="empty">ยังไม่มีรูปถ่ายผู้ยืม</div></div>
    <div class="photo-actions"><button type="button" id="cameraBorrower" style="background:linear-gradient(135deg,#f9db7a,var(--pea-gold));color:var(--pea-deep);">◉ ถ่ายรูปผู้ยืม</button><button type="button" id="pickerBorrower">＋ แนบรูปผู้ยืม</button></div>
    <input hidden type="file" id="fileBorrower" accept="image/*" capture="user">
</section>
<section id="returnFields" class="return-only"><div class="field"><label>ผลการตรวจสภาพ <span class="required">*</span></label><div class="status-choice"><label><input type="radio" name="condition" value="good" checked> ปกติ / ไม่พบความเสียหาย</label><label><input type="radio" name="condition" value="damaged"> พบความเสียหาย</label></div></div><div class="field"><label for="condition_note">รายละเอียดการตรวจสภาพ</label><textarea id="condition_note" placeholder="ระบุรอย ความเสียหาย หรือหมายเหตุ (ถ้ามี)"></textarea></div></section>
<section class="gallery"><div class="gallery-head"><div class="gallery-title" id="photoTitle">หลักฐานสภาพก่อนส่งมอบ <span class="required">*</span></div><div class="gallery-caption">ถ่ายอย่างน้อย 1 รูป</div></div><div class="photos" id="photos"><div class="empty">ยังไม่มีรูปภาพ</div></div><div class="photo-actions"><button type="button" id="camera">◉ เปิดกล้อง</button><button type="button" id="picker">＋ เพิ่มหลักฐาน</button></div><input hidden type="file" id="file" accept="image/*" capture="environment" multiple></section>
<button type="button" class="submit" id="submit">บันทึกการส่งมอบพร้อมรูปภาพ</button><a class="history-link" href="<?= ($currentUser['role'] === 'lender') ? 'lender.php' : (($currentUser['role'] === 'borrower') ? 'borrower.php' : 'history.php') ?>">ดูประวัติและเปรียบเทียบรูปภาพ</a></section></main>
<div class="camera-modal" id="cameraModal" hidden><div class="camera-box"><button type="button" class="camera-close" id="cameraClose" aria-label="&#3611;&#3636;&#3604;">&times;</button><h2 id="cameraModalTitle">กล้องถ่ายภาพ</h2><video id="cameraPreview" autoplay playsinline></video><button type="button" class="camera-capture" id="cameraCapture">&#3606;&#3656;&#3634;&#3618;&#3616;&#3634;&#3614;</button></div></div>
<script>
const modeButtons=document.querySelectorAll('[data-mode]'),returnOnly=document.querySelectorAll('.return-only'),returnHelp=document.getElementById('returnHelp'),photoTitle=document.getElementById('photoTitle'),submit=document.getElementById('submit'),file=document.getElementById('file'),fileBorrower=document.getElementById('fileBorrower'),photos=document.getElementById('photos'),borrowerSelect=document.getElementById('borrower_select'),manualBorrowerWrap=document.getElementById('manualBorrowerWrap'),borrowerName=document.getElementById('borrower_name'),itemName=document.getElementById('item_name'),subItemName=document.getElementById('sub_item_name'),borrowerPhone=document.getElementById('borrower_phone'),borrowDuration=document.getElementById('borrow_duration'),lenderName=document.getElementById('lender_name'),conditionNote=document.getElementById('condition_note'),borrowerGallery=document.getElementById('borrowerGallery'),borrowerPhotoDisplay=document.getElementById('borrowerPhotoDisplay');let mode='Borrow',images=[],borrowerImage=null,cameraTarget='equipment';const formParams=new URLSearchParams(location.search);

function initBorrowerField(){
    const passedBorrower=formParams.get('borrower')||'';
    if(passedBorrower){
        let matched=false;
        for(let opt of borrowerSelect.options){
            if(opt.value===passedBorrower){
                borrowerSelect.value=passedBorrower;
                borrowerName.value=passedBorrower;
                manualBorrowerWrap.style.display='none';
                if(opt.dataset.phone&&!borrowerPhone.value)borrowerPhone.value=opt.dataset.phone;
                matched=true;
                break;
            }
        }
        if(!matched){
            borrowerSelect.value='__custom__';
            manualBorrowerWrap.style.display='block';
            borrowerName.value=passedBorrower;
        }
    }else{
        borrowerName.value='';
    }
}
if(borrowerSelect){
    borrowerSelect.addEventListener('change',()=>{
        const val=borrowerSelect.value;
        if(val==='__custom__'){
            manualBorrowerWrap.style.display='block';
            borrowerName.value='';
            borrowerName.focus();
        }else if(val===''){
            manualBorrowerWrap.style.display='none';
            borrowerName.value='';
        }else{
            manualBorrowerWrap.style.display='none';
            borrowerName.value=val;
            const opt=borrowerSelect.options[borrowerSelect.selectedIndex];
            if(opt&&opt.dataset.phone){
                borrowerPhone.value=opt.dataset.phone;
            }
        }
    });
}
initBorrowerField();
itemName.value=formParams.get('item')||'';
if(!lenderName.value){lenderName.value=formParams.get('lender')||'<?= htmlspecialchars($currentUser["full_name"] ?? "", ENT_QUOTES) ?>';}
function setMode(next){mode=next;modeButtons.forEach(b=>b.classList.toggle('active',b.dataset.mode===mode));returnOnly.forEach(e=>e.style.display=mode==='Return'?'block':'none');returnHelp.style.display=mode==='Return'?'block':'none';if(borrowerGallery)borrowerGallery.style.display=mode==='Return'?'none':'block';photoTitle.innerHTML=mode==='Return'?'หลักฐานสภาพขณะรับคืน <span class="required">*</span>':'หลักฐานสภาพก่อนส่งมอบ <span class="required">*</span>';submit.textContent=mode==='Return'?'บันทึกการรับคืนและตรวจสภาพ':'บันทึกการส่งมอบพร้อมรูปภาพ';images=[];render();renderBorrowerPhoto();}modeButtons.forEach(b=>b.onclick=()=>setMode(b.dataset.mode));
function render(){photos.innerHTML=images.length?'':'<div class="empty">ยังไม่มีรูปภาพ</div>';images.forEach((src,i)=>{const card=document.createElement('div');card.className='photo';card.innerHTML=`<img src="${src}" alt="รูปที่ ${i+1}"><button type="button">ลบรูป</button>`;card.querySelector('button').onclick=()=>{images.splice(i,1);render()};photos.appendChild(card)})}
function renderBorrowerPhoto(){if(!borrowerPhotoDisplay)return;if(borrowerImage){borrowerPhotoDisplay.innerHTML=`<div class="borrower-photo-card"><img src="${borrowerImage}" alt="รูปถ่ายผู้ยืม"><button type="button" id="removeBorrowerBtn">ลบรูปผู้ยืม</button></div>`;document.getElementById('removeBorrowerBtn').onclick=()=>{borrowerImage=null;renderBorrowerPhoto();};}else{borrowerPhotoDisplay.innerHTML='<div class="empty">ยังไม่มีรูปถ่ายผู้ยืม</div>';}}
function read(files){Array.from(files).forEach(f=>{const r=new FileReader();r.onload=e=>{images.push(e.target.result);render()};r.readAsDataURL(f)});file.value=''}
document.getElementById('picker').onclick=()=>{file.removeAttribute('capture');file.click()};
document.getElementById('pickerBorrower').onclick=()=>{fileBorrower.removeAttribute('capture');fileBorrower.click()};
fileBorrower.onchange=e=>{const f=e.target.files[0];if(f){const r=new FileReader();r.onload=ev=>{borrowerImage=ev.target.result;renderBorrowerPhoto();};r.readAsDataURL(f);}fileBorrower.value='';};
const cameraModal=document.getElementById('cameraModal'),cameraPreview=document.getElementById('cameraPreview'),cameraModalTitle=document.getElementById('cameraModalTitle');let cameraStream=null;
function closeCamera(){if(cameraStream)cameraStream.getTracks().forEach(track=>track.stop());cameraStream=null;cameraPreview.srcObject=null;cameraModal.hidden=true}
async function openCamera(target='equipment',facing='environment'){cameraTarget=target;if(cameraModalTitle)cameraModalTitle.textContent=target==='borrower'?'กล้องถ่ายรูปผู้ยืม':'กล้องถ่ายรูปอุปกรณ์';if(navigator.mediaDevices&&navigator.mediaDevices.getUserMedia){try{let stream=null;try{stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:facing}},audio:false});}catch(err1){try{stream=await navigator.mediaDevices.getUserMedia({video:true,audio:false});}catch(err2){stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'user'},audio:false});}}cameraStream=stream;cameraPreview.srcObject=cameraStream;cameraModal.hidden=false;return;}catch(error){console.error('Camera error:',error);alert('ไม่สามารถเปิดกล้องได้: '+(error.message||error.name)+'\n\nระบบจะเปิดหน้าต่างเลือกไฟล์รูปภาพแทนครับ');}}if(target==='borrower'){fileBorrower.removeAttribute('capture');fileBorrower.click();}else{file.setAttribute('capture','environment');file.click();}}
document.getElementById('camera').onclick=()=>openCamera('equipment','environment');
document.getElementById('cameraBorrower').onclick=()=>openCamera('borrower','user');
document.getElementById('cameraClose').onclick=closeCamera;cameraModal.addEventListener('click',event=>{if(event.target===cameraModal)closeCamera()});
document.getElementById('cameraCapture').onclick=()=>{if(!cameraPreview.videoWidth)return;const canvas=document.createElement('canvas');canvas.width=cameraPreview.videoWidth;canvas.height=cameraPreview.videoHeight;canvas.getContext('2d').drawImage(cameraPreview,0,0);const data=canvas.toDataURL('image/jpeg',.9);if(cameraTarget==='borrower'){borrowerImage=data;renderBorrowerPhoto();}else{images.push(data);render();}closeCamera()};
file.onchange=e=>read(e.target.files);window.addEventListener('paste',e=>{const items=(e.clipboardData||window.clipboardData)?.items;if(!items)return;for(let item of items){if(item.type&&item.type.startsWith('image/')){const blob=item.getAsFile();if(blob){const r=new FileReader();r.onload=ev=>{images.push(ev.target.result);render();};r.readAsDataURL(blob);}}}});
submit.onclick=async()=>{const borrower=borrowerName.value.trim(),item=itemName.value.trim();if(!borrower||!item)return alert('กรุณาเลือกหรือกรอกชื่อผู้ยืม และระบุชื่ออุปกรณ์');if(mode==='Borrow'&&!borrowerImage)return alert('กรุณาเปิดกล้องหรือแนบรูปถ่ายผู้ยืม 1 รูป');if(!images.length)return alert(mode==='Return'?'กรุณาถ่ายรูปหรือแนบรูปภาพสภาพอุปกรณ์ขณะรับคืนอย่างน้อย 1 รูป':'กรุณาถ่ายรูปหรือแนบรูปภาพสภาพอุปกรณ์ก่อนส่งมอบอย่างน้อย 1 รูป');const fd=new FormData();fd.append('action_type',mode);fd.append('borrower_name',borrower);fd.append('item_name',item);fd.append('sub_item_name',subItemName.value);fd.append('borrower_phone',borrowerPhone.value);fd.append('borrow_duration',borrowDuration.value);fd.append('lender_name',lenderName.value);fd.append('department',formParams.get('department')||'<?= htmlspecialchars($currentUser["dept_name"] ?? "แผนกโครงข่ายสื่อสารหลัก", ENT_QUOTES) ?>');fd.append('condition_status',document.querySelector('input[name="condition"]:checked').value);fd.append('condition_note',conditionNote.value);if(mode==='Borrow'&&borrowerImage)fd.append('borrower_image',borrowerImage);if(mode==='Return')fd.append('borrow_id',formParams.get('id')||'0');images.forEach(i=>fd.append('image[]',i));try{const r=await fetch('save_record.php',{method:'POST',body:fd}),t=await r.text();alert(t);if(r.ok){if(window.parent!==window)window.parent.postMessage({type:'itemflow-saved'},location.origin);else location.reload()}}catch(e){alert('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้')}};render();renderBorrowerPhoto();if(formParams.get('form')==='return')setMode('Return');
</script></body></html>


