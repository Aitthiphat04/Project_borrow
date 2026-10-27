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
            /* ปรับให้ขาวสว่างขึ้น */
            --text-muted: #b0b0c0;
            /* ปรับเทาให้สว่างขึ้น */
            --navbar-bg: #301b5e;
            --sidebar-hover: #35355e;
            --border-color: #44446b;
            --input-bg: #1a1a2e;
            --input-text: #ffffff;
            /* ตัวหนังสือในช่องกรอกให้เป็นสีขาว */
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
            font-weight: 500;
        }

        .btn-custom-yellow:hover {
            background-color: #e5b337;
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

        /* ปรับสีเมนูในโหมดมืด */

        .nav-pills .nav-link:hover {
            background-color: var(--sidebar-hover);
        }

        .nav-pills .nav-link.active {
            background-color: #5c3b99;
            color: white !important;
        }

        [data-theme="dark"] .nav-pills .nav-link.active {
            background-color: #6a44b8;
            /* ปรับสีม่วงให้สว่างขึ้นในโหมดมืด */
        }

        /* จัดการสไตล์ช่องกรอกข้อมูล (Input & Select) */
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

        /* ปรับสี Placeholder (คำใบ้ในช่องกรอก) */
        .form-control::placeholder {
            color: var(--input-placeholder);
            opacity: 1;
        }

        /* ปรับแต่ง Dropdown Select (option) สำหรับโหมดมืด */
        [data-theme="dark"] select option {
            background-color: #252542;
            color: white;
        }

        .avatar-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: #eaddf5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #5c3b99;
            margin-bottom: 15px;
        }

        [data-theme="dark"] .avatar-preview {
            background-color: #442a75;
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
            <a href="index.php" class="text-decoration-none d-flex align-items-center">
                <div class="bg-white rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 55px; height: 55px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                    <img src="https://upload.wikimedia.org/wikipedia/th/thumb/a/a4/Provincial_Electricity_Authority_Logo.svg/800px-Provincial_Electricity_Authority_Logo.svg.png" alt="Logo" style="width: 35px;">
                </div>
                <div>
                    <h4 class="m-0 fw-bold text-white">ตั้งค่าระบบ</h4>
                    <div style="font-size: 0.8rem; color: #d8ccee;">จัดการข้อมูลส่วนตัวและการแสดงผล</div>
                </div>
            </a>
        </div>
        <div>
            <a href="index.php" class="btn btn-light fw-bold" style="border-radius: 25px; padding: 8px 20px; color: #4b2f89;">กลับหน้าหลัก</a>
        </div>
    </div>

    <div class="settings-container">
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
                                <div class="avatar-preview me-4">W</div>
                                <div>
                                    <button class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold">เปลี่ยนรูปโปรไฟล์</button>
                                    <div class="text-muted-custom mt-2" style="font-size: 0.85rem;">รองรับไฟล์ JPG, PNG ขนาดไม่เกิน 2MB</div>
                                </div>
                            </div>
                            <form>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label text-muted-custom fw-bold">ชื่อ-นามสกุล</label>
                                        <input type="text" class="form-control fw-bold" value="วุฒิชัย ชั่งทอง">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-muted-custom fw-bold">รหัสพนักงาน</label>
                                        <input type="text" class="form-control fw-bold" value="EMP-10293">
                                    </div>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label text-muted-custom fw-bold">เบอร์โทรศัพท์ (สำหรับติดต่อรับของ)</label>
                                        <input type="text" class="form-control fw-bold" value="0865559988" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-muted-custom fw-bold">เลือกแผนก / หน่วยงาน</label>
                                        <select class="form-select fw-bold">
                                            <option selected>แผนกโครงข่ายสื่อสารหลัก</option>
                                            <option>แผนกระบบสื่อสารสนับสนุนงานระบบไฟฟ้า</option>
                                            <option>แผนกเทคโนโลยีการสื่อสารองค์กร</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-custom-yellow px-4 fw-bold">บันทึกการเปลี่ยนแปลง</button>
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
                            <div class="mb-5">
                                <h6 class="fw-bold text-muted-custom mb-3">เปลี่ยนรหัสผ่าน</h6>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <input type="password" class="form-control mb-2 fw-bold" placeholder="รหัสผ่านปัจจุบัน">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <input type="password" class="form-control mb-2 fw-bold" placeholder="รหัสผ่านใหม่">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="password" class="form-control fw-bold" placeholder="ยืนยันรหัสผ่านใหม่">
                                    </div>
                                </div>
                                <button class="btn btn-custom-yellow px-4 mt-2 fw-bold">อัปเดตรหัสผ่าน</button>
                            </div>
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