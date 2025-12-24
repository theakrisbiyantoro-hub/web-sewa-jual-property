<?php
include "database.php";
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: regislogin.php");
    exit;
}

// API MODE (JSON)
if (isset($_GET["api"])) {
    header("Content-Type: application/json; charset=utf-8");

    $uid = (int)$_SESSION["user_id"];
    $api = $_GET["api"];

    // LIST FAVORITES
    if ($api === "list") {
        $sql = "
          SELECT 
            p.id,
            p.type,
            p.nama,
            p.lokasi,
            p.cover,
            p.harga
          FROM favorites f
          JOIN property p ON p.id = f.property_id
          WHERE f.user_id = ?
          ORDER BY f.created_at DESC
        ";
        $stmt = $koneksi->prepare($sql);
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $res = $stmt->get_result();

        $data = [];
        while ($row = $res->fetch_assoc()) $data[] = $row;

        echo json_encode($data);
        exit;
    }
        // LIST FAVORITE IDS (buat homepage: nandain tombol hati)
    if ($api === "ids") {
        $stmt = $koneksi->prepare("SELECT property_id FROM favorites WHERE user_id=?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $res = $stmt->get_result();

        $ids = [];
        while ($row = $res->fetch_assoc()) $ids[] = (int)$row["property_id"];

        echo json_encode($ids);
        exit;
    }

    // REMOVE FAVORITE
    if ($api === "remove" && $_SERVER["REQUEST_METHOD"] === "POST") {
        $property_id = (int)($_POST["property_id"] ?? 0);
        if ($property_id <= 0) {
            http_response_code(400);
            echo json_encode(["ok" => false, "error" => "invalid_property_id"]);
            exit;
        }

        $stmt = $koneksi->prepare("DELETE FROM favorites WHERE user_id=? AND property_id=?");
        $stmt->bind_param("ii", $uid, $property_id);
        $ok = $stmt->execute();

        echo json_encode(["ok" => (bool)$ok]);
        exit;
    }

        // TOGGLE FAVORITE (add kalau belum ada, remove kalau sudah ada)
    if ($api === "toggle" && $_SERVER["REQUEST_METHOD"] === "POST") {
        $property_id = (int)($_POST["property_id"] ?? 0);
        if ($property_id <= 0) {
            http_response_code(400);
            echo json_encode(["ok" => false, "error" => "invalid_property_id"]);
            exit;
        }

        // cek sudah ada belum
        $stmt = $koneksi->prepare("SELECT 1 FROM favorites WHERE user_id=? AND property_id=? LIMIT 1");
        $stmt->bind_param("ii", $uid, $property_id);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;

        if ($exists) {
            $stmt = $koneksi->prepare("DELETE FROM favorites WHERE user_id=? AND property_id=?");
            $stmt->bind_param("ii", $uid, $property_id);
            $ok = $stmt->execute();
            echo json_encode(["ok" => (bool)$ok, "action" => "removed"]);
            exit;
        } else {
            $stmt = $koneksi->prepare("INSERT INTO favorites (user_id, property_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $uid, $property_id);
            $ok = $stmt->execute();
            echo json_encode(["ok" => (bool)$ok, "action" => "added"]);
            exit;
        }
    }

    http_response_code(404);
    echo json_encode(["ok" => false, "error" => "unknown_api"]);
    exit;
}

$userEmail = null;
$userName  = null;
$resNav = $koneksi->query("SELECT id, nama, type FROM property ORDER BY (type='RUMAH') DESC, id DESC");
$navProps = $resNav ? $resNav->fetch_all(MYSQLI_ASSOC) : [];
// urutkan: RUMAH dulu, lalu APARTEMENT (dan id terbaru dulu)
usort($navProps, function($a, $b){
    $ta = ($a['type'] === 'RUMAH') ? 0 : 1;
    $tb = ($b['type'] === 'RUMAH') ? 0 : 1;
    if ($ta !== $tb) return $ta <=> $tb;    
    return ((int)$b['id']) <=> ((int)$a['id']);
});

if (isset($_SESSION["user_id"])) {
  $uid = (int)$_SESSION["user_id"];
  $stmt = $koneksi->prepare("SELECT email, name FROM users WHERE id=?");
  $stmt->bind_param("i", $uid);
  $stmt->execute();
  $u = $stmt->get_result()->fetch_assoc();
  $userEmail = $u["email"] ?? null;
  $userName  = $u["name"] ?? null;
}

$profileError = "";
$profileSuccess = "";

// UPDATE PROFILE (username + password)
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "update_profile") {

    if (!isset($_SESSION["user_id"])) {
        header("Location: regislogin.php");
        exit;
    }

    $uid = (int)$_SESSION["user_id"];
    $new_name = trim($_POST["new_name"] ?? "");
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    // validasi
    if ($new_name !== "" && strlen($new_name) < 3) {
        header("Location: ".$_SERVER["PHP_SELF"]."?profile=fail");
        exit;
    }

    if ($new_password !== "") {
        if (strlen($new_password) < 6 || $new_password !== $confirm_password) {
            header("Location: ".$_SERVER["PHP_SELF"]."?profile=fail");
            exit;
        }
    }

    // kalau kosong semua
    if ($new_name === "" && $new_password === "") {
        header("Location: ".$_SERVER["PHP_SELF"]."?profile=fail");
        exit;
    }

    // CASE 1: update name + password
    if ($new_name !== "" && $new_password !== "") {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $koneksi->prepare("UPDATE users SET name=?, password=? WHERE id=?");
        $stmt->bind_param("ssi", $new_name, $hash, $uid);
    }
    // CASE 2: update name saja
    elseif ($new_name !== "") {
        $stmt = $koneksi->prepare("UPDATE users SET name=? WHERE id=?");
        $stmt->bind_param("si", $new_name, $uid);
    }
    // CASE 3: update password saja
    else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $koneksi->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hash, $uid);
    }

    if ($stmt && $stmt->execute()) {
        if ($new_name !== "") $_SESSION["name"] = $new_name;
        header("Location: ".$_SERVER["PHP_SELF"]."?profile=success");
        exit;
    }

    header("Location: ".$_SERVER["PHP_SELF"]."?profile=fail");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Favorites | PropertyKu</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.4.0/fonts/remixicon.css" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Poppins", sans-serif;
        }

        body {
            background: #f6f7fb;
        }

        /* ================= NAVBAR (SAMA contact.php) ================= */
        .navbar{
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        width: 92%;
        height: 64px;

        display: flex;
        align-items: center;
        justify-content: space-between;

        padding: 0 22px;
        border-radius: 60px;
        background: rgba(29,36,51,.55);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        z-index: 999;
        transition: all .35s ease;
        }
        .navbar.scrolled{
        top: 10px;
        background:#1d2433;
        box-shadow: 0 10px 30px rgba(0,0,0,.35);
        }

        /* LEFT GROUP */
        .nav-left-group{display:flex;align-items:center;gap:12px;}
        .nav-left{font-size:18px;font-weight:600;color:#fff;letter-spacing:.5px;}

        /* CENTER MENU (desktop) */
        .nav-center{display:flex;align-items:center;gap:34px;}
        .nav-center a{
        text-decoration:none;
        font-size:14px;
        color:#cfd3dc;
        transition:.3s;
        }
        .nav-center a:hover{color:#fff;}
        .nav-center a.active{color:#fff;font-weight:500;}

        /* RIGHT */
        .login-btn{
        padding:10px 26px;border-radius:40px;border:none;
        background:#fff;color:#1d2433;font-size:14px;font-weight:600;
        cursor:pointer;transition:.3s;
        }
        .login-btn:hover{background:#e5e8ef;}

        /* HAMBURGER */
        .nav-toggle{
        display:none;
        background:transparent;border:none;color:#fff;
        font-size:22px;cursor:pointer;
        }

        /* MOBILE DROPDOWN */
        .nav-mobile{
        display:none;
        position:absolute;
        top:76px;
        left:50%;
        transform:translateX(-50%);
        width:92%;
        padding:12px;
        border-radius:18px;
        background: rgba(29,36,51,.85);
        backdrop-filter: blur(14px);
        z-index:998;
        }
        .nav-mobile a{
        display:block;
        padding:12px 14px;
        border-radius:12px;
        text-decoration:none;
        color:#cfd3dc;
        }
        .nav-mobile a:hover{color:#fff;background:rgba(255,255,255,.08);}
        .nav-mobile a.active{color:#fff;font-weight:500;}
        .nav-mobile.show{display:block;}

        /* PROFILE */
        .profile-wrap{position:relative;}
        .profile-btn{
        width:44px;height:44px;border-radius:50%;
        border:none;cursor:pointer;
        background:rgba(255,255,255,.2);color:#fff;
        }
        .profile-btn span{font-weight:700;}
        .profile-menu{
        position:absolute;right:0;top:54px;width:230px;
        background:rgba(29,36,51,.95);
        backdrop-filter:blur(12px);
        border-radius:14px;padding:12px;
        display:none;z-index:9999;
        }
        .profile-menu.show{display:block;}
        .profile-email,.profile-name{text-align:center;}
        .profile-email{font-size:13px;color:#cfd3dc;margin-bottom:4px;}
        .profile-name{font-size:12px;color:#fff;font-weight:600;margin-bottom:10px;opacity:.95;}
        .profile-logout{
        display:block;margin-top:8px;text-align:center;
        color:#ffb4b4;text-decoration:none;
        }

        /* Edit Profile (sama contact.php) */
        .profile-edit-btn{
        width:100%;
        padding:10px;
        border-radius:10px;
        border:none;
        background:rgba(255,255,255,.15);
        color:#fff;
        font-weight:600;
        cursor:pointer;
        }
        .edit-profile-form{display:none;margin-top:10px;}
        .edit-profile-form.show{display:block;}
        .edit-profile-form input{
        width:100%;
        padding:10px;
        border-radius:10px;
        border:1px solid rgba(255,255,255,.15);
        background:rgba(255,255,255,.08);
        color:#fff;
        outline:none;
        margin-bottom:8px;
        font-size:13px;
        }
        .profile-save-btn{
        width:100%;
        padding:10px;
        border-radius:10px;
        border:none;
        background:#fff;
        color:#1d2433;
        font-weight:600;
        cursor:pointer;
        }
        .profile-cancel-btn{
        width:100%;
        padding:10px;
        border-radius:10px;
        border:none;
        background:rgba(255,255,255,.12);
        color:#fff;
        font-weight:600;
        cursor:pointer;
        margin-top:8px;
        }

        /* ===== Properti dropdown (DESKTOP) ===== */
        .prop-wrap{ position: relative; display:inline-flex; align-items:center; }
        .prop-btn{
        background: transparent;
        border: none;
        color:#cfd3dc;
        font-size:14px;
        cursor:pointer;
        padding:0;
        line-height:1;
        display:inline-flex;
        align-items:center;
        gap:6px;
        transition:.3s;
        }
        .prop-btn:hover{ color:#fff; }

        .prop-menu{
        position:absolute;
        left:0;
        top:54px;
        width:280px;
        max-height:340px;
        overflow:auto;
        background: rgba(29,36,51,.95);
        backdrop-filter: blur(12px);
        border-radius:14px;
        padding:10px;
        display:none;
        z-index:9999;
        }
        .prop-menu.show{ display:block; }

        .prop-item{
        display:flex;
        align-items:center;
        gap:10px;
        padding:10px 12px;
        border-radius:12px;
        text-decoration:none;
        color:#cfd3dc;
        font-size:13px;
        }
        .prop-item:hover{ background: rgba(255,255,255,.08); color:#fff; }

        /* ===== Properti dropdown (MOBILE) ===== */
        .m-prop-btn{
        width:100%;
        display:flex;
        align-items:center;
        justify-content:space-between;
        padding:12px 14px;
        border-radius:12px;
        background: transparent;
        border:none;
        color:#cfd3dc;
        font-size:16px;
        cursor:pointer;
        }
        .m-prop-btn:hover{ color:#fff; background: rgba(255,255,255,.08); }

        .m-prop-menu{
        display:none;
        margin-top:6px;
        padding:8px;
        border-radius:14px;
        background: rgba(255,255,255,.06);
        max-height:260px;
        overflow:auto;
        }
        .m-prop-menu.show{ display:block; }

        .m-prop-item{
        display:flex;
        align-items:center;
        gap:10px;
        padding:10px 12px;
        border-radius:12px;
        text-decoration:none;
        color:#cfd3dc;
        font-size:13px;
        }
        .m-prop-item:hover{ background: rgba(255,255,255,.08); color:#fff; }

        /* MOBILE RULES */
        @media (max-width:600px){
        .nav-toggle{display:block;}
        .nav-center{display:none;}
        }

        /* ================= HERO ================= */
        .favorite-hero {
            height: 55vh;
            background: linear-gradient(rgba(29, 36, 51, 0.6),
                    rgba(29, 36, 51, 0.6)),
                url("img/pexels-heyho-6312358.jpg") center/cover;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
        }

        .favorite-hero h1 {
            font-size: 46px;
            font-weight: 600;
        }

        .favorite-hero span {
            font-size: 14px;
            opacity: .85;
        }

        /* ================= FAVORITE LIST ================= */
        .favorite-section {
            padding: 120px 6%;
        }

        .favorite-wrapper {
            max-width: 1200px;
            margin: auto;
        }

        .favorite-title {
            font-size: 34px;
            font-weight: 600;
            color: #1d2433;
            margin-bottom: 40px;
        }

        .favorite-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
        }

        /* CARD */
        .favorite-card {
            background: #ffffff;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, .12);
            transition: .3s;
        }

        .favorite-card:hover {
            transform: translateY(-6px);
        }

        .favorite-card img {
            width: 100%;
            height: 220px;
            object-fit: cover;
        }

        .favorite-content {
            padding: 22px;
        }

        .favorite-content h3 {
            font-size: 18px;
            color: #1d2433;
            margin-bottom: 6px;
        }

        .favorite-location {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 14px;
        }

        .favorite-price {
            font-size: 16px;
            font-weight: 600;
            color: #1d2433;
            margin-bottom: 18px;
        }

        .favorite-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .detail-btn {
            padding: 10px 22px;
            border-radius: 30px;
            border: none;
            background: #1d2433;
            color: white;
            font-size: 13px;
            cursor: pointer;
        }

        .remove-btn {
            background: transparent;
            border: none;
            font-size: 20px;
            color: #ef4444;
            cursor: pointer;
        }

        /* EMPTY STATE */
        .empty-favorite {
            text-align: center;
            padding: 80px 0;
            color: #6b7280;
        }

        .empty-favorite i {
            font-size: 60px;
            margin-bottom: 16px;
            color: #9ca3af;
        }

        /* ============================
   FOOTER
=============================== */

        .main-footer {
            background: #19293e;
            color: #ddd;
            padding: 70px 40px 40px;
            margin-top: 80px;
            font-family: "Poppins", sans-serif;
        }

        .footer-container {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1fr;
            gap: 40px;
            max-width: 1200px;
            margin: auto;
        }

        /* BRAND */
        .footer-logo {
            font-size: 28px;
            font-weight: 700;
            color: white;
        }

        .footer-desc {
            font-size: 14px;
            opacity: 0.7;
            margin-top: 10px;
        }

        /* COLUMN TITLES */
        .footer-col h3 {
            color: white;
            font-size: 16px;
            margin-bottom: 14px;
        }

        /* LINKS */
        .footer-col ul {
            list-style: none;
        }

        .footer-col ul li {
            margin-bottom: 8px;
        }

        .footer-col ul li a {
            color: #bdbdbd;
            font-size: 14px;
            text-decoration: none;
            transition: 0.25s;
        }

        .footer-col ul li a:hover {
            color: white;
        }

        /* CONTACT & SOCIAL */
        .footer-social {
            margin-top: 12px;
            display: flex;
            gap: 12px;
        }

        .footer-social a {
            font-size: 22px;
            color: #ddd;
            transition: .25s;
        }

        .footer-social a:hover {
            color: white;
        }

        /* BOTTOM BAR */
        .footer-bottom {
            text-align: center;
            margin-top: 40px;
            padding-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 13px;
            color: #aaa;
        }


        /* RESPONSIVE */
        @media (max-width: 900px) {
            .footer-container {
                grid-template-columns: 1fr 1fr;
                text-align: left;
            }
        }

        @media (max-width: 600px) {
            .footer-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <!-- ================= NAVBAR ================= -->
    <nav class="navbar" id="navbar">
        <!-- LEFT: hamburger + logo -->
        <div class="nav-left-group">
            <button class="nav-toggle" id="navToggle" aria-label="Open menu" type="button">
            <i class="ri-menu-line"></i>
            </button>
            <div class="nav-left">PropertyKu</div>
        </div>

        <!-- CENTER (desktop) -->
        <div class="nav-center" id="navCenter">
            <a href="HOMEPAGEFIX.php" class="<?= basename($_SERVER['PHP_SELF'])=='HOMEPAGEFIX.php'?'active':'' ?>">Home</a>

            <div class="prop-wrap">
            <button class="prop-btn" id="propBtn" type="button">
                Properti <i class="ri-arrow-down-s-line"></i>
            </button>
            <div class="prop-menu" id="propMenu">
                <?php foreach($navProps as $p):
                $detailUrl = ($p['type'] === 'RUMAH')
                    ? "detailhome.php?id=".(int)$p['id']
                    : "detailapart.php?id=".(int)$p['id'];
                ?>
                <a class="prop-item" href="<?= $detailUrl ?>">
                    <i class="<?= ($p['type'] === 'RUMAH') ? 'ri-home-5-line' : 'ri-building-2-line' ?>"></i>
                    <?= htmlspecialchars($p['nama']) ?>
                </a>
                <?php endforeach; ?>
            </div>
            </div>

            <?php $redir = urlencode($_SERVER["REQUEST_URI"]); ?>
            <a href="<?= isset($_SESSION["user_id"]) ? 'favorite.php' : 'regislogin.php?redirect='.$redir ?>">Favorite</a>
            <a href="<?= isset($_SESSION["user_id"]) ? 'riwayat.php' : 'regislogin.php?redirect='.$redir ?>">Riwayat</a>
            <a href="contact.php" class="<?= basename($_SERVER['PHP_SELF'])=='contact.php'?'active':'' ?>">Contact Us</a>
        </div>

        <!-- RIGHT (login / profile) -->
        <div class="nav-right">
            <?php if (isset($_SESSION["user_id"])): ?>
            <div class="profile-wrap">
                <button class="profile-btn" id="profileBtn" type="button">
                <span><?= strtoupper(substr($userEmail ?? "U", 0, 1)) ?></span>
                </button>

                <div class="profile-menu" id="profileMenu">
                <div class="profile-email"><?= htmlspecialchars($userEmail ?? "") ?></div>
                <div class="profile-name"><?= htmlspecialchars($userName ?? "") ?></div>
                <hr style="border:0;border-top:1px solid rgba(255,255,255,.15);margin:10px 0;">

                <button type="button" class="profile-edit-btn" id="btnEditProfile">Edit Profile</button>

                <form method="POST" action="" id="editProfileForm" class="edit-profile-form">
                    <input type="hidden" name="action" value="update_profile">

                    <input type="text" name="new_name" placeholder="Ganti username"
                    value="<?= htmlspecialchars($userName ?? "") ?>">

                    <input type="password" name="new_password" placeholder="Password baru *min 6 digit">
                    <input type="password" name="confirm_password" placeholder="Konfirmasi password baru">

                    <button type="submit" class="profile-save-btn">Simpan</button>
                    <button type="button" class="profile-cancel-btn" id="btnCancelEdit">Batal</button>
                </form>

                <a href="logout.php" class="profile-logout">Logout</a>
                </div>
            </div>
            <?php else: ?>
            <button class="login-btn" id="btnLogin" type="button">Login</button>
            <?php endif; ?>
        </div>

        <!-- MOBILE DROPDOWN -->
        <div class="nav-mobile" id="navMobile">
            <a href="HOMEPAGEFIX.php" class="<?= basename($_SERVER['PHP_SELF'])=='HOMEPAGEFIX.php'?'active':'' ?>">Home</a>

            <button class="m-prop-btn" id="mPropBtn" type="button">
            Properti <i class="ri-arrow-down-s-line"></i>
            </button>

            <div class="m-prop-menu" id="mPropMenu">    
            <?php foreach($navProps as $p):
                $detailUrl = ($p['type'] === 'RUMAH')
                ? "detailhome.php?id=".(int)$p['id']
                : "detailapart.php?id=".(int)$p['id'];
            ?>
                <a class="m-prop-item" href="<?= $detailUrl ?>">
                <i class="<?= ($p['type'] === 'RUMAH') ? 'ri-home-5-line' : 'ri-building-2-line' ?>"></i>
                <?= htmlspecialchars($p['nama']) ?>
                </a>
            <?php endforeach; ?>
            </div>

            <?php $redir = urlencode($_SERVER["REQUEST_URI"]); ?>
            <a href="<?= isset($_SESSION["user_id"]) ? 'favorite.php' : 'regislogin.php?redirect='.$redir ?>">Favorite</a>
            <a href="<?= isset($_SESSION["user_id"]) ? 'riwayat.php' : 'regislogin.php?redirect='.$redir ?>">Riwayat</a>
            <a href="contact.php" class="<?= basename($_SERVER['PHP_SELF'])=='contact.php'?'active':'' ?>">Contact Us</a>
        </div>
    </nav>


    <!-- HERO -->
    <section class="favorite-hero">
        <div>
            <h1>My Favorites</h1>
            <span>Your saved dream properties</span>
        </div>
    </section>

    <!-- FAVORITE LIST -->
    <section class="favorite-section">
        <div class="favorite-wrapper">
            <h2 class="favorite-title">Saved Properties</h2>

            <!-- GRID -->
            <div class="favorite-grid" id="favoriteGrid">
            </div>
        </div>
    </section>

    <!-- ================= FOOTER ================= -->
    <footer class="main-footer">

        <div class="footer-container">

            <!-- BRAND -->
            <div class="footer-col">
                <h2 class="footer-logo">PropertyKu</h2>
                <p class="footer-desc">
                    Platform pencarian properti modern dengan fitur lengkap
                    untuk membantu Anda menemukan hunian terbaik.
                </p>
            </div>

            <!-- MENU -->
            <div class="footer-col">
                <h3>Menu</h3>
                <ul>
                    <li><a href="HOMEPAGEFIX.php">Homepage</a></li>
                    <li><a href="#">Properti</a></li>
                    <li><a href="favorite.php">Favorite</a></li>
                    <li><a href="riwayat.php">Riwayat</a></li>
                    <li><a href="contact.php">Contact Us</a></li>
                </ul>
            </div>

            <!-- SUPPORT -->
            <div class="footer-col">
                <h3>Layanan</h3>
                <ul>
                    <li><a href="#">Promo</a></li>
                    <li><a href="#">Survei Lokasi</a></li>
                    <li><a href="#">KPR Partner</a></li>
                    <li><a href="#">Bantuan</a></li>
                </ul>
            </div>

            <!-- CONTACT -->
            <div class="footer-col">
                <h3>Hubungi Kami</h3>
                <p>Email: support@propertyku.com</p>
                <p>WhatsApp: +62 812-3456-7890</p>

                <div class="footer-social">
                    <a href="https://www.instagram.com/"><i class="ri-instagram-line"></i></a>
                    <a href="https://web.facebook.com/?locale=id_ID&_rdc=1&_rdr#"><i class="ri-facebook-circle-line"></i></a>
                    <a href="https://x.com/?lang=id"><i class="ri-twitter-x-line"></i></a>
                </div>
            </div>

        </div>

        <div class="footer-bottom">
            © 2025 PropertyKu — All Rights Reserved.
        </div>

    </footer>

    <script>
  // ================== NAVBAR SCROLL ==================
  const navbar = document.getElementById("navbar");
  window.addEventListener("scroll", () => {
    navbar.classList.toggle("scrolled", window.scrollY > 80);
  });

  // ================== FAVORITES (DB via API in SAME FILE) ==================
  function rupiah(num){
    const n = Number(num || 0);
    return "Rp" + n.toLocaleString("id-ID");
  }

  function goDetail(id, kind) {
    const page = (kind === "RUMAH") ? "detailhome.php" : "detailapart.php";
    window.location.href = `${page}?id=${encodeURIComponent(id)}`;
  }

  function renderFavorites(items){
    const grid = document.getElementById("favoriteGrid");
    grid.innerHTML = "";

    if (!items || items.length === 0) {
      grid.innerHTML = `
        <div class="empty-favorite">
          <i class="ri-heart-line"></i>
          <p>No favorite properties yet</p>
        </div>
      `;
      return;
    }

    items.forEach((item) => {
      const img = item.cover ? `uploads/${item.cover}` : "";
      const kind = item.type; // "RUMAH" / "APARTEMENT"

      grid.innerHTML += `
        <div class="favorite-card">
          <img src="${img}" alt="">
          <div class="favorite-content">
            <h3>${item.nama ?? ""}</h3>
            <div class="favorite-location">${item.lokasi ?? ""}</div>
            <div class="favorite-price">${rupiah(item.harga)}</div>

            <div class="favorite-actions">
              <button class="detail-btn" onclick="goDetail('${item.id}','${kind}')">View Detail</button>
              <button class="remove-btn" onclick="removeFavorite('${item.id}')">
                <i class="ri-heart-fill"></i>
              </button>
            </div>
          </div>
        </div>
      `;
    });
  }

  async function loadFavorites(){
    const res = await fetch("favorite.php?api=list");
    const data = await res.json();
    renderFavorites(data);
  }

  async function removeFavorite(propertyId){
    await fetch("favorite.php?api=remove", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "property_id=" + encodeURIComponent(propertyId)
    });
    await loadFavorites();
  }

  document.addEventListener("DOMContentLoaded", loadFavorites);

  // ================== NAV / PROFILE / DROPDOWNS ==================
  const navToggle = document.getElementById("navToggle");
  const navMobile = document.getElementById("navMobile");

  const profileBtn  = document.getElementById("profileBtn");
  const profileMenu = document.getElementById("profileMenu");

  const btnEdit  = document.getElementById("btnEditProfile");
  const formEdit = document.getElementById("editProfileForm");
  const btnCancel = document.getElementById("btnCancelEdit");

  const btnLogin = document.getElementById("btnLogin");

  const propBtn  = document.getElementById("propBtn");
  const propMenu = document.getElementById("propMenu");

  const mPropBtn  = document.getElementById("mPropBtn");
  const mPropMenu = document.getElementById("mPropMenu");

  // ====== toggle mobile menu ======
  if (navToggle && navMobile) {
    navToggle.addEventListener("click", (e) => {
      e.stopPropagation();
      navMobile.classList.toggle("show");
    });
    navMobile.addEventListener("click", (e) => e.stopPropagation());
  }

  // ====== login button ======
  if (btnLogin) {
    btnLogin.addEventListener("click", () => window.location.href = "regislogin.php");
  }

  // ====== profile menu ======
  if (profileBtn && profileMenu) {
    profileBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      profileMenu.classList.toggle("show");
    });
    profileMenu.addEventListener("click", (e) => e.stopPropagation());
  }

  // ====== edit profile toggle ======
  if (btnEdit && formEdit) {
    btnEdit.addEventListener("click", (e) => {
      e.stopPropagation();
      formEdit.classList.toggle("show");
    });
  }

  if (btnCancel && formEdit) {
    btnCancel.addEventListener("click", (e) => {
      e.stopPropagation();
      formEdit.classList.remove("show");
      const pw  = formEdit.querySelector('input[name="new_password"]');
      const cpw = formEdit.querySelector('input[name="confirm_password"]');
      if (pw) pw.value = "";
      if (cpw) cpw.value = "";
    });
  }

  // ====== Properti dropdown desktop ======
  if (propBtn && propMenu) {
    propBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      propMenu.classList.toggle("show");
    });
    propMenu.addEventListener("click", (e) => e.stopPropagation());
  }

  // ====== Properti dropdown mobile ======
  if (mPropBtn && mPropMenu) {
    mPropBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      mPropMenu.classList.toggle("show");
    });
    mPropMenu.addEventListener("click", (e) => e.stopPropagation());
  }

  // klik item properti di mobile -> nutup menu
  mPropMenu?.querySelectorAll("a").forEach((a) => {
    a.addEventListener("click", () => {
      navMobile?.classList.remove("show");
      mPropMenu?.classList.remove("show");
    });
  });

  // ====== SATU klik global untuk nutup semua ======
  document.addEventListener("click", () => {
    navMobile?.classList.remove("show");
    profileMenu?.classList.remove("show");
    propMenu?.classList.remove("show");
    mPropMenu?.classList.remove("show");
    formEdit?.classList.remove("show");
  });
</script>

</body>
<?php if (isset($_GET['profile']) && $_GET['profile'] === 'success'): ?>
  <script>alert("Profil berhasil diperbarui!");</script>
<?php elseif (isset($_GET['profile']) && $_GET['profile'] === 'fail'): ?>
  <script>alert("Gagal update profil. Cek username/password kamu.");</script>
<?php endif; ?> 
</html>