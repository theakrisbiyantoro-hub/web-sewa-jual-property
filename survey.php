<?php
include "database.php";
session_start();

/* ====== (opsional) proteksi admin ======
   kalau kamu belum pakai role, boleh hapus blok ini */
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: regislogin.php");
    exit;
}

/* ====== HAPUS DATA ====== */
if (isset($_GET["delete"])) {
    $id = (int)$_GET["delete"];
    $stmt = $koneksi->prepare("DELETE FROM survey WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: survey.php?msg=deleted");
    exit;
}

/* ====== AMBIL DATA ====== */
$res = $koneksi->query("SELECT id, full_name, phone, pesan, created_at FROM survey ORDER BY id DESC");
$surveys = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$totalSurveys = count($surveys);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Survey - PropertyKu</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.4.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
:root{--primary:#5c6a65;--primary-soft:#EAF1FF;--bg:#F4F6F8;--text-main:#1F2937;--text-soft:#6B7280;--border-soft:#E5E7EB;--card:#fff;}
*{margin:0;padding:0;box-sizing:border-box;font-family:"Poppins",sans-serif;}
body{background:var(--bg);padding:40px;}
.app{background:var(--card);border-radius:32px;box-shadow:0 18px 45px rgba(0,0,0,.08);display:flex;overflow:hidden;min-height:600px;}
/* SIDEBAR */
.sidebar{flex:0 0 230px;width:230px;background:#fff;border-right:1px solid var(--border-soft);padding:28px 22px;display:flex;flex-direction:column;transition:width .25s ease,padding .25s ease;overflow:hidden;}
.sidebar-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;}
.sidebar-top-title{font-size:18px;color:var(--text-main);margin:0;line-height:1;}
.hamburger{background:var(--primary-soft);border:none;border-radius:12px;padding:6px 8px;cursor:pointer;color:var(--primary);font-size:18px;display:inline-flex;align-items:center;justify-content:center;}
.profile{display:flex;align-items:center;gap:15px;flex-direction:column;text-align:center;margin-top:10px;margin-bottom:10px;}
.profile-avatar{width:54px;height:54px;border-radius:16px;background-image:url("profile.jpeg");background-size:cover;background-position:center;background-repeat:no-repeat;}
.profile-info h3{font-size:12px;font-weight:600;color:var(--primary);margin:0;}
.profile-info span{font-size:10px;color:var(--text-soft);display:block;margin-top:4px;}
.sidebar-title{font-size:13px;font-weight:500;text-transform:uppercase;margin:18px 0 8px;color:var(--text-soft);}
.menu{list-style:none;}
.menu li a{display:flex;align-items:center;gap:10px;padding:10px 12px;margin-bottom:5px;text-decoration:none;font-size:14px;color:var(--text-soft);border-radius:12px;}
.menu li a i{font-size:18px;}
.menu li a.active,.menu li a:hover{background:var(--primary-soft);color:var(--primary);}
/* COLLAPSE SIDEBAR */
.sidebar.collapsed{width:60px;flex:0 0 60px;padding:18px 8px;}
.sidebar.collapsed .sidebar-top{justify-content:center;}
.sidebar.collapsed .sidebar-top-title{display:none;}
.sidebar.collapsed .profile,.sidebar.collapsed .sidebar-title,.sidebar.collapsed .menu{display:none;}
/* MAIN */
.main{flex:1;padding:32px;background:#F7F8FA;min-width:0;}
.header-box{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;}
.header-box h2{margin-left:10px;font-size:22px;color:var(--text-main);}
.search-wrapper{display:flex;align-items:center;gap:10px;}
.search-box{background:#fff;border-radius:999px;padding:8px 14px;display:flex;align-items:center;gap:8px;border:1px solid var(--border-soft);width:250px;}
.search-box input{border:none;outline:none;font-size:13px;background:transparent;width:100%;}
.search-box i{color:var(--text-soft);}
.badge{background:var(--primary-soft);color:var(--primary);padding:8px 14px;border-radius:999px;font-size:13px;font-weight:600;border:1px solid var(--border-soft);}
/* ALERT */
.alert{background:#e8fff1;border:1px solid #b7f0cc;color:#166534;padding:10px 14px;border-radius:14px;font-size:13px;margin-bottom:14px;}
/* TABLE */
.table-box{background:#fff;padding:20px;border-radius:20px;box-shadow:0 8px 22px rgba(0,0,0,.05);}
.table-scroll{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;touch-action:pan-x;}
.table-scroll table{width:100%;min-width:900px;border-collapse:collapse;}
th,td{text-align:center;padding:10px 8px;border-bottom:1px solid #eee;font-size:14px;}
th{color:var(--text-main);font-weight:600;font-size:15px;}
td.pesan-cell{text-align:left;max-width:520px;white-space:pre-wrap;word-break:break-word;}
td.waktu-cell{color:var(--text-soft);font-size:13px;white-space:nowrap;}
/* BUTTON */
.action-btn{padding:6px 14px;border-radius:8px;font-size:12px;cursor:pointer;border:none;text-decoration:none;display:inline-block;}
.delete-btn{background:#ffe8e8;color:#d11a1a;}
.delete-btn:hover{background:#ffd1d1;}

.overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);opacity:0;pointer-events:none;transition:.2s;z-index:150;}
.overlay.show{opacity:1;pointer-events:auto;}
.mobile-topbar{display:none;align-items:center;gap:12px;padding:8px 10px;border-radius:16px;margin-bottom:12px;top:10px;z-index:200;position:sticky;}
/* RESPONSIVE */
@media(max-width:1100px){.header-box{flex-direction:column;align-items:flex-start;gap:12px;}.search-wrapper{width:100%;flex-direction:column;align-items:stretch;gap:10px;}.search-box{width:100%;}}
@media(max-width:600px){
  body{padding:12px;}
  .app{border-radius:24px;overflow:visible;}
  .main{padding:14px;}
  .mobile-topbar{display:flex;}
  .sidebar .hamburger{display:none;}
  .sidebar{position:fixed;left:0;top:0;bottom:0;width:260px;transform:translateX(-105%);transition:.25s;box-shadow:0 18px 45px rgba(0,0,0,.18);z-index:300;}
  .sidebar.open{transform:translateX(0);}
  .table-scroll table{min-width:950px;width:950px;}
}
</style>
</head>

<body>
<div id="overlay" class="overlay"></div>

<div class="app">
    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-top">
            <h4 class="sidebar-top-title">Dashboard</h4>
            <button id="toggleSidebar" class="hamburger" type="button">
                <i class="ri-menu-line"></i>
            </button>
        </div>

        <div class="profile">
            <div class="profile-avatar"></div>
            <div class="profile-info">
                <h3>Admin Head</h3>
                <span>PropertyKu</span>
            </div>
        </div>

        <div>
            <div class="sidebar-title">Menu</div>
            <ul class="menu">
                <li><a href="dashboard.php"><i class="ri-dashboard-line"></i> Dashboard</a></li>
                <li><a href="homeproper.php"><i class="ri-home-6-line"></i> Rumah</a></li>
                <li><a href="apartproper.php"><i class="ri-building-4-line"></i> Apartemen</a></li>
                <li><a href="dataproper.php"><i class="ri-database-2-line"></i> Data Properti</a></li>
                <li><a class="active" href="survey.php"><i class="ri-send-plane-line"></i> Survey</a></li>
                <li><a href="message.php"><i class="ri-mail-line"></i> Message</a></li>
                <li><a href="riwayatadmin.php"><i class="ri-file-list-3-line"></i> Riwayat</a></li>
            </ul>

            <div class="sidebar-title">Akun</div>
            <ul class="menu">
                <li><a href="logout.php"><i class="ri-logout-circle-r-line"></i> Logout</a></li>
            </ul>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main">
        <div class="mobile-topbar">
            <button id="mobileHamburger" class="hamburger" type="button" aria-label="Menu">
                <i class="ri-menu-line"></i>
            </button>
            <div class="mobile-topbar-title">Survey</div>
        </div>
        <div class="header-box">
            <h2>Data Survey</h2>

            <div class="search-wrapper">
                <div class="search-box">
                    <i class="ri-search-line"></i>
                    <input type="text" id="searchInput" placeholder="Cari nama / no hp / pesan">
                </div>
                <div class="badge">Total: <?= (int)$totalSurveys ?></div>
            </div>
        </div>

        <?php if (isset($_GET["msg"]) && $_GET["msg"] === "deleted"): ?>
            <div class="alert">Survey berhasil dihapus.</div>
        <?php endif; ?>

        <div class="table-box">
            <div class="table-scroll">
                <table id="surveyTable">
                    <thead>
                        <tr>
                            <th style="width:70px;">ID</th>
                            <th style="width:180px;">Nama</th>
                            <th style="width:150px;">No. HP</th>
                            <th>Pesan</th>
                            <th style="width:170px;">Waktu</th>
                            <th style="width:120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($surveys)): ?>
                            <tr>
                                <td colspan="6" style="color:var(--text-soft);padding:18px;">
                                    Belum ada survey masuk.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($surveys as $s): ?>
                                <tr>
                                    <td><?= (int)$s["id"] ?></td>
                                    <td class="name-cell"><?= htmlspecialchars($s["full_name"] ?? "") ?></td>
                                    <td class="phone-cell"><?= htmlspecialchars($s["phone"] ?? "") ?></td>
                                    <td class="pesan-cell"><?= htmlspecialchars($s["pesan"] ?? "") ?></td>
                                    <td class="waktu-cell"><?= htmlspecialchars($s["created_at"] ?? "") ?></td>
                                    <td>
                                        <a class="action-btn delete-btn"
                                           href="survey.php?delete=<?= (int)$s["id"] ?>"
                                           onclick="return confirm('Hapus survey ini?')">
                                           Hapus
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>
/* ===== sidebar toggle (desktop) ===== */
const sidebar = document.getElementById("sidebar");
const toggleBtn = document.getElementById("toggleSidebar"); // desktop button (di sidebar)
const mobileBtn = document.getElementById("mobileHamburger"); // mobile button (di main)
const overlay = document.getElementById("overlay");

function isMobile(){ return window.matchMedia("(max-width: 600px)").matches; }

function openSidebar(){
  sidebar.classList.add("open");
  overlay.classList.add("show");
  document.body.style.overflow = "hidden";
}

function closeSidebar(){
  sidebar.classList.remove("open");
  overlay.classList.remove("show");
  document.body.style.overflow = "";
}

function toggleSidebar(){
  if (isMobile()){
    sidebar.classList.contains("open") ? closeSidebar() : openSidebar();
  } else {
    sidebar.classList.toggle("collapsed");
  }
}

if (toggleBtn) toggleBtn.addEventListener("click", toggleSidebar);
if (mobileBtn) mobileBtn.addEventListener("click", toggleSidebar);
if (overlay) overlay.addEventListener("click", closeSidebar);

window.addEventListener("resize", () => {
  if (!isMobile()) closeSidebar(); // kalau balik ke desktop, tutup overlay mobile
});

/* ===== search filter (frontend) ===== */
const searchInput = document.getElementById("searchInput");
const table = document.getElementById("surveyTable");
if (searchInput && table) {
  searchInput.addEventListener("keyup", () => {
    const q = searchInput.value.toLowerCase();
    const rows = table.querySelectorAll("tbody tr");
    rows.forEach(row => {
      const text = row.innerText.toLowerCase();
      row.style.display = text.includes(q) ? "" : "none";
    });
  });
}
</script>

</body>
</html>
