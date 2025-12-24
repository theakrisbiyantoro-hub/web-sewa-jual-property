<?php
include "database.php";
session_start();

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") die("Akses ditolak!");

$rumah = $koneksi->query("SELECT * FROM property WHERE type='RUMAH' ORDER BY id DESC");
$apart = $koneksi->query("SELECT * FROM property WHERE type='APARTEMENT' ORDER BY id DESC");
$latest = $koneksi->query("SELECT id, nama, type, lokasi, cover FROM property ORDER BY id DESC LIMIT 3");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Panel - PropertyKu</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.4.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --primary: #5c6a65;
            --primary-soft: #EAF1FF;
            --bg: #F4F6F8;
            --text-main: #1F2937;
            --text-soft: #6B7280;
            --card: #FFFFFF;
            --border-soft: #E5E7EB;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Poppins", sans-serif;
        }

        body {
            background: var(--bg);
            padding: 40px;
        }

        .app {
            background: var(--card);
            border-radius: 32px;
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.08);
            display: grid;
            overflow: hidden;
            min-height: 600px;
            grid-template-columns: auto 1.4fr 0.7fr;
            grid-template-areas: "sidebar main right";
        }

        .sidebar {
            width: 230px;
            background: #FFFFFF;
            border-right: 1px solid var(--border-soft);
            padding: 28px 22px;
            display: flex;
            flex-direction: column;
            grid-area: sidebar;
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
            flex-direction: column;
            text-align: center;
            margin-top: 10px;
        }

        .profile-avatar {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            background-image: url("profile.jpeg");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        .profile-info h3 {
            font-size: 12px;
            font-weight: 600;
            color: var(--primary);
            margin: 0;
        }

        .profile-info span {
            font-size: 10px;
            color: var(--text-soft);
            display: block;
            margin-top: 4px;
        }

        .sidebar-title {
            font-size: 13px;
            font-weight: 500;
            text-transform: uppercase;
            margin: 18px 0 8px;
            color: var(--text-soft);
        }

        .menu {
            list-style: none;
        }

        .menu li a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            margin-bottom: 5px;
            text-decoration: none;
            font-size: 14px;
            color: var(--text-soft);
            border-radius: 12px;
        }

        .menu li a i {
            font-size: 18px;
        }

        .menu li a.active,
        .menu li a:hover {
            background: var(--primary-soft);
            color: var(--primary);
        }

        .main {
            flex: 1.4;
            background: #F3F6FB;
            padding: 26px 26px 26px 26px;
            display: flex;
            flex-direction: column;
            gap: 26px;
            grid-area: main;
            min-width: 0;
        }

        .search-box {
            background: #FFFFFF;
            border-radius: 999px;
            padding: 8px 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--border-soft);
            min-width: 260px;
        }

        .search-box input {
            border: none;
            outline: none;
            font-size: 13px;
            background: transparent;
            width: 100%;
        }

        .search-box i {
            color: var(--text-soft);
            font-size: 18px;
        }

        .hero {
            background: var(--primary-soft);
            border-radius: 24px;
            padding: 24px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .hero-text {
            max-width: 55%;
        }

        .hero-text h2 {
            font-size: 24px;
            line-height: 1.25;
            margin-bottom: 8px;
        }

        .hero-text p {
            font-size: 13px;
            color: var(--text-soft);
            margin-bottom: 14px;
        }

        .hero-text button {
            border: none;
            background: var(--primary);
            color: #fff;
            padding: 9px 18px;
            border-radius: 999px;
            font-size: 13px;
            cursor: pointer;
        }

        .hero-img {
            width: 200px;
            height: 250px;
            border-radius: 20px;
            background: #DCE6F5;
            background-image: url("https://images.pexels.com/photos/439391/pexels-photo-439391.jpeg?auto=compress&cs=tinysrgb&w=600");
            background-size: cover;
            background-position: center;
        }

        .cluster-row {
            display: flex;
            gap: 14px;
            margin-bottom: 22px;
            overflow-x: auto;
            overflow-y: hidden;
            flex-wrap: nowrap;
            width: 100%;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            padding-bottom: 10px;
            scroll-snap-type: x mandatory;
        }

        .cluster-card {
            width: 200px;
            height: 240px;
            border-radius: 18px;
            background: #FFFFFF;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.03);
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            scroll-snap-align: start;
            flex: 0 0 200px;
        }

        .cluster-thumb {
            height: 140px;
            border-radius: 14px;
            background: #DEE7F6;
            margin-bottom: 6px;
        }

        .cluster-card h4 {
            font-size: 13px;
        }

        .cluster-card span {
            font-size: 11px;
            color: var(--text-soft);
        }

        .trending-list {
            background: #FFFFFF;
            border-radius: 20px;
            padding: 16px 14px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.03);
        }

        .trending-item {
            display: grid;
            grid-template-columns: 32px 1fr 60px;
            align-items: center;
            gap: 10px;
            padding: 8px 4px;
            border-radius: 12px;
        }

        .trending-item+.trending-item {
            margin-top: 4px;
        }

        .trending-item:hover {
            background: #EEF3FB;
        }

        .trending-thumb {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            background: #DEE7F6;
        }

        .trending-info h4 {
            font-size: 13px;
        }

        .trending-info span {
            font-size: 11px;
            color: var(--text-soft);
        }

        .trending-time {
            font-size: 12px;
            text-align: right;
            color: var(--text-soft);
        }

        .right {
            flex: 0.7;
            background: #F3F6FB;
            padding: 26px 24px 26px 20px;
            border-left: 1px solid var(--border-soft);
            display: flex;
            flex-direction: column;
            gap: 24px;
            grid-area: right;
            min-width: 0;
        }

        .top-list {
            background: #FFFFFF;
            border-radius: 20px;
            padding: 16px 14px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.03);
            margin-bottom: 20px;
        }

        .top-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 6px;
            border-radius: 12px;
        }

        .top-row+.top-row {
            margin-top: 4px;
        }

        .top-avatar {
            width: 34px;
            height: 34px;
            border-radius: 12px;
            background: #DEE7F6;
        }

        .top-info h4 {
            font-size: 13px;
        }

        .top-info span {
            font-size: 11px;
            color: var(--text-soft);
        }

        .detail-card {
            background: var(--primary);
            border-radius: 26px;
            padding: 18px 16px;
            color: #fff;
            box-shadow: 0 18px 36px rgba(0, 0, 0, 0.25);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .detail-thumb {
            height: 140px;
            border-radius: 18px;
            background: #5F769A;
            background-image: url("pexels-fotoaibe-1571460 (1).jpg");
            background-size: cover;
            background-position: center;
        }

        .detail-info h3 {
            font-size: 18px;
        }

        .detail-info span {
            font-size: 12px;
            opacity: 0.9;
        }

        .wave {
            margin: 6px 0 4px;
            height: 32px;
            border-radius: 12px;
            background: #5F769A;
        }

        .detail-actions {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-top: 4px;
        }

        .detail-actions button {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            background: #FFFFFF;
            color: var(--primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .detail-actions button.main {
            background: #F5F7FB;
        }

        .sidebar-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 22px;
        }

        .sidebar-top-title {
            font-size: 18px;
            color: var(--text-main);
            margin: 0;
            line-height: 1;
        }

        .hamburger {
            background: var(--primary-soft);
            border: none;
            border-radius: 12px;
            padding: 6px 8px;
            cursor: pointer;
            color: var(--primary);
            font-size: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .35);
            opacity: 0;
            pointer-events: none;
            transition: .2s;
            z-index: 90;
        }

        .overlay.show {
            opacity: 1;
            pointer-events: auto;
        }

        .mobile-topbar {
            display: none;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            background: rgba(243, 246, 251, .9);
            border-radius: 16px;
            margin-bottom: -20px;
            backdrop-filter: blur(6px);
            top: 10px;
            z-index: 50;
        }

        .sidebar.collapsed {
            width: 60px;
            padding: 18px 8px;
        }

        .sidebar.collapsed .sidebar-top {
            justify-content: center;
        }

        .sidebar.collapsed .profile,
        .sidebar.collapsed .sidebar-title,
        .sidebar.collapsed .menu {
            display: none;
        }

        .sidebar.collapsed .sidebar-top-title {
            display: none;
        }

        @media (max-width:1024px) {
            body {
                padding: 20px;
            }

            .app {
                border-radius: 24px;
                grid-template-columns: auto 1fr;
                grid-template-rows: auto auto;
                grid-template-areas: "sidebar main" "sidebar right";
            }

            .right {
                border-left: none;
                border-top: 1px solid var(--border-soft);
                padding: 20px;
            }

            .main {
                padding: 20px;
            }

            .hero {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }

            .hero-text {
                max-width: 100%;
            }

            .hero-img {
                width: 100%;
                height: 200px;
            }

            .mobile-topbar {
                display: none;
            }
        }

        @media (max-width:600px) {
            body {
                padding: 12px;
            }

            .app {
                border-radius: 20px;
                grid-template-columns: 1fr;
                grid-template-rows: auto auto;
                grid-template-areas: "main" "right";
            }

            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                bottom: 0;
                width: 260px;
                transform: translateX(-105%);
                transition: .25s;
                box-shadow: 8px 0 30px rgba(0, 0, 0, .15);
                z-index: 100;
                background: #fff;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .mobile-topbar {
                display: flex;
            }

            .sidebar .hamburger {
                display: none;
            }
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
                    <li><a class="active" href="dashboard.php"><i class="ri-dashboard-line"></i> Dashboard</a></li>
                    <li><a href="homeproper.php"><i class="ri-home-6-line"></i> Rumah</a></li>
                    <li><a href="apartproper.php"><i class="ri-building-4-line"></i> Apartemen</a></li>
                    <li><a href="dataproper.php"><i class="ri-database-2-line"></i> Data Properti</a></li>
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

        <!-- MAIN CENTER -->
        <main class="main">
            <div class="mobile-topbar">
                <button id="mobileHamburger" class="hamburger" type="button" aria-label="Menu">
                    <i class="ri-menu-line"></i>
                </button>
            </div>
            <h2>Admin Panel - PropertyKu</h2>
            <!-- HERO -->
            <section class="hero">
                <div class="hero-text">
                    <h2>Kelola rumah dan apartemen dalam satu panel.</h2>
                    <p>
                        Pantau unit, transaksi, dan pengguna secara real-time.
                        Tambahkan listing baru, update harga, dan kelola ketersediaan dengan mudah.
                    </p>
                    <button onclick="window.location.href='dataproper.php'">Lihat Data Properti</button>
                </div>
                <div class="hero-img"></div>
            </section>

            <!-- RUMAH -->
            <h3>Properti - Rumah</h3>
            <div class="cluster-row">
                <?php while ($r = $rumah->fetch_assoc()) {
                    $editPage = ($r['type'] === "APARTEMENT") ? "apartproper.php" : "homeproper.php";
                ?>
                    <div class="cluster-card"
                        onclick="window.location.href='<?= $editPage ?>?mode=edit&id=<?= $r['id'] ?>'"
                        style="cursor:pointer;">
                        <div class="cluster-thumb"
                            style="background-image:url('uploads/<?= $r['cover'] ?>'); background-size:cover; background-position:center;">
                        </div>
                        <h4><?= $r['nama'] ?></h4>
                        <span><?= $r['jumlah_unit'] ?> Unit • <?= $r['lokasi'] ?></span>
                        <span><b>Rp <?= number_format($r['harga'], 0, ',', '.') ?></b></span>
                    </div>
                <?php } ?>
            </div>

            <!-- APARTEMEN -->
            <h3>Properti - Apartemen</h3>
            <div class="cluster-row">
                <?php while ($a = $apart->fetch_assoc()) {
                    $editPage = ($a['type'] === "APARTEMENT") ? "apartproper.php" : "homeproper.php";
                ?>
                    <div class="cluster-card"
                        onclick="window.location.href='<?= $editPage ?>?mode=edit&id=<?= $a['id'] ?>'"
                        style="cursor:pointer;">
                        <div class="cluster-thumb"
                            style="background-image:url('uploads/<?= $a['cover'] ?>'); background-size:cover; background-position:center;">
                        </div>
                        <h4><?= $a['nama'] ?></h4>
                        <span><?= $a['jumlah_unit'] ?> Unit • <?= $a['lokasi'] ?></span>
                        <span><b>Rp <?= number_format($a['harga'], 0, ',', '.') ?> /bulan</b></span>
                    </div>
                <?php } ?>
            </div>

            <!-- TRENDING UNIT-->
            <h3>Unit Paling Banyak Dilihat</h3>
            <div class="trending-list">
                <div class="trending-item">
                    <div>01</div>
                    <div class="trending-info">
                        <h4>Rumah Type 90/120 - Emerald</h4>
                        <span>Cluster Emerald Residence • Surabaya</span>
                    </div>
                    <div class="trending-time">2x<br>Dilihat</div>
                </div>
                <div class="trending-item">
                    <div>02</div>
                    <div class="trending-info">
                        <h4>Apartemen SkyTower Lantai 18</h4>
                        <span>2 BR • City View</span>
                    </div>
                    <div class="trending-time">3x<br>Dilihat</div>
                </div>
                <div class="trending-item">
                    <div>03</div>
                    <div class="trending-info">
                        <h4>Rumah Hook Type 70/100</h4>
                        <span>Harmony Hills • Sidoarjo</span>
                    </div>
                    <div class="trending-time">5x<br>Dilihat</div>
                </div>
            </div>
        </main>

        <!-- RIGHT PANEL -->
        <aside class="right">
            <!-- PROPERTI TERBARU -->
            <div>
                <h3>Properti Terbaru</h3><br>
                <div class="top-list">
                    <?php while ($p = $latest->fetch_assoc()) {
                        $editPage = ($p['type'] === "APARTEMENT")
                            ? "apartproper.php"
                            : "homeproper.php";
                    ?>
                        <div class="top-row" onclick="window.location.href='<?= $editPage ?>?mode=edit&id=<?= $p['id'] ?>'" style="cursor:pointer;">
                            <div class="top-avatar"
                                style="background-image:url('uploads/<?= $p['cover'] ?>');
                                background-size:cover;
                                background-position:center;">
                            </div>
                            <div class="top-info">
                                <h4><?= $p['nama'] ?></h4>
                                <span>
                                    <?= ($p['type'] === "APARTEMENT") ? "Apartemen" : "Rumah" ?>
                                    • <?= $p['lokasi'] ?>
                                </span>
                            </div>
                        </div>
                    <?php } ?>
                </div>
        </aside>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const sidebar = document.querySelector(".sidebar");
            const toggleBtn = document.getElementById("toggleSidebar"); // desktop
            const mobileBtn = document.getElementById("mobileHamburger"); // mobile topbar
            const overlay = document.getElementById("overlay");

            function isMobile() {
                return window.matchMedia("(max-width: 600px)").matches;
            }

            function openSidebar() {
                sidebar.classList.add("open");
                overlay.classList.add("show");
                document.body.style.overflow = "hidden";
            }

            function closeSidebar() {
                sidebar.classList.remove("open");
                overlay.classList.remove("show");
                document.body.style.overflow = "";
            }

            function toggleSidebar() {
                if (isMobile()) {
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