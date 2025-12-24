<?php
include "database.php";
session_start();



// PROSES HAPUS PROPERTY
if (isset($_POST['hapus_id'])) {
    $id = intval($_POST['hapus_id']);

    // Hapus, media otomatis terhapus karena ON DELETE CASCADE
    $koneksi->query("DELETE FROM property WHERE id = $id");

    // Refresh agar tetap di halaman ini
    header("Location: dataproper.php");
    exit;
}

// Ambil semua properti
// FILTER & SEARCH
$filter = $_GET['filter'] ?? 'ALL';
$search = $_GET['search'] ?? '';

// Base query
$sql = "SELECT * FROM property WHERE 1";

// Filter tipe
if ($filter !== 'ALL') {
    $f = $koneksi->real_escape_string($filter);
    $sql .= " AND type = '$f'";
}

// Search nama properti
if (!empty($search)) {
    $s = $koneksi->real_escape_string($search);
    $sql .= " AND nama LIKE '%$s%'";
}

$sql .= " ORDER BY id DESC";
$q = $koneksi->query($sql);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Data Properti - PropertyKu</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.4.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
:root{--primary:#5c6a65;--primary-soft:#EAF1FF;--bg:#F4F6F8;--text-main:#1F2937;--text-soft:#6B7280;--border-soft:#E5E7EB;--card:#fff;}
*{margin:0;padding:0;box-sizing:border-box;font-family:"Poppins",sans-serif;}
body{background:var(--bg);padding:40px;}
.app{background:var(--card);border-radius:32px;box-shadow:0 18px 45px rgba(0,0,0,.08);display:flex;overflow:hidden;min-height:600px;}
.sidebar{flex:0 0 230px;width:230px;background:#fff;border-right:1px solid var(--border-soft);padding:28px 22px;display:flex;flex-direction:column;transition:width .25s ease,padding .25s ease;overflow:hidden;}
.profile{display:flex;align-items:center;gap:15px;margin-bottom:10px;flex-direction:column;text-align:center;margin-top:10px;}
.profile-avatar{width:54px;height:54px;border-radius:16px;background-image:url("profile.jpeg");background-size:cover;background-position:center;background-repeat:no-repeat;}
.profile-info h3{font-size:12px;font-weight:600;color:var(--primary);margin:0;}
.profile-info span{font-size:10px;color:var(--text-soft);display:block;margin-top:4px;}
.sidebar-title{font-size:13px;font-weight:500;text-transform:uppercase;margin:18px 0 8px;color:var(--text-soft);}
.menu{list-style:none;}
.menu li a{display:flex;align-items:center;gap:10px;padding:10px 12px;margin-bottom:5px;text-decoration:none;font-size:14px;color:var(--text-soft);border-radius:12px;}
.menu li a i{font-size:18px;}
.menu li a.active,.menu li a:hover{background:var(--primary-soft);color:var(--primary);}
.main{flex:1;padding:32px;background:#F7F8FA;transition:all .25s ease;min-width:0;}
.table-box{background:#fff;padding:20px;border-radius:20px;box-shadow:0 8px 22px rgba(0,0,0,.05);}
.table-scroll{width:100%;overflow-x:auto;overflow-y:hidden;-webkit-overflow-scrolling:touch;touch-action:pan-x;}
.table-scroll table{width:max-content;min-width:900px;border-collapse:collapse;}
th,td{text-align:center;padding:10px 8px;border-bottom:1px solid #eee;font-size:14px;}
th{text-align:center;color:var(--text-main);font-weight:600;font-size:15px;}
td img{width:130px;height:95px;object-fit:cover;border-radius:9px;display:block;}
td:nth-child(1),th:nth-child(1){width:150px;}
td:nth-child(2),th:nth-child(2){width:120px;word-wrap:break-word;}
td:nth-child(4),th:nth-child(4){width:140px;}
td:nth-child(7),th:nth-child(7){width:150px;}
.action-btn{padding:6px 14px;border-radius:8px;font-size:12px;cursor:pointer;border:none;}
.edit-btn{background-color: #e5f4e6ff; color:var(--primary);}
.edit-btn:hover{background:#DEE8FF;}
.delete-btn{background:#ffe8e8;color:#d11a1a;}
.delete-btn:hover{background:#ffd1d1;}
.status-tag{padding:5px 10px;border-radius:8px;font-size:12px;}
.stok-ada{background:#EAF1FF;color:#2F4A72;}
.stok-booking{background:#F3F4F6;color:#4B5563;}
.stok-sold{background:#ffeaea;color:#d60000;}
.search-box{background:#fff;border-radius:999px;padding:8px 14px;display:flex;align-items:center;gap:8px;border:1px solid var(--border-soft);width:250px;}
.search-box input{border:none;outline:none;font-size:13px;background:transparent;width:100%;}
.search-box i{color:var(--text-soft);}
.header-box{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;}
.header-box h2{margin-left:10px;}
.search-wrapper{display:flex;align-items:center;gap:10px;}
.filter-btn{padding:6px 14px;border:none;border-radius:20px;background:var(--primary-soft);color:var(--primary);cursor:pointer;font-size:13px;}
.filter-btn.active{background:var(--primary);color:#fff;}
.sidebar-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;}
.sidebar-top-title{font-size:18px;color:var(--text-main);margin:0;line-height:1;}
.sidebar.collapsed .sidebar-top{justify-content:center;}
.sidebar.collapsed .sidebar-top-title{display:none;}
.hamburger{background:var(--primary-soft);border:none;border-radius:12px;padding:6px 8px;cursor:pointer;color:var(--primary);font-size:18px;display:inline-flex;align-items:center;justify-content:center;}
.sidebar.collapsed{width:60px;flex:0 0 60px;padding:18px 8px;}
.sidebar.collapsed .profile,.sidebar.collapsed .sidebar-title,.sidebar.collapsed .menu{display:none;}
.mobile-topbar{display:none;align-items:center;gap:12px;padding:8px 10px;border-radius:16px;margin-bottom:12px;top:10px;z-index:200;position:sticky;}
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);opacity:0;pointer-events:none;transition:.2s;z-index:150;}
.overlay.show{opacity:1;pointer-events:auto;}

@media(max-width:1100px){
.header-box{flex-direction:column;align-items:flex-start;gap:12px;}
.search-wrapper{width:100%;flex-direction:column;align-items:stretch;gap:10px;}
.search-box{width:100%;}
.search-wrapper>div:last-child{display:flex;gap:8px;flex-wrap:wrap;}
}
@media(max-width:600px){
body{padding:12px;}
.app{border-radius:24px;overflow:visible;}
.sidebar{position:fixed;left:0;top:0;bottom:0;width:260px;transform:translateX(-105%);transition:.25s;box-shadow:0 18px 45px rgba(0,0,0,.18);z-index:300;}
.sidebar.open{transform:translateX(0);}
.overlay.show{opacity:1;pointer-events:auto;}
.main{padding:14px;margin-top:0;}
.mobile-topbar{display:flex;}
.sidebar .hamburger{display:none;}
.table-scroll{width:100%;overflow-x:auto;overflow-y:hidden;-webkit-overflow-scrolling:touch;overscroll-behavior-x:contain;touch-action:pan-x;}
.table-scroll table{min-width:950px;width:950px;}
}
</style>

</head>
<body>
<div id="overlay" class="overlay"></div>
<div class="app">

    <!-- SIDEBAR -->
    <aside class="sidebar">
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
                <li><a class="active" href="dataproper.php"><i class="ri-database-2-line"></i> Data Properti</a></li>
                <li><a href="survey.php"><i class="ri-send-plane-line"></i> Survey</a></li>
                <li><a href="message.php"><i class="ri-mail-line"></i> Messages</a></li>
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
        </div>
        <div class="table-box">
           <div class="header-box">
                <h2>Data Properti</h2>
                <div class="search-wrapper">
                    <div class="search-box">
                        <i class="ri-search-line"></i>
                        <input type="text" name="search" id="searchInput" placeholder="Cari nama properti" autocomplete="off">
                        <i class="ri-close-line" id="clearSearch" style="cursor:pointer; display:none;"></i>
                    </div>
                    <div>
                        <button class="filter-btn active" data-type="ALL">All</button>
                        <button class="filter-btn" data-type="RUMAH">Rumah</button>
                        <button class="filter-btn" data-type="APARTEMENT">Apartemen</button>
                    </div>
                </div>
            </div>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr data-type="<?= $row['type'] ?>">
                            <th>Foto</th>
                            <th>Nama<br>Properti</th>
                            <th>Lokasi</th>
                            <th>Harga</th>
                            <th>Kategori</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php while($row = $q->fetch_assoc()) { ?>
                        <tr data-type="<?= $row['type'] ?>">
                            <td><img src="uploads/<?= $row['cover'] ?>" alt=""></td>
                            <td><?= $row['nama'] ?></td>
                            <td><?= $row['lokasi'] ?></td>
                            <td>Rp <?= number_format($row['harga'],0,',','.') ?></td>
                            <td><?= $row['kategori'] ?></td>
                            <td>
                                <?php if($row['status'] == "Tersedia") { ?>
                                    <span class="status-tag stok-ada">Tersedia</span>
                                <?php } elseif($row['status'] == "Terbooking") { ?>
                                    <span class="status-tag stok-booking">Terbooking</span>
                                <?php } else { ?>
                                    <span class="status-tag stok-sold">Terjual</span>
                                <?php } ?>
                            </td>
                            <td>
                                <!-- EDIT -->
                                <?php $editPage = ($row['type'] === "APARTEMENT") ? "apartproper.php" : "homeproper.php";?>
                                <button class="action-btn edit-btn" onclick="window.location.href='<?= $editPage ?>?mode=edit&id=<?= $row['id'] ?>'">Edit</button>
                                <!-- HAPUS DENGAN POST (TETAP DI HALAMAN INI) -->
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="hapus_id" value="<?= $row['id'] ?>">
                                    <button class="action-btn delete-btn" onclick="return confirm('Apakah Anda yakin ingin menghapus properti ini?')">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const input = document.getElementById("searchInput");
    const rows = document.querySelectorAll("tbody tr");
    const clearBtn = document.getElementById("clearSearch");
    const filterButtons = document.querySelectorAll(".filter-btn");
    let selectedFilter = "ALL"; 
    function applyFilter() {
        const keyword = input.value.toLowerCase();
        clearBtn.style.display = keyword.length > 0 ? "block" : "none";
        rows.forEach(row => {
            const nama = row.children[1].innerText.toLowerCase();
            const tipe = row.getAttribute("data-type"); 
            const matchSearch = nama.includes(keyword);
            const matchFilter = (selectedFilter === "ALL" || selectedFilter === tipe);
            row.style.display = (matchSearch && matchFilter) ? "" : "none";
        });
    }
    // Search realtime
    input.addEventListener("input", applyFilter);
    // Clear search
    clearBtn.addEventListener("click", () => {
        input.value = "";
        applyFilter();
    });
    // Filter button click
    filterButtons.forEach(btn => {
        btn.addEventListener("click", () => {
            filterButtons.forEach(b => b.classList.remove("active"));
            btn.classList.add("active");
            selectedFilter = btn.getAttribute("data-type");
            applyFilter();
        });
    });
    const sidebar  = document.querySelector(".sidebar");
    const toggleBtn = document.getElementById("toggleSidebar");     // desktop
    const mobileBtn = document.getElementById("mobileHamburger");   // mobile
    const overlay  = document.getElementById("overlay");

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

    toggleBtn && toggleBtn.addEventListener("click", toggleSidebar);
    mobileBtn && mobileBtn.addEventListener("click", toggleSidebar);
    overlay && overlay.addEventListener("click", closeSidebar);

    window.addEventListener("resize", () => {
        if (!isMobile()) closeSidebar();
    });
});
</script>
</body>
</html>
