<?php
// ไฟล์นี้คงไว้เพื่อความเข้ากันได้แบบย้อนหลัง (Backward Compatibility)
// โดยจะเปลี่ยนเส้นทางไปยังหน้าแรกหลักของระบบ (index.php) อัตโนมัติ พร้อมส่งต่อพารามิเตอร์ทั้งหมด
$queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: index.php' . $queryString, true, 301);
exit;
