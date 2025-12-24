<?php
include "database.php";
session_start();

if (!isset($_SESSION["user_id"])) {
  header("Location: regislogin.php");
  exit;
}

$uid = (int) $_SESSION['user_id'];

/* ================= CEK STATUS BOOKING ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cek_status') {

  $booking_id = (int)($_POST['booking_id'] ?? 0);

  // validasi booking milik user
  $cek = $koneksi->query("
        SELECT id, status 
        FROM booking 
        WHERE id = $booking_id 
          AND user_id = $uid
          AND status = 'verified'
    ");

  if ($cek && $cek->num_rows > 0) {
    // user baru dianggap lihat hasil verifikasi
    $koneksi->query("
            UPDATE booking 
            SET user_checked = 1
            WHERE id = $booking_id
        ");
  }

  header("Location: riwayat.php");
  exit;
}


/* ================= UPLOAD BUKTI TRANSFER ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_payment') {

  $booking_id = (int) ($_POST['booking_id'] ?? 0);
  $amount     = (int) ($_POST['amount'] ?? 0);

  if ($booking_id <= 0 || $amount <= 0 || empty($_FILES['proof']['name'])) {
    die("Data pembayaran tidak lengkap.");
  }

  // cek booking milik user
  $cek = $koneksi->query("
    SELECT id, status 
    FROM booking 
    WHERE id = $booking_id AND user_id = $uid
  ");

  if (!$cek || $cek->num_rows === 0) {
    die("Booking tidak valid.");
  }

  $b = $cek->fetch_assoc();
  if ($b['status'] !== 'wait_payment') {
    die("Status booking tidak bisa dibayar.");
  }

  // validasi file
  $ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
  $allow = ['jpg', 'jpeg', 'png', 'webp'];

  if (!in_array($ext, $allow)) {
    die("Format gambar tidak didukung.");
  }

  if ($_FILES['proof']['size'] > 3 * 1024 * 1024) {
    die("Ukuran file maksimal 3MB.");
  }

  // simpan file
  $dir = "uploads/payments/";
  if (!is_dir($dir)) mkdir($dir, 0777, true);

  $fileName = "proof_{$booking_id}_" . time() . "." . $ext;
  $path = $dir . $fileName;

  move_uploaded_file($_FILES['proof']['tmp_name'], $path);

  // insert payment
  $stmt = $koneksi->prepare("
    INSERT INTO booking_payments
    (booking_id, user_id, amount, proof_file, status, submitted_at)
    VALUES (?, ?, ?, ?, 'wait_verify', NOW())
  ");
  $stmt->bind_param("iiis", $booking_id, $uid, $amount, $fileName);
  $stmt->execute();

  // update booking
  $koneksi->query("
    UPDATE booking 
    SET status = 'wait_verify'
    WHERE id = $booking_id
  ");

  header("Location: riwayat.php");
  exit;
}

$userEmail = null;
$userName  = null;

/* ====== NAV PROPERTI LIST ====== */
$resNav = $koneksi->query("SELECT id, nama, type FROM property ORDER BY (type='RUMAH') DESC, id DESC");
$navProps = $resNav ? $resNav->fetch_all(MYSQLI_ASSOC) : [];

usort($navProps, function ($a, $b) {
  $ta = ($a['type'] === 'RUMAH') ? 0 : 1;
  $tb = ($b['type'] === 'RUMAH') ? 0 : 1;
  if ($ta !== $tb) return $ta <=> $tb;
  return ((int)$b['id']) <=> ((int)$a['id']);
});

/* ====== RIWAYAT BOOKING USER ====== */
$bookingRes = $koneksi->query("
  SELECT 
    b.*,
    p.nama AS property_name
  FROM booking b
  JOIN property p ON b.property_id = p.id
  WHERE b.user_id = $uid
  ORDER BY b.created_at DESC
");

/* ====== USER INFO ====== */
if (isset($_SESSION["user_id"])) {
  $uid = (int)$_SESSION["user_id"];
  $stmt = $koneksi->prepare("SELECT email, name FROM users WHERE id=?");
  $stmt->bind_param("i", $uid);
  $stmt->execute();
  $u = $stmt->get_result()->fetch_assoc();

  $userEmail = $u["email"] ?? null;
  $userName  = $u["name"] ?? null;
}

/* ===== UPDATE PROFILE ===== */
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
    header("Location: " . $_SERVER["PHP_SELF"] . "?profile=fail");
    exit;
  }

  if ($new_password !== "") {
    if (strlen($new_password) < 6 || $new_password !== $confirm_password) {
      header("Location: " . $_SERVER["PHP_SELF"] . "?profile=fail");
      exit;
    }
  }

  // kalau kosong semua
  if ($new_name === "" && $new_password === "") {
    header("Location: " . $_SERVER["PHP_SELF"] . "?profile=fail");
    exit;
  }

  // CASE 1: update name + password
  if ($new_name !== "" && $new_password !== "") {
    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $koneksi->prepare("UPDATE users SET name=?, password=? WHERE id=?");
    $stmt->bind_param("ssi", $new_name, $hash, $uid);
  }
  // CASE 2: update name aja
  elseif ($new_name !== "") {
    $stmt = $koneksi->prepare("UPDATE users SET name=? WHERE id=?");
    $stmt->bind_param("si", $new_name, $uid);
  }
  // CASE 3: update password aja
  else {
    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $koneksi->prepare("UPDATE users SET password=? WHERE id=?");
    $stmt->bind_param("si", $hash, $uid);
  }

  if ($stmt && $stmt->execute()) {
    if ($new_name !== "") {
      $_SESSION["name"] = $new_name;
      $userName = $new_name; // biar langsung update di UI
    }
    header("Location: " . $_SERVER["PHP_SELF"] . "?profile=success");
    exit;
  }

  header("Location: " . $_SERVER["PHP_SELF"] . "?profile=fail");
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Booking History | PropertyKu</title>

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

    /* ================= NAVBAR ================= */
    .navbar {
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
      background: rgba(29, 36, 51, .55);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      z-index: 999;
      transition: all .35s ease;
    }

    .navbar.scrolled {
      top: 10px;
      background: #1d2433;
      box-shadow: 0 10px 30px rgba(0, 0, 0, .35);
    }

    .nav-left-group {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .nav-left {
      font-size: 18px;
      font-weight: 600;
      color: #fff;
      letter-spacing: .5px;
    }

    .nav-center {
      display: flex;
      align-items: center;
      gap: 34px;
    }

    .nav-center a {
      text-decoration: none;
      font-size: 14px;
      color: #cfd3dc;
      transition: .3s;
    }

    .nav-center a:hover {
      color: #fff;
    }

    .nav-center a.active {
      color: #fff;
      font-weight: 500;
    }

    .login-btn {
      padding: 10px 26px;
      border-radius: 40px;
      border: none;
      background: #fff;
      color: #1d2433;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: .3s;
    }

    .login-btn:hover {
      background: #e5e8ef;
    }

    .nav-toggle {
      display: none;
      background: transparent;
      border: none;
      color: #fff;
      font-size: 22px;
      cursor: pointer;
    }

    .nav-mobile {
      display: none;
      position: absolute;
      top: 76px;
      left: 50%;
      transform: translateX(-50%);
      width: 92%;
      padding: 12px;
      border-radius: 18px;
      background: rgba(29, 36, 51, .85);
      backdrop-filter: blur(14px);
      z-index: 998;
    }

    .nav-mobile a {
      display: block;
      padding: 12px 14px;
      border-radius: 12px;
      text-decoration: none;
      color: #cfd3dc;
    }

    .nav-mobile a:hover {
      color: #fff;
      background: rgba(255, 255, 255, .08);
    }

    .nav-mobile a.active {
      color: #fff;
      font-weight: 500;
    }

    .nav-mobile.show {
      display: block;
    }

    /* PROFILE */
    .profile-wrap {
      position: relative;
    }

    .profile-btn {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      border: none;
      cursor: pointer;
      background: rgba(255, 255, 255, .2);
      color: #fff;
    }

    .profile-btn span {
      font-weight: 700;
    }

    .profile-menu {
      position: absolute;
      right: 0;
      top: 54px;
      width: 230px;
      background: rgba(29, 36, 51, .95);
      backdrop-filter: blur(12px);
      border-radius: 14px;
      padding: 12px;
      display: none;
      z-index: 9999;
    }

    .profile-menu.show {
      display: block;
    }

    .profile-email,
    .profile-name {
      text-align: center;
    }

    .profile-email {
      font-size: 13px;
      color: #cfd3dc;
      margin-bottom: 4px;
    }

    .profile-name {
      font-size: 12px;
      color: #fff;
      font-weight: 600;
      margin-bottom: 10px;
      opacity: .95;
    }

    .profile-logout {
      display: block;
      margin-top: 8px;
      text-align: center;
      color: #ffb4b4;
      text-decoration: none;
    }

    .profile-menu hr {
      border: 0;
      border-top: 1px solid rgba(255, 255, 255, .15);
      margin: 10px 0;
    }

    /* tombol edit + form edit */
    .profile-edit-btn {
      width: 100%;
      padding: 10px;
      border-radius: 10px;
      border: none;
      background: rgba(255, 255, 255, .15);
      color: #fff;
      font-weight: 600;
      cursor: pointer;
    }

    .edit-profile-form {
      display: none;
      margin-top: 10px;
    }

    .edit-profile-form.show {
      display: block;
    }

    .edit-profile-form input {
      width: 100%;
      padding: 10px;
      border-radius: 10px;
      border: 1px solid rgba(255, 255, 255, .15);
      background: rgba(255, 255, 255, .08);
      color: #fff;
      outline: none;
      margin-bottom: 8px;
      font-size: 13px;
    }

    .profile-save-btn {
      width: 100%;
      padding: 10px;
      border-radius: 10px;
      border: none;
      background: #fff;
      color: #1d2433;
      font-weight: 600;
      cursor: pointer;
    }

    .profile-cancel-btn {
      width: 100%;
      padding: 10px;
      border-radius: 10px;
      border: none;
      background: rgba(255, 255, 255, .12);
      color: #fff;
      font-weight: 600;
      cursor: pointer;
      margin-top: 8px;
    }

    /* ===== Properti dropdown (DESKTOP) ===== */
    .prop-wrap {
      position: relative;
      display: inline-flex;
      align-items: center;
    }

    .prop-btn {
      background: transparent;
      border: none;
      color: #cfd3dc;
      font-size: 14px;
      cursor: pointer;
      padding: 0;
      line-height: 1;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: .3s;
    }

    .prop-btn:hover {
      color: #fff;
    }

    .prop-menu {
      position: absolute;
      left: 0;
      top: 54px;
      width: 280px;
      max-height: 340px;
      overflow: auto;
      background: rgba(29, 36, 51, .95);
      backdrop-filter: blur(12px);
      border-radius: 14px;
      padding: 10px;
      display: none;
      z-index: 9999;
    }

    .prop-menu.show {
      display: block;
    }

    .prop-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 12px;
      border-radius: 12px;
      text-decoration: none;
      color: #cfd3dc;
      font-size: 13px;
    }

    .prop-item:hover {
      background: rgba(255, 255, 255, .08);
      color: #fff;
    }

    /* ===== Properti dropdown (MOBILE) ===== */
    .m-prop-btn {
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px 14px;
      border-radius: 12px;
      background: transparent;
      border: none;
      color: #cfd3dc;
      font-size: 16px;
      cursor: pointer;
    }

    .m-prop-btn:hover {
      color: #fff;
      background: rgba(255, 255, 255, .08);
    }

    .m-prop-menu {
      display: none;
      margin-top: 6px;
      padding: 8px;
      border-radius: 14px;
      background: rgba(255, 255, 255, .06);
      max-height: 260px;
      overflow: auto;
    }

    .m-prop-menu.show {
      display: block;
    }

    .m-prop-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 12px;
      border-radius: 12px;
      text-decoration: none;
      color: #cfd3dc;
      font-size: 13px;
    }

    .m-prop-item:hover {
      background: rgba(255, 255, 255, .08);
      color: #fff;
    }

    @media(max-width:600px) {
      .nav-toggle {
        display: block;
      }

      .nav-center {
        display: none;
      }
    }

    /* ================= HERO ================= */
    .history-hero {
      height: 55vh;
      background: linear-gradient(rgba(29, 36, 51, .6), rgba(29, 36, 51, .6)),
        url("img/pexels-heyho-6312358.jpg") center/cover;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      text-align: center;
      padding: 0 16px;
    }

    .history-hero h1 {
      font-size: 46px;
      font-weight: 600;
    }

    .history-hero span {
      font-size: 14px;
      opacity: .85;
    }

    /* ================= HISTORY ================= */
    .history-section {
      padding: 120px 6%;
    }

    .history-wrapper {
      max-width: 1200px;
      margin: auto;
    }

    .history-title {
      font-size: 34px;
      font-weight: 600;
      color: #1d2433;
      margin-bottom: 40px;
    }

    .history-card {
      background: #fff;
      border-radius: 22px;
      padding: 26px;
      margin-bottom: 24px;
      box-shadow: 0 20px 50px rgba(0, 0, 0, .12);
    }

    @media(max-width:900px) {
      .history-hero h1 {
        font-size: 34px;
      }

      .history-section {
        padding: 90px 6%;
      }

      .history-title {
        font-size: 28px;
        margin-bottom: 26px;
      }

      .history-card {
        padding: 20px;
      }
    }

    @media(max-width:600px) {
      .history-hero {
        height: 48vh;
      }

      .history-hero h1 {
        font-size: 28px;
      }

      .history-hero span {
        font-size: 13px;
      }

      .history-section {
        padding: 80px 6%;
      }

      .history-title {
        font-size: 24px;
      }
    }

    /* ================= FOOTER ================= */
    .main-footer {
      background: #19293e;
      color: #ddd;
      padding: 70px 40px 40px;
      margin-top: 80px;
    }

    .footer-container {
      display: grid;
      grid-template-columns: 1.5fr 1fr 1fr 1fr;
      gap: 40px;
      max-width: 1200px;
      margin: auto;
    }

    .footer-logo {
      font-size: 28px;
      font-weight: 700;
      color: white;
    }

    .footer-desc {
      font-size: 14px;
      opacity: .7;
      margin-top: 10px;
    }

    .footer-col h3 {
      color: white;
      font-size: 16px;
      margin-bottom: 14px;
    }

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
      transition: .25s;
    }

    .footer-col ul li a:hover {
      color: white;
    }

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

    .footer-bottom {
      text-align: center;
      margin-top: 40px;
      padding-top: 18px;
      border-top: 1px solid rgba(255, 255, 255, .1);
      font-size: 13px;
      color: #aaa;
    }

    /* ================= SUBMIT BUTTON ================= */
    .submit-btn {
      padding: 10px 18px;
      border-radius: 8px;
      border: 1.5px solid #2c3e50;
      background: transparent;
      color: #2c3e50;
      font-weight: 600;
      cursor: pointer;
      transition: all .25s ease;
    }

    .submit-btn:hover {
      background: #2c3e50;
      color: #fff;
    }

    .submit-btn:disabled {
      opacity: .6;
      cursor: not-allowed;
    }


    @media(max-width:900px) {
      .footer-container {
        grid-template-columns: 1fr 1fr;
        text-align: left;
      }
    }

    @media(max-width:600px) {
      .main-footer {
        padding: 60px 22px 34px;
      }

      .footer-container {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body>

  <!-- ================= NAVBAR ================= -->
  <nav class="navbar" id="navbar">
    <div class="nav-left-group">
      <button class="nav-toggle" id="navToggle" aria-label="Open menu" type="button">
        <i class="ri-menu-line"></i>
      </button>
      <div class="nav-left">PropertyKu</div>
    </div>

    <!-- CENTER (desktop) -->
    <div class="nav-center" id="navCenter">
      <a href="HOMEPAGEFIX.php" class="<?= basename($_SERVER['PHP_SELF']) == 'HOMEPAGEFIX.php' ? 'active' : '' ?>">Home</a>

      <div class="prop-wrap">
        <button class="prop-btn" id="propBtn" type="button">
          Properti <i class="ri-arrow-down-s-line"></i>
        </button>
        <div class="prop-menu" id="propMenu">
          <?php foreach ($navProps as $p):
            $detailUrl = ($p['type'] === 'RUMAH')
              ? "detailhome.php?id=" . (int)$p['id']
              : "detailapart.php?id=" . (int)$p['id'];
          ?>
            <a class="prop-item" href="<?= $detailUrl ?>">
              <i class="<?= ($p['type'] === 'RUMAH') ? 'ri-home-5-line' : 'ri-building-2-line' ?>"></i>
              <?= htmlspecialchars($p['nama']) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <?php $redir = urlencode($_SERVER["REQUEST_URI"]); ?>
      <a href="<?= isset($_SESSION["user_id"]) ? 'favorite.php' : 'regislogin.php?redirect=' . $redir ?>">Favorite</a>
      <a href="<?= isset($_SESSION["user_id"]) ? 'riwayat.php' : 'regislogin.php?redirect=' . $redir ?>">Riwayat</a>
      <a href="contact.php" class="<?= basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : '' ?>">Contact Us</a>
    </div>

    <!-- RIGHT -->
    <div class="nav-right">
      <?php if (isset($_SESSION["user_id"])): ?>
        <div class="profile-wrap">
          <button class="profile-btn" id="profileBtn" type="button">
            <span><?= strtoupper(substr($userEmail ?? "U", 0, 1)) ?></span>
          </button>

          <div class="profile-menu" id="profileMenu">
            <div class="profile-email"><?= htmlspecialchars($userEmail ?? "") ?></div>
            <div class="profile-name"><?= htmlspecialchars($userName ?? "") ?></div>

            <hr>

            <!-- tombol edit -->
            <button type="button" class="profile-edit-btn" id="btnEditProfile">Edit Profile</button>

            <!-- FORM EDIT (default hidden) -->
            <form method="POST" action="" id="editProfileForm" class="edit-profile-form">
              <input type="hidden" name="action" value="update_profile">

              <input
                type="text"
                name="new_name"
                placeholder="Ganti username"
                value="<?= htmlspecialchars($userName ?? "") ?>">
              <input
                type="password"
                name="new_password"
                placeholder="Password baru *min 6 digit">
              <input
                type="password"
                name="confirm_password"
                placeholder="Konfirmasi password baru">

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
      <a href="HOMEPAGEFIX.php" class="<?= basename($_SERVER['PHP_SELF']) == 'HOMEPAGEFIX.php' ? 'active' : '' ?>">Home</a>

      <button class="m-prop-btn" id="mPropBtn" type="button">
        Properti <i class="ri-arrow-down-s-line"></i>
      </button>
      <div class="m-prop-menu" id="mPropMenu">
        <?php foreach ($navProps as $p):
          $detailUrl = ($p['type'] === 'RUMAH')
            ? "detailhome.php?id=" . (int)$p['id']
            : "detailapart.php?id=" . (int)$p['id'];
        ?>
          <a class="m-prop-item" href="<?= $detailUrl ?>">
            <i class="<?= ($p['type'] === 'RUMAH') ? 'ri-home-5-line' : 'ri-building-2-line' ?>"></i>
            <?= htmlspecialchars($p['nama']) ?>
          </a>
        <?php endforeach; ?>
      </div>

      <?php $redir = urlencode($_SERVER["REQUEST_URI"]); ?>
      <a href="<?= isset($_SESSION["user_id"]) ? 'favorite.php' : 'regislogin.php?redirect=' . $redir ?>">Favorite</a>
      <a href="<?= isset($_SESSION["user_id"]) ? 'riwayat.php' : 'regislogin.php?redirect=' . $redir ?>">Riwayat</a>
      <a href="contact.php" class="<?= basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : '' ?>">Contact Us</a>
    </div>
  </nav>

  <!-- HERO -->
  <section class="history-hero">
    <div>
      <h1>Booking History</h1>
      <span>Your transaction records</span>
    </div>
  </section>

  <!-- HISTORY -->
  <section class="history-section">
    <div class="history-wrapper">
      <h2 class="history-title">My Bookings</h2>
      <div id="historyList">

        <?php if ($bookingRes && $bookingRes->num_rows > 0): ?>
          <?php while ($b = $bookingRes->fetch_assoc()): ?>

            <div class="history-card">
              <h3><?= htmlspecialchars($b['property_name']) ?></h3>
              <p><b>Unit:</b> <?= htmlspecialchars($b['unit_type']) ?></p>
              <p><b>Check-in:</b> <?= htmlspecialchars($b['checkin_date']) ?></p>

              <?php if ($b['status'] === 'wait_payment'): ?>

                <p style="color:#e67e22;font-weight:600;">Menunggu Pembayaran</p>

                <form method="POST" enctype="multipart/form-data">
                  <input type="hidden" name="action" value="upload_payment">
                  <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">

                  <label>Nominal Transfer</label>
                  <input type="number" name="amount" required>

                  <label>Bukti Transfer</label>
                  <input type="file" name="proof" accept="image/*" required>

                  <button type="submit" class="submit-btn" style="margin-top:10px;">
                    Upload Bukti Transfer
                  </button>
                </form>

              <?php elseif ($b['status'] === 'wait_verify'): ?>

                <p style="color:#2980b9;font-weight:600;">Menunggu Verifikasi Admin</p>
                <small>Tunggu 1–5 menit, klik cek status secara berkala.</small><br><br>

                <form method="POST">
                  <input type="hidden" name="action" value="cek_status">
                  <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                  <button type="submit" class="submit-btn">Cek Status</button>
                </form>

              <?php elseif ($b['status'] === 'verified' && (int)$b['user_checked'] === 0): ?>

                <p style="color:#2980b9;font-weight:600;">Menunggu Verifikasi Admin</p>
                <small>Tunggu 1–5 menit, klik cek status secara berkala.</small><br><br>

                <form method="POST">
                  <input type="hidden" name="action" value="cek_status">
                  <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                  <button type="submit" class="submit-btn">Cek Status</button>
                </form>

              <?php elseif ($b['status'] === 'verified' && (int)$b['user_checked'] === 1): ?>

                <p style="color:#27ae60;font-weight:600;">Booking Terverifikasi</p>
                <a href="invoice.php?booking_id=<?= $b['id'] ?>">
                  <button class="submit-btn">Download Invoice (PDF)</button>
                </a>

              <?php elseif ($b['status'] === 'rejected'): ?>

                <p style="color:#c0392b;font-weight:600;">Pembayaran Ditolak</p>

              <?php endif; ?>


            </div>

          <?php endwhile; ?>
        <?php else: ?>
          <p>Kamu belum memiliki riwayat booking.</p>
        <?php endif; ?>

      </div>
    </div>
  </section>


  <!-- FOOTER -->
  <footer class="main-footer">
    <div class="footer-container">
      <div class="footer-col">
        <h2 class="footer-logo">PropertyKu</h2>
        <p class="footer-desc">Platform pencarian properti modern dengan fitur lengkap untuk membantu Anda menemukan hunian terbaik.</p>
      </div>

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

      <div class="footer-col">
        <h3>Layanan</h3>
        <ul>
          <li><a href="#">Promo</a></li>
          <li><a href="#">Survei Lokasi</a></li>
          <li><a href="#">KPR Partner</a></li>
          <li><a href="#">Bantuan</a></li>
        </ul>
      </div>

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

    <div class="footer-bottom">© 2025 PropertyKu — All Rights Reserved.</div>
  </footer>

  <script>
    // scroll effect
    const navbar = document.getElementById("navbar");
    window.addEventListener("scroll", () => {
      navbar.classList.toggle("scrolled", window.scrollY > 80);
    });

    // toggle mobile menu
    const navToggle = document.getElementById("navToggle");
    const navMobile = document.getElementById("navMobile");
    if (navToggle && navMobile) {
      navToggle.addEventListener("click", (e) => {
        e.stopPropagation();
        navMobile.classList.toggle("show");
      });
      navMobile.addEventListener("click", (e) => e.stopPropagation());
    }

    // login button
    const btnLogin = document.getElementById("btnLogin");
    if (btnLogin) btnLogin.addEventListener("click", () => window.location.href = "regislogin.php");

    // profile menu
    const profileBtn = document.getElementById("profileBtn");
    const profileMenu = document.getElementById("profileMenu");
    if (profileBtn && profileMenu) {
      profileBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        profileMenu.classList.toggle("show");
      });
      profileMenu.addEventListener("click", (e) => e.stopPropagation());
    }

    // Properti dropdown desktop
    const propBtn = document.getElementById("propBtn");
    const propMenu = document.getElementById("propMenu");
    if (propBtn && propMenu) {
      propBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        propMenu.classList.toggle("show");
      });
      propMenu.addEventListener("click", (e) => e.stopPropagation());
    }

    // Properti dropdown mobile
    const mPropBtn = document.getElementById("mPropBtn");
    const mPropMenu = document.getElementById("mPropMenu");
    if (mPropBtn && mPropMenu) {
      mPropBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        mPropMenu.classList.toggle("show");
      });
      mPropMenu.addEventListener("click", (e) => e.stopPropagation());

      // klik salah satu properti -> tutup semua
      mPropMenu.querySelectorAll("a").forEach(a => {
        a.addEventListener("click", () => {
          navMobile.classList.remove("show");
          mPropMenu.classList.remove("show");
        });
      });
    }

    // Edit profile toggle
    const btnEditProfile = document.getElementById("btnEditProfile");
    const editProfileForm = document.getElementById("editProfileForm");
    const btnCancelEdit = document.getElementById("btnCancelEdit");

    if (btnEditProfile && editProfileForm) {
      btnEditProfile.addEventListener("click", (e) => {
        e.stopPropagation();
        editProfileForm.classList.toggle("show");
      });
    }
    if (btnCancelEdit && editProfileForm) {
      btnCancelEdit.addEventListener("click", (e) => {
        e.stopPropagation();
        editProfileForm.classList.remove("show");
      });
    }
    if (editProfileForm) {
      editProfileForm.addEventListener("click", (e) => e.stopPropagation());
    }

    // satu klik global untuk nutup semua
    document.addEventListener("click", () => {
      navMobile?.classList.remove("show");
      profileMenu?.classList.remove("show");
      propMenu?.classList.remove("show");
      mPropMenu?.classList.remove("show");
    });
  </script>

  <?php if (isset($_GET['profile']) && $_GET['profile'] === 'success'): ?>
    <script>
      alert("Profil berhasil diperbarui!");
    </script>
  <?php elseif (isset($_GET['profile']) && $_GET['profile'] === 'fail'): ?>
    <script>
      alert("Gagal update profil. Cek username/password kamu.");
    </script>
  <?php endif; ?>

</body>

</html>