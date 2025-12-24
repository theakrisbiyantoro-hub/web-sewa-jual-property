<?php
include "database.php";
session_start();

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

$property_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($property_id <= 0) die("Property ID tidak valid.");

$BASE_UPLOAD = "uploads/";

/* ===== helpers ===== */
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }
function rupiah($n){ return "Rp " . number_format((float)$n, 0, ",", "."); }
function file_url($base, $file){ return $base . ltrim((string)$file, "/"); }
function parse_unit_desc($text){
        $out = [
            "area" => "-",
            "bedrooms" => "-",
            "bathrooms" => "-",
            "ceiling" => "-",
        ];

        $text = (string)$text;
        $lines = preg_split("/\r\n|\n|\r/", $text);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === "" || strpos($line, ":") === false) continue;

            [$k, $v] = array_map("trim", explode(":", $line, 2));
            $k = strtolower($k);

            $map = [
                "area" => "area",
                "luas" => "area",
                "total area" => "area",

                "bedrooms" => "bedrooms",
                "bedroom" => "bedrooms",
                "kamar" => "bedrooms",
                "kamar tidur" => "bedrooms",

                "bathrooms" => "bathrooms",
                "bathroom" => "bathrooms",
                "wc" => "bathrooms",
                "toilet" => "bathrooms",

                "ceiling" => "ceiling",
                "ceiling height" => "ceiling",
                "plafon" => "ceiling",
                "tinggi plafon" => "ceiling",
            ];

            if (isset($map[$k]) && $v !== "") {
                $out[$map[$k]] = $v;
            }
        }

        return $out;
    }
    function parse_denah_desc($text){
        $out = [
            "area" => "-",
            "bedrooms" => "-",
            "bathrooms" => "-",
            "floors" => "-",
        ];

        $text = (string)$text;
        $lines = preg_split("/\r\n|\n|\r/", $text);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === "" || strpos($line, ":") === false) continue;

            [$k, $v] = array_map("trim", explode(":", $line, 2));
            $k = strtolower($k);

            $map = [
                "area" => "area",
                "luas" => "area",
                "total area" => "area",

                "bedrooms" => "bedrooms",
                "bedroom" => "bedrooms",
                "kamar" => "bedrooms",
                "kamar tidur" => "bedrooms",

                "bathrooms" => "bathrooms",
                "bathroom" => "bathrooms",
                "wc" => "bathrooms",
                "toilet" => "bathrooms",
                "kamar mandi" => "bathrooms",

                "floors" => "floors",
                "floor" => "floors",
                "lantai" => "floors",
                "jumlah lantai" => "floors",
            ];

            if (isset($map[$k]) && $v !== "") {
                $out[$map[$k]] = $v;
            }
        }

        return $out;
    }
    function denah_public_text($text){
        $text = (string)$text;
        $lines = preg_split("/\r\n|\n|\r/", $text);
        $keep = [];

        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === "") { 
                $keep[] = $line; 
                continue; 
            }

            // kalau format "key: value"
            if (strpos($trim, ":") !== false) {
                [$k, $v] = array_map("trim", explode(":", $trim, 2));
                $k = strtolower($k);

                // daftar key yang mau disembunyikan dari user
                $specKeys = [
                    "area","luas","total area",
                    "bedrooms","bedroom","kamar","kamar tidur",
                    "bathrooms","bathroom","wc","toilet","kamar mandi",
                    "floors","floor","lantai","jumlah lantai",
                ];

                if (in_array($k, $specKeys, true)) {
                    continue; // skip baris spec
                }
            }

            $keep[] = $line;
        }

        // rapihin: hapus baris kosong berlebihan
        $out = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $keep)));
        return $out;
    }

/* ambil property */
$p = $koneksi->prepare("SELECT * FROM property WHERE id=? AND type='RUMAH'");
$p->bind_param("i", $property_id);
$p->execute();
$property = $p->get_result()->fetch_assoc();
$p->close();

if (!$property) die("Rumah tidak ditemukan");

/* ambil media */
$m = $koneksi->prepare("SELECT * FROM property_media WHERE id_property=? ORDER BY id ASC");
$m->bind_param("i", $property_id);
$m->execute();
$rows = $m->get_result()->fetch_all(MYSQLI_ASSOC);
$m->close();

$media = ["PANORAMA"=>[], "FASILITAS"=>[], "PUBLIC_DOMAIN"=>[], "UNIT_LAYOUT"=>[], "SPEC"=>[], "DENAH"=>[]];
    foreach ($rows as $r) {
        if (isset($media[$r["type"]])) $media[$r["type"]][] = $r;
    }
$denahDesc = $media["DENAH"][0]["deskripsi"] ?? "";
$denahSpec = parse_denah_desc($denahDesc);

// HANDLE SUBMIT BOOKING (RUMAH)
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["do_booking"])) {

        if (!isset($_SESSION["user_id"])) {
            header("Location: regislogin.php");
            exit;
        }

        $full_name = trim($_POST["full_name"] ?? "");
        $email     = trim($_POST["email"] ?? "");
        $phone     = trim($_POST["phone"] ?? "");

        if ($full_name === "" || $email === "" || $phone === "") {
            $error = "Data booking belum lengkap.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Email tidak valid.";
        } else {

            $stmt = $koneksi->prepare(
                "INSERT INTO booking (property_id, full_name, email, phone)
                VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("isss", $property_id, $full_name, $email, $phone);

            if ($stmt->execute()) {
                $booking_id = $stmt->insert_id;
                $stmt->close();

                // WA redirect (opsional tapi cakep)
                $waNumber = "62895347360010";
                $message =
                    "Hello Admin, I am interested in this house.\n\n" .
                    "Property: " . ($property["nama"] ?? "-") . "\n" .
                    "Name: {$full_name}\n" .
                    "Phone: {$phone}\n" .
                    "Email: {$email}\n" .
                    "Booking ID: {$booking_id}";

                header("Location: https://wa.me/{$waNumber}?text=" . urlencode($message));
                exit;

            } else {
                $error = "Gagal menyimpan booking.";
                $stmt->close();
            }
        }
    }

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
        header("Location: ".$_SERVER["PHP_SELF"]."?id=".$property_id."&profile=fail");
        exit;
    }

    if ($new_password !== "") {
        if (strlen($new_password) < 6 || $new_password !== $confirm_password) {
        header("Location: ".$_SERVER["PHP_SELF"]."?id=".$property_id."&profile=fail");
        exit;
        }
    }

    // kalau kosong semua
    if ($new_name === "" && $new_password === "") {
        header("Location: ".$_SERVER["PHP_SELF"]."?id=".$property_id."&profile=fail");
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
        header("Location: ".$_SERVER["PHP_SELF"]."?id=".$property_id."&profile=success");
        exit;
    }
    header("Location: ".$_SERVER["PHP_SELF"]."?id=".$property_id."&profile=fail");
    exit;

}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.4.0/fonts/remixicon.css" rel="stylesheet">
    <title>Document</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Poppins", sans-serif;
        }

        /* ================= NAVBAR ================= */
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

        /* Edit Profile */
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


        .hero-slider {
            position: relative;
            width: 100%;
            height: 100vh;
            overflow: hidden;
        }

        .slide {
            position: absolute;
            inset: 0;
            opacity: 0;
            transition: opacity 1.2s ease-in-out;
        }

        .slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .slide.active {
            opacity: 1;
        }

        .overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.25);
        }

        .hero-text {
            position: absolute;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            width: 60%;
            text-align: center;
            font-size: 22px;
            font-weight: 300;
            line-height: 1.6;
        }

        .hero-btn {
            position: absolute;
            bottom: 60px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(255, 255, 255, 0.85);
            padding: 12px 30px;
            border-radius: 40px;
            border: none;
            cursor: pointer;
            font-size: 15px;
        }

        /* ================= APARTMENT DETAIL ================= */
        .apartment-detail {
            padding: 120px 6%;
            background: #f9fafb;
        }

        .apartment-wrapper {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            gap: 60px;
            align-items: center;
        }

        /* IMAGE */
        .apartment-image img {
            width: 100%;
            height: 520px;
            object-fit: cover;
            border-radius: 22px;
        }

        /* INFO */
        .apartment-info {
            color: #1d2433;
        }

        .apartment-category {
            display: inline-block;
            font-size: 13px;
            letter-spacing: 1px;
            color: #6b7280;
            margin-bottom: 14px;
            text-transform: uppercase;
        }

        .apartment-title {
            font-size: 40px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .apartment-location {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 22px;
        }

        .apartment-desc {
            font-size: 15px;
            line-height: 1.8;
            color: #4b5563;
            margin-bottom: 34px;
        }

        /* SPEC */
        .apartment-spec {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 40px;
        }

        .apartment-spec span {
            font-size: 13px;
            color: #6b7280;
        }

        .apartment-spec strong {
            display: block;
            font-size: 16px;
            margin-top: 6px;
        }

        /* STATUS */
        .status.available {
            color: #16a34a;
        }

        /* BUTTON */
        .apartment-btn {
            padding: 14px 36px;
            border-radius: 40px;
            border: none;
            background: #1d2433;
            color: white;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: 0.3s;
        }

        .apartment-btn:hover {
            background: #111827;
        }

        .apartment-divider {
            width: 100%;
            height: 2px;
            background: linear-gradient(to right,
                    transparent,
                    rgba(29, 36, 51, 0.25),
                    transparent);
            margin: 36px 0;
        }

        /* ================= FLOORPLAN SECTION ================= */
        .floorplan-section {
            padding: 120px 6%;
            background: #f9fafb;
        }

        .floorplan-wrapper {
            display: grid;
            grid-template-columns: 1fr 1.1fr;
            gap: 80px;
            align-items: center;
        }

        /* LEFT */
        .floorplan-title {
            font-size: 34px;
            font-weight: 600;
            color: #1d2433;
            margin-bottom: 24px;
        }

        .floorplan-desc {
            font-size: 15px;
            line-height: 1.9;
            color: #4b5563;
            margin-bottom: 22px;
        }

        /* META INFO (HALUS, BUKAN TABEL) */
        .floorplan-meta {
            display: flex;
            gap: 40px;
            margin-top: 30px;
        }

        .floorplan-meta div {
            text-align: left;
        }

        .floorplan-meta strong {
            font-size: 20px;
            color: #1d2433;
            display: block;
        }

        .floorplan-meta span {
            font-size: 13px;
            color: #6b7280;
        }

        /* RIGHT IMAGE */
        .floorplan-image img {
            width: 100%;
            max-height: 520px;
            object-fit: contain;
            padding: 32px;
            border-radius: 22px;
        }

        .title-apartment {
            color: #657aa5;
            font-weight: 500;
        }

        /* ================= FACILITY SLIDER ================= */
        .facility-section {
            padding: 120px 6% 140px;
            background: #f9fafb;
        }

        .facility-header {
            position: relative;
            text-align: center;
            margin-bottom: 60px;
        }

        .facility-label {
            font-size: 12px;
            letter-spacing: 2px;
            color: #9ca3af;
            display: block;
            margin-bottom: 12px;
        }

        .facility-title {
            font-size: 40px;
            font-weight: 500;
            color: #1d2433;
        }

        /* NAV ARROW */
        .facility-nav {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            gap: 14px;
        }

        .facility-nav button {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: 1px solid #d1d5db;
            background: transparent;
            cursor: pointer;
            font-size: 18px;
            color: #374151;
            transition: 0.3s;
        }

        .facility-nav button:hover {
            background: #1d2433;
            color: #ffffff;
        }

        /* SLIDER */
        .facility-slider {
            display: flex;
            gap: 40px;
            overflow-x: hidden;
            scroll-behavior: smooth;
        }

        /* CARD */
        .facility-card {
            min-width: 420px;
            text-align: center;
        }

        .facility-card img {
            width: 100%;
            height: 320px;
            object-fit: cover;
            border-radius: 6px;
            margin-bottom: 18px;
        }

        .facility-card p {
            font-size: 15px;
            color: #4b5563;
        }

        /* ================= UNIT LAYOUT ================= */
        .unit-layout-section {
            padding: 120px 6%;
            background: #f9fafb;
        }

        .unit-layout-wrapper {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            gap: 80px;
            align-items: center;
        }

        /* IMAGE */
        .unit-image img {
            width: 100%;
            max-height: 520px;
            object-fit: contain;
            padding: 28px;
            border-radius: 22px;
        }

        /* TABS */
        .unit-tabs {
            display: flex;
            gap: 14px;
            margin-bottom: 32px;
        }

        .unit-tab {
            padding: 10px 22px;
            border-radius: 30px;
            border: 1px solid #d1d5db;
            background: transparent;
            font-size: 14px;
            cursor: pointer;
            color: #374151;
            transition: 0.3s;
        }

        .unit-tab.active,
        .unit-tab:hover {
            background: #1d2433;
            color: #ffffff;
        }

        /* INFO */
        .unit-title {
            font-size: 32px;
            font-weight: 600;
            color: #1d2433;

            margin-top: 40px;
            /* 🔥 INI YANG NGASIH JARAK */
            margin-bottom: 30px;
        }

        /* ================= UNIT LAYOUT LIST ================= */
        .unit-layout-list {
            margin-top: 30px;
            border-top: 1px solid #e5e7eb;
        }

        .layout-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .layout-row span {
            font-size: 13px;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #6b7280;
        }

        .layout-row strong {
            font-size: 15px;
            font-weight: 500;
            color: #1d2433;
        }

        /* TOTAL PRICE */
        .layout-row.highlight {
            background: #f3f4f6;
            padding: 22px 18px;
            margin-top: 6px;
            border-bottom: none;
        }

        .layout-row.highlight span {
            color: #374151;
        }

        .layout-row.highlight strong {
            font-size: 17px;
        }

        .unit-image-title {
            font-size: 33px;
            font-weight: 600;
            color: #1d2433;
            margin-bottom: 18px;
        }

        .unit-360-btn {
            margin-top: 22px;
            display: inline-flex;
            align-items: center;
            gap: 10px;

            padding: 12px 26px;
            border-radius: 30px;

            background: #1d2433;
            color: #ffffff;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;

            transition: all 0.3s ease;
        }

        .unit-360-btn i {
            font-size: 18px;
        }

        .unit-360-btn:hover {
            background: #111827;
            transform: translateY(-2px);
        }

        .unit-spec-section {
            padding: 120px 6%;
            background: #f9fafb;
        }

        .unit-spec-wrapper {
            max-width: 1200px;
            margin: auto;
            text-align: center;
            margin-top: -80px;
        }

        .unit-spec-image {
            position: relative;
            display: inline-block;
        }

        .unit-spec-image img {
            width: 100%;
            max-width: 900px;
            border-radius: 26px;
        }

        /* FLOATING PREVIEW CARD */
        .spec-preview {
            position: absolute;
            bottom: 40px;
            right: 40px;

            width: 200px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 22px;
            padding: 14px;

            box-shadow: 0 20px 40px rgba(0, 0, 0, .18);

            opacity: 0;
            transform: translateY(10px);
            pointer-events: none;

            transition: all .35s ease;
        }

        .spec-preview img {
            width: 100%;
            border-radius: 14px;
            margin-bottom: 10px;
        }

        .spec-preview p {
            font-weight: 600;
            color: #1d2433;
        }

        /* BUTTONS */
        .spec-buttons {
            margin-top: 26px;
            display: flex;
            justify-content: center;
            gap: 14px;
        }

        .spec-buttons button {
            padding: 10px 22px;
            border-radius: 30px;
            border: none;
            background: #f3f4f6;
            cursor: pointer;
            font-size: 14px;
            transition: .3s;
        }

        .spec-buttons button:hover {
            background: #1d2433;
            color: white;
        }

        .unit-image {
            position: relative;
        }

        .unit-nav {
            position: absolute;
            display: flex;
            align-items: center;
            gap: 8px;

            background: rgba(0, 0, 0, 0.55);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 40px;
            cursor: pointer;
        }

        .unit-nav.next {
            top: 20px;
            right: 20px;
        }

        .unit-nav.prev {
            bottom: 20px;
            left: 20px;
        }

        .unit-spec-header {
            margin-bottom: 20px;
            text-align: center;
        }

        .unit-spec-label {
            font-size: 12px;
            letter-spacing: 2px;
            color: #9ca3af;
            display: block;
            margin-bottom: 8px;
        }

        .unit-spec-title {
            font-size: 40px;
            font-weight: 600;
            color: #1d2433;
        }

        /* ================= UNIT VIDEO SECTION ================= */
        .unit-video-section {
            padding: 140px 6%;
            background: #f9fafb;
        }

        .unit-video-wrapper {
            max-width: 1400px;
            margin: auto;
        }

        /* HEADER */
        .unit-video-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .unit-video-label {
            font-size: 12px;
            letter-spacing: 2px;
            color: #9ca3af;
            display: block;
            margin-bottom: 10px;
        }

        .unit-video-title {
            font-size: 42px;
            font-weight: 600;
            color: #2d3c59;
            margin-bottom: 14px;
        }

        .unit-video-desc {
            max-width: 680px;
            margin: auto;
            font-size: 15px;
            line-height: 1.8;
            color: #4b5563;
        }

        /* VIDEO CONTAINER */
        .unit-video-container {
            position: relative;
            margin-top: 60px;
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 40px 80px rgba(0, 0, 0, 0.18);
        }

        .unit-video-container video {
            width: 100%;
            height: auto;
            display: block;
        }

        .video-overlay i {
            font-size: 88px;
            color: rgba(255, 255, 255, 0.9);
            filter: drop-shadow(0 10px 30px rgba(0, 0, 0, 0.4));
        }

        /* Highlight word "Motion" */
        .motion-highlight {
            color: #6b80aa;
            /* abu ke navy */
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        /* ================= PUBLIC DOMAIN ================= */
        .public-domain-section {
            padding: 140px 6%;
            background: #f9fafb;
        }

        .public-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 50px;
        }

        .public-header h2 {
            font-size: 42px;
            font-weight: 600;
            line-height: 1.15;
            color: #1d2433;
        }

        .public-header p {
            margin-top: 14px;
            font-size: 14px;
            color: #6b7280;
        }

        /* NAV */
        .public-nav {
            display: flex;
            gap: 12px;
        }

        .public-nav button {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: 1px solid #d1d5db;
            background: transparent;
            cursor: pointer;
            transition: .3s;
        }

        .public-nav button:hover {
            background: #1d2433;
            color: white;
        }

        /* SLIDER */
        .public-slider {
            display: flex;
            gap: 28px;
            overflow-x: hidden;
            scroll-behavior: smooth;
        }

        /* CARD */
        .public-item {
            min-width: 320px;
            cursor: pointer;
        }

        .public-item img {
            width: 100%;
            aspect-ratio: 4 / 5;
            object-fit: cover;
            transition: transform .6s ease;
        }

        .public-info {
            margin-top: 16px;
        }

        .public-info h4 {
            font-size: 16px;
            font-weight: 500;
            color: #1d2433;
        }

        .public-info span {
            font-size: 13px;
            color: #6b7280;
        }

        /* HOVER */
        .public-item:hover img {
            transform: scale(1.08);
        }

        /* ================= LOCATION MAP ================= */
        .location-section {
            padding: 140px 6%;
            background: #f9fafb;
        }

        .location-wrapper {
            max-width: 1400px;
            margin: auto;
        }

        .location-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .location-label {
            font-size: 12px;
            letter-spacing: 2px;
            color: #9ca3af;
            display: block;
            margin-bottom: 10px;
        }

        .location-title {
            font-size: 42px;
            font-weight: 600;
            color: #1d2433;
            margin-bottom: 14px;
        }

        .location-desc {
            max-width: 620px;
            margin: auto;
            font-size: 15px;
            line-height: 1.8;
            color: #4b5563;
        }

        /* MAP */
        .map-container {
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 40px 80px rgba(0, 0, 0, 0.18);
        }

        .map-container iframe {
            width: 100%;
            height: 520px;
            border: none;
        }

        /* ================= BOOKING FORM ================= */
        .booking-section {
            padding: 140px 6%;
            background: #f9fafb;
        }

        .booking-wrapper {
            max-width: 900px;
            margin: auto;
        }

        .booking-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .booking-label {
            font-size: 12px;
            letter-spacing: 2px;
            color: #9ca3af;
            display: block;
            margin-bottom: 10px;
        }

        .booking-title {
            font-size: 42px;
            font-weight: 600;
            color: #1d2433;
            margin-bottom: 12px;
        }

        .booking-desc {
            font-size: 15px;
            color: #4b5563;
        }

        .booking-form {
            display: grid;
            gap: 22px;
            background: #ffffff;
            padding: 40px;
            border-radius: 28px;
            box-shadow: 0 30px 70px rgba(0, 0, 0, .12);
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 6px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid #d1d5db;
            font-size: 14px;
            outline: none;
        }

        .form-group textarea {
            resize: none;
            height: 90px;
        }

        .booking-btn {
            margin-top: 10px;
            padding: 16px;
            border-radius: 40px;
            border: none;
            background: #1d2433;
            color: white;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: .3s;
        }

        .booking-btn:hover {
            background: #111827;
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

        .spec-unit-switch {
            display: flex;
            justify-content: center;
            gap: 14px;
            margin-bottom: 26px;
        }

        .spec-unit-btn {
            padding: 10px 26px;
            border-radius: 30px;
            border: 1px solid #d1d5db;
            background: transparent;
            font-size: 14px;
            cursor: pointer;
            transition: .3s;
        }

        .spec-unit-btn.active,
        .spec-unit-btn:hover {
            background: #1d2433;
            color: white;
        }

        .spec-panorama {
            margin-top: 26px;
            display: flex;
            justify-content: center;
        }

        .panorama-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;

            padding: 14px 34px;
            border-radius: 40px;

            background: #1d2433;
            color: #ffffff;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;

            transition: all 0.3s ease;
        }

        .panorama-link i {
            font-size: 18px;
        }

        .panorama-link:hover {
            background: #111827;
            transform: translateY(-2px);
        }

        /* ===========================
   RESPONSIVE — TABLET
=========================== */
        @media (max-width: 1024px) {

            /* NAVBAR */
            .nav-center {
                gap: 22px;
            }

            .hero-text {
                width: 80%;
                font-size: 18px;
            }

            /* GRID SECTIONS */
            .apartment-wrapper,
            .floorplan-wrapper,
            .unit-layout-wrapper {
                grid-template-columns: 1fr;
                gap: 50px;
            }

            .apartment-image img,
            .floorplan-image img,
            .unit-image img {
                height: auto;
                max-height: 420px;
            }

            /* TITLES */
            .apartment-title {
                font-size: 34px;
            }

            .facility-title,
            .unit-spec-title,
            .unit-video-title,
            .location-title,
            .booking-title {
                font-size: 34px;
            }

            /* FACILITY */
            .facility-card {
                min-width: 340px;
            }
        }

        /* ===========================
   RESPONSIVE — MOBILE
=========================== */
        @media (max-width: 600px) {

            /* NAVBAR */
            .navbar {
                height: 56px;
                padding: 0 18px;
            }

            .nav-center {
                display: none;
            }

            .login-btn {
                padding: 8px 18px;
                font-size: 13px;
            }

            /* HERO */
            .hero-slider {
                height: 85vh;
            }

            .hero-text {
                width: 90%;
                font-size: 16px;
                line-height: 1.6;
            }

            .hero-btn {
                bottom: 40px;
                padding: 10px 24px;
                font-size: 14px;
            }

            /* SECTION PADDING */
            section {
                padding: 90px 6% !important;
            }

            /* TITLES */
            .apartment-title {
                font-size: 28px;
            }

            .floorplan-title,
            .facility-title,
            .unit-image-title,
            .unit-spec-title,
            .unit-video-title,
            .location-title,
            .booking-title {
                font-size: 28px;
            }

            /* TEXT */
            .apartment-desc,
            .floorplan-desc,
            .unit-video-desc,
            .location-desc,
            .booking-desc {
                font-size: 14px;
            }

            /* SPEC GRID */
            .apartment-spec {
                grid-template-columns: 1fr 1fr;
                gap: 18px;
            }

            /* FACILITY SLIDER */
            .facility-slider {
                overflow-x: auto;
                scroll-snap-type: x mandatory;
            }

            .facility-card {
                min-width: 280px;
                scroll-snap-align: start;
            }

            /* UNIT TABS */
            .unit-tabs {
                flex-wrap: wrap;
                gap: 10px;
            }

            .unit-tab {
                font-size: 13px;
                padding: 8px 18px;
            }

            /* UNIT IMAGE BUTTON */
            .unit-360-btn {
                width: 100%;
                justify-content: center;
                margin-top: 18px;
            }

            /* PUBLIC DOMAIN */
            .public-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 22px;
            }

            .public-slider {
                overflow-x: auto;
                scroll-snap-type: x mandatory;
            }

            .public-item {
                min-width: 260px;
                scroll-snap-align: start;
            }

            /* MAP */
            .map-container iframe {
                height: 360px;
            }

            /* BOOKING */
            .booking-form {
                padding: 28px;
            }

            /* FOOTER */
            .footer-container {
                grid-template-columns: 1fr;
                text-align: left;
            }
        }
        
        @media (max-width: 600px){
            .floorplan-wrapper{
                grid-template-columns: 1fr !important;
                gap: 26px !important;
                align-items: stretch !important; 
            }

            .floorplan-info,
            .floorplan-image{
                height: auto !important;
                max-height: none !important;
                overflow: visible !important;
            }

            .floorplan-desc{
                overflow-wrap: anywhere; 
                word-break: break-word;
            }

            .floorplan-meta{
                flex-wrap: wrap !important;
                gap: 14px !important;
            }

            .floorplan-meta > div{
                flex: 1 1 calc(50% - 14px);  
                min-width: 140px;
            }

            .floorplan-image img{
                display: block;
                width: 100%;
                height: auto;
                max-height: none !important;
                padding: 0 !important;     
                object-fit: contain;
            }
        }

    html, body {
        width: 100%;
        overflow-x: hidden;
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
                <input type="text" name="new_name" placeholder="Ganti username" value="<?= htmlspecialchars($userName ?? "") ?>">
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

    <!-- ================= HERO SECTION ================= -->
    <div class="hero-slider">

        <div class="slide active">
            <img src="img/pexels-jvdm-1457842.jpg">
        </div>

        <div class="slide">
            <img src="img/pexels-fotografiagmazg-27349378.jpg">
        </div>

        <div class="slide">
            <img src="img/pexels-heyho-11 (2).jpg">
        </div>

        <div class="overlay"></div>

        <div class="hero-text">
            YOUR SOURCE OF MODERN AND STYLISH INTERIOR
            DECOR TO CREATE A UNIQUE ATMOSPHERE
            IN YOUR HOME.
        </div>

        <button class="hero-btn" id="btnBrowseCatalog" type="button">Browse Catalog</button>
    </div>

    <!-- ================= DESKRIPSI APARTEMEN ================= -->
    <section class="apartment-detail">
        <div class="apartment-wrapper">

            <!-- LEFT IMAGE -->
            <div class="apartment-image">
                <img src="<?= esc(file_url($BASE_UPLOAD, $property['cover'])) ?>">
            </div>

            <!-- RIGHT CONTENT -->
            <div class="apartment-info">
                <span class="apartment-category"><?= esc($property['kategori']) ?></span>

                <h2 class="apartment-title"><?= esc($property['nama']) ?></h2>
                <p class="apartment-location">
                    <i class="ri-map-pin-line"></i>
                    <?= esc($property['lokasi']) ?>
                </p>

                <p class="apartment-desc"><?= nl2br(esc($property['deskripsi'])) ?></p>

                <div class="apartment-divider"></div>

                <div class="apartment-spec">
                    <div>
                        <span>Units</span>
                        <strong><?= esc($property['jumlah_unit']) ?> Units</strong>
                    </div>
                    <div>
                        <span>Price Starting From</span>
                        <strong><?= esc(rupiah($property['harga'])) ?></strong>
                    </div>
                    <div>
                        <span>Status</span>
                        <strong><?= esc($property['status']) ?></strong>
                    </div>
                </div>

                <a href="#bookingForm" class="apartment-btn">Book Now</a>
            </div>

        </div>
    </section>

    <!-- ================= DENAH UTAMA APARTEMEN ================= -->
    <section class="floorplan-section">
        <div class="floorplan-wrapper">

            <!-- LEFT : FLOORPLAN DESCRIPTION ONLY -->
            <div class="floorplan-info">
                    <h2 class="floorplan-title">
                        Floor Plan <span class="title-apartment">House</span>
                    </h2>

                    <?php if (trim($denahDesc) !== ""): ?>
                        <?php $denahPublic = denah_public_text($denahDesc); ?>
                        <p class="floorplan-desc"><?= nl2br(esc($denahPublic)) ?></p>
                    <?php else: ?>
                        <p class="floorplan-desc" style="opacity:.8;">
                            Deskripsi denah belum diisi.
                        </p>
                    <?php endif; ?>

                    <div class="apartment-divider"></div>

                    <div class="floorplan-meta">
                        <div><strong><?= esc($denahSpec["area"]) ?></strong><span>Total Area</span></div>
                        <div><strong><?= esc($denahSpec["bedrooms"]) ?></strong><span>Bedrooms</span></div>
                        <div><strong><?= esc($denahSpec["bathrooms"]) ?></strong><span>Bathrooms</span></div>
                        <div><strong><?= esc($denahSpec["floors"]) ?></strong><span>Floors</span></div>
                    </div>
                </div>

            <!-- RIGHT : FLOORPLAN IMAGE -->
            <div class="floorplan-image">
                <img src="<?= esc(file_url($BASE_UPLOAD, $property['denah'])) ?>"alt="House Floor Plan">
            </div>

        </div>
    </section>

    <!-- ================= FASILITAS ================= -->
    <section class="facility-section">

        <div class="facility-header">
            <span class="facility-label">Facilities</span>
            <h2 class="facility-title">The atmosphere of a comfortable life</h2>

            <div class="facility-nav">
                <button id="facilityPrev"><i class="ri-arrow-left-line"></i></button>
                <button id="facilityNext"><i class="ri-arrow-right-line"></i></button>
            </div>
        </div>

        <div class="facility-slider" id="facilitySlider">

            <?php if (empty($media["FASILITAS"])): ?>
                <p>Belum ada fasilitas.</p>
                <?php else: ?>
                <?php foreach ($media["FASILITAS"] as $f): ?>
                    <div class="facility-card">
                    <img src="<?= esc(file_url($BASE_UPLOAD, $f['file'])) ?>">
                    <p><?= esc($f['nama']) ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>

    </section>

    <!-- ================= UNIT SPESIFIKASI ================= -->
    <?php
        $specItems = $media["SPEC"] ?? [];
        $firstImg   = !empty($specItems) ? file_url($BASE_UPLOAD, $specItems[0]["file"]) : "";
        $firstTitle = !empty($specItems) ? ($specItems[0]["nama"] ?? "-") : "-";
    ?>

    <section class="unit-spec-section">
    <div class="unit-spec-wrapper">

        <div class="unit-spec-header">
        <span class="unit-spec-label">UNIT</span>
        <h2 class="unit-spec-title">House Specifications</h2>
        </div>

        <?php if (empty($specItems)): ?>
            <p style="text-align:center;opacity:.7;">Belum ada spesifikasi unit.</p>
        <?php else: ?>

        <!-- IMAGE -->
        <div class="unit-spec-image">
            <img id="specMainImg"
                src="<?= esc($firstImg) ?>"
                alt="Unit Interior">

            <button class="unit-nav next" id="specNextBtn" type="button">
            <span id="nextLabel">-</span>
            <i class="ri-arrow-right-s-line"></i>
            </button>

            <button class="unit-nav prev" id="specPrevBtn" type="button">
            <i class="ri-arrow-left-s-line"></i>
            <span id="prevLabel">-</span>
            </button>

            <!-- FLOAT PREVIEW -->
            <div class="spec-preview" id="specPreview">
            <img id="previewImg" src="" alt="">
            <p id="previewTitle">-</p>
            </div>
        </div>

        <!-- BUTTONS -->
        <div class="spec-buttons">
            <?php foreach ($specItems as $s): ?>
            <button class="spec-btn"
                type="button"
                data-title="<?= esc($s['nama'] ?? 'Spec') ?>"
                data-img="<?= esc(file_url($BASE_UPLOAD, $s['file'])) ?>">
                <?= esc($s['nama'] ?? 'Spec') ?>
            </button>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>

    </div>
            <div class="spec-panorama">
                <a href="https://tour.panoee.net/6948eeeeb66ec83a6810e420/6948eef6b66ec89b7c10e437" target="_blank" class="panorama-link">
                    <i class="ri-360-line"></i>
                    Explore 360° Virtual Tour
                </a>
            </div>

        </div>

    </section>

    <!-- ================= VIDEO SECTION ================= -->
    <section class="unit-video-section">

        <div class="unit-video-wrapper">

            <div class="unit-video-header">
                <span class="unit-video-label">EXPERIENCE</span>
                <h2 class="unit-video-title">
                    Explore The Unit in <span class="motion-highlight">Motion</span>
                </h2>
                <p class="unit-video-desc">
                    Discover the atmosphere, layout, and interior details of the unit through a cinematic walkthrough.
                </p>
            </div>

            <div class="unit-video-container">
                <!-- GANTI src sesuai video kamu -->
                <video controls preload="metadata">
                    <source src="<?= esc(file_url($BASE_UPLOAD, $property['video'])) ?>" type="video/mp4">
                </video>
            </div>

        </div>

    </section>

    <!-- ================= LOCATION MAP ================= -->
    <section class="location-section">
        <div class="location-wrapper">

            <div class="location-header">
                <span class="location-label">LOCATION</span>
                <h2 class="location-title">House Location</h2>
                <p class="location-desc">
                    Strategically located in the heart of the city with easy access to public facilities.
                </p>
            </div>

            <div class="map-container">
                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d31696.9917809989!2d112.736!3d-7.275!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2dd7fbf2c5a1c9d9%3A0x123456789abcdef!2sSurabaya!5e0!3m2!1sen!2sid!4v000000000"
                    allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>

        </div>
    </section>

    <!-- ================= BOOKING FORM ================= -->
    <section class="booking-section">
        <div class="booking-wrapper">

            <div class="booking-header">
                <span class="booking-label">BOOKING</span>
                <h2 class="booking-title">Book Your Unit</h2>
                <p class="booking-desc">
                    Fill in your details and choose your preferred unit type.
                </p>
            </div>

            <form class="booking-form" id="bookingForm" method="POST">
                <input type="hidden" name="do_booking" value="1">

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" id="fullName" name="full_name" required>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" id="phone" name="phone" required>
                </div>

                <button type="submit" class="booking-btn"
                    <?= !isset($_SESSION["user_id"]) ? 'onclick="window.location.href=\'regislogin.php\'; return false;"' : '' ?>>
                    Submit
                </button>

            </form>

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
                    <a href="#"><i class="ri-instagram-line"></i></a>
                    <a href="#"><i class="ri-facebook-circle-line"></i></a>
                    <a href="#"><i class="ri-twitter-x-line"></i></a>
                </div>
            </div>

        </div>

        <div class="footer-bottom">
            © 2025 PropertyKu — All Rights Reserved.
        </div>

    </footer>

<script>
  /* ================= HERO SLIDER (punyamu) ================= */
  let slides = document.querySelectorAll(".slide");
  let index = 0;

  function changeSlide() {
    slides[index].classList.remove("active");
    index = (index + 1) % slides.length;
    slides[index].classList.add("active");
  }
  setInterval(changeSlide, 4000);

  /* ================= FACILITY SLIDER (punyamu) ================= */
  const slider = document.getElementById("facilitySlider");
  const nextBtn = document.getElementById("facilityNext");
  const prevBtn = document.getElementById("facilityPrev");
  const scrollAmount = 460;

  nextBtn?.addEventListener("click", () => slider && (slider.scrollLeft += scrollAmount));
  prevBtn?.addEventListener("click", () => slider && (slider.scrollLeft -= scrollAmount));

  /* ================= UNIT SPEC (punyamu) ================= */
  const preview = document.getElementById("specPreview");
  const previewImg = document.getElementById("previewImg");
  const previewTitle = document.getElementById("previewTitle");

  const specButtons = document.querySelectorAll(".spec-btn");
  const mainImage = document.querySelector(".unit-spec-image img");
  const nextLabel = document.getElementById("nextLabel");
  const prevLabel = document.getElementById("prevLabel");

  const specNextBtn = document.getElementById("specNextBtn");
  const specPrevBtn = document.getElementById("specPrevBtn");

  const specData = Array.from(specButtons).map(btn => ({
    title: btn.dataset.title || "-",
    img: btn.dataset.img || ""
  }));

  let currentIndex = 0;

  function updateSpecView(i) {
    if (!specData.length) return;
    currentIndex = (i + specData.length) % specData.length;

    if (specData[currentIndex].img && mainImage) {
      mainImage.src = specData[currentIndex].img;
    }
    if (nextLabel) nextLabel.textContent = specData[(currentIndex + 1) % specData.length].title;
    if (prevLabel) prevLabel.textContent = specData[(currentIndex - 1 + specData.length) % specData.length].title;
  }

  specButtons.forEach((btn, i) => {
    btn.addEventListener("mouseenter", () => {
      if (!preview || !previewImg || !previewTitle) return;
      previewImg.src = specData[i].img;
      previewTitle.textContent = specData[i].title;
      preview.style.opacity = "1";
      preview.style.transform = "translateY(0)";
    });

    btn.addEventListener("mouseleave", () => {
      if (!preview) return;
      preview.style.opacity = "0";
      preview.style.transform = "translateY(10px)";
    });

    btn.addEventListener("click", () => updateSpecView(i));
  });

  specNextBtn?.addEventListener("click", () => updateSpecView(currentIndex + 1));
  specPrevBtn?.addEventListener("click", () => updateSpecView(currentIndex - 1));
  if (specData.length) updateSpecView(0);

  /* =======================================
     NAVBAR + PROFILE + PROPERTI DROPDOWN 
  ========================================== */
  document.addEventListener("DOMContentLoaded", () => {

    // ===== scroll effect
    const navbar = document.getElementById("navbar");
    window.addEventListener("scroll", () => {
      if (!navbar) return;
      navbar.classList.toggle("scrolled", window.scrollY > 80);
    });

    // ===== mobile menu toggle
    const navToggle = document.getElementById("navToggle");
    const navMobile = document.getElementById("navMobile");

    if (navToggle && navMobile) {
      navToggle.addEventListener("click", (e) => {
        e.stopPropagation();
        navMobile.classList.toggle("show");
      });

      navMobile.addEventListener("click", (e) => e.stopPropagation());

      document.addEventListener("click", () => {
        navMobile.classList.remove("show");
      });

      // kalau klik link biasa di mobile, menu nutup
      navMobile.querySelectorAll("a").forEach(a => {
        a.addEventListener("click", () => navMobile.classList.remove("show"));
      });
    }

    // ===== desktop properti dropdown
    const propBtn = document.getElementById("propBtn");
    const propMenu = document.getElementById("propMenu");

    if (propBtn && propMenu) {
      propBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        propMenu.classList.toggle("show");
      });

      propMenu.addEventListener("click", (e) => e.stopPropagation());

      document.addEventListener("click", () => propMenu.classList.remove("show"));
    }

    // ===== mobile properti dropdown
    const mPropBtn = document.getElementById("mPropBtn");
    const mPropMenu = document.getElementById("mPropMenu");

    if (mPropBtn && mPropMenu) {
      mPropBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        mPropMenu.classList.toggle("show");
      });

      // biar setelah klik item properti, menu mobile nutup juga
      mPropMenu.querySelectorAll("a").forEach(a => {
        a.addEventListener("click", () => navMobile?.classList.remove("show"));
      });
    }

    // ===== profile dropdown + edit
    const profileBtn = document.getElementById("profileBtn");
    const profileMenu = document.getElementById("profileMenu");
    const btnEdit = document.getElementById("btnEditProfile");
    const formEdit = document.getElementById("editProfileForm");
    const btnCancel = document.getElementById("btnCancelEdit");

    if (profileBtn && profileMenu) {
      profileBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        profileMenu.classList.toggle("show");
      });

      profileMenu.addEventListener("click", (e) => e.stopPropagation());

      document.addEventListener("click", () => {
        profileMenu.classList.remove("show");
        formEdit?.classList.remove("show");
      });
    }

    btnEdit?.addEventListener("click", () => formEdit?.classList.toggle("show"));

    btnCancel?.addEventListener("click", () => {
      formEdit?.classList.remove("show");
      const pw = formEdit?.querySelector('input[name="new_password"]');
      const cpw = formEdit?.querySelector('input[name="confirm_password"]');
      if (pw) pw.value = "";
      if (cpw) cpw.value = "";
    });

    // ===== login button
    const btnLogin = document.getElementById("btnLogin");
    btnLogin?.addEventListener("click", () => window.location.href = "regislogin.php");
    // ==== tombol browse catalog
    const btnBrowseCatalog = document.getElementById("btnBrowseCatalog");
        btnBrowseCatalog?.addEventListener("click", () => {
        window.location.href = "HOMEPAGEFIX.php#catalog";
    });
  });
</script>
</body>
<?php if (isset($_GET['profile'])): ?>
<?php
  $msg = ($_GET['profile'] === 'success')
    ? 'Profil berhasil diperbarui!'
    : 'Gagal update profil. Cek username/password kamu.';
?>
<script>
  alert(<?= json_encode($msg) ?>);
  const url = new URL(window.location.href);
  url.searchParams.delete('profile');
  history.replaceState({}, '', url);
</script>
<?php endif; ?>
</html>