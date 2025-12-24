<?php
include "database.php";
session_start();

$query = "SELECT id,type,cover,harga,status,nama,lokasi,jumlah_unit,kategori,kode FROM property ORDER BY id DESC";
$result = $koneksi->query($query);
$properties = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$navProps = $properties;
// urutkan: RUMAH dulu, lalu APARTEMENT (dan id terbaru dulu)
usort($navProps, function($a, $b){
    $ta = ($a['type'] === 'RUMAH') ? 0 : 1;
    $tb = ($b['type'] === 'RUMAH') ? 0 : 1;
    if ($ta !== $tb) return $ta <=> $tb;
    return ((int)$b['id']) <=> ((int)$a['id']);
});

function rupiah($angka) {
    return 'Rp' . number_format((float)$angka, 0, ',', '.');
}

function statusUIfromType($type) {
  return ($type === 'RUMAH') ? 'Dijual' : 'Disewa';
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "survey_submit") {

    if (!isset($_SESSION["user_id"])) {
        header("Location: regislogin.php?redirect=" . urlencode($_SERVER["REQUEST_URI"]));
        exit;
    }

    $full_name = trim($_POST["full_name"] ?? "");
    $phone     = preg_replace('/[^0-9+]/', '', trim($_POST["phone"] ?? ""));
    $pesan     = trim($_POST["pesan"] ?? "");

    if ($full_name !== "" && $phone !== "" && $pesan !== "") {
        $stmt = $koneksi->prepare("INSERT INTO survey (full_name, phone, pesan) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $full_name, $phone, $pesan);

        header("Location: ".$_SERVER["PHP_SELF"].($stmt->execute() ? "?survey=success" : "?survey=fail"));
        exit;
    }

    header("Location: ".$_SERVER["PHP_SELF"]."?survey=fail");
    exit;
}

$userEmail = null;
$userName  = null;

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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.4.0/fonts/remixicon.css" rel="stylesheet">

    <!-- SWIPER CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Swiper/8.4.5/swiper-bundle.min.css">

    <title>PropertyKu</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Poppins", sans-serif;
        }

        .header {
            width: 100%;
            height: 100vh;
            background: linear-gradient(to bottom, #637A92, #C8D6E4);
            position: relative;
            overflow: hidden;
        }

        /* BIG TEXT */
        .big-text {
            font-size: 170px;
            font-weight: 700;
            line-height: 120px;
            color: white;
            position: absolute;
            top: 110px;
            left: 60px;
            letter-spacing: -4px;
            z-index: 1;
        }

        .big-text span:nth-child(2) {
            margin-left: 720px;
            position: relative;
            top: -40px;
        }

        /* IMAGE */
        .house-img {
            width: 100%;
            position: absolute;
            bottom: 0;
            z-index: 2;
        }

        /* LEFT TEXT CONTENT */
        .left-content {
            position: absolute;
            left: 60px;
            bottom: 120px;
            z-index: 3;
            color: white;
        }

        .tagline {
            font-size: 32px;
            font-weight: 400;
            margin-top: 10px;
        }

        .view-btn {
            margin-top: 25px;
            padding: 12px 30px;
            background: white;
            color: black;
            border-radius: 40px;
            border: none;
            font-size: 14px;
            font-weight: 600;
        }

        .info {
            margin-top: 25px;
            font-size: 12px;
            letter-spacing: 1px;
            line-height: 16px;
        }

        /* REVIEW BOX */
        .review-box {
            position: absolute;
            right: 60px;
            bottom: 120px;
            background: white;
            padding: 25px;
            border-radius: 25px;
            width: 220px;
            z-index: 4;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .review-box h3 {
            font-size: 36px;
            margin-bottom: 5px;
        }

        .review-box p {
            font-size: 13px;
            color: #444;
            margin-bottom: 10px;
        }

        .review-box .avatars img {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            margin-right: -8px;
            border: 2px solid white;
        }

        /* ANIMATION BASE */
        .animate-up {
            opacity: 0;
            transform: translateY(80px);
            transition: 1.1s ease;
        }

        .animate-up.show {
            opacity: 1;
            transform: translateY(0);
        }

        .animate-down {
            opacity: 0;
            transform: translateY(-80px);
            transition: 1.1s ease;
        }

        .animate-down.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* FROM LEFT */
        .animate-left {
            opacity: 0;
            transform: translateX(-120px);
            transition: 1.1s ease;
        }

        .animate-left.show {
            opacity: 1;
            transform: translateX(0);
        }

        /* FROM RIGHT */
        .animate-right {
            opacity: 0;
            transform: translateX(120px);
            transition: 1.1s ease;
        }

        .animate-right.show {
            opacity: 1;
            transform: translateX(0);
        }

        /* SWITCH BUTTON WRAPPER */
        .mode-switch {
            position: absolute;
            bottom: 40px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            background: rgba(255, 255, 255, 0.25);
            padding: 8px;
            border-radius: 50px;
            backdrop-filter: blur(10px);
            z-index: 10;
        }

        /* BUTTON */
        .switch-btn {
            padding: 12px 26px;
            border-radius: 40px;
            background: transparent;
            border: none;
            font-size: 15px;
            color: white;
            cursor: pointer;
            transition: 0.3s;
        }

        /* ACTIVE BUTTON */
        .switch-btn.active {
            background: white;
            color: black;
            font-weight: 600;
        }

        /* TRANSITION FOR BACKGROUND */
        .header {
            transition: background 1s ease;
        }

        /* IMAGE FADE TRANSITION */
        .house-img {
            transition: opacity 0.8s ease;
        }

        /* IMAGE SLIDE ANIMATION */
        .house-img {
            opacity: 0;
            transform: translateY(80px);
            transition: opacity 0.8s ease, transform 0.8s ease;
        }

        .house-img.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* RESET TITLE WHEN SWITCHING */
        .big-text.switch-animate {
            opacity: 0;
            transform: translateY(-120px);
            transition: 0.9s ease;
        }

        .big-text.switch-animate.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* MAIN LAYOUT */
        /* ===== FIX TRUSTED SECTION LAYOUT ===== */
        .trusted-container {
            display: flex;
            gap: 60px;
            align-items: flex-start;
            max-width: 1200px;
            margin: 0 auto;
            padding: 100px 40px;
        }

        /* LEFT SIDE */
        .left {
            flex: 1;
        }

        .title {
            font-size: 48px;
            font-weight: 700;
            color: #222;
            line-height: 1.2;
        }

        .title span {
            color: #0F6A59;
        }

        .desc {
            margin-top: 15px;
            font-size: 14px;
            color: #6a6a6a;
            max-width: 350px;
        }

        .stats {
            margin-top: 50px;
        }

        .avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            margin-right: -12px;
            border: 2px solid #fff;
        }

        .numbers {
            display: flex;
            margin-top: 20px;
            gap: 60px;
        }

        .numbers span {
            font-size: 22px;
            font-weight: 600;
            color: #111;
        }

        .numbers p {
            font-size: 13px;
            color: #6a6a6a;
        }


        /* CARD STYLE */
        .card {
            display: flex;
            align-items: center;
            background: #ffffff;
            border: 1px solid #efefef;
            padding: 22px;
            border-radius: 20px;
            margin-bottom: 18px;
            position: relative;
        }

        .icon-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #0F6A59;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 18px;
        }

        .icon-circle i {
            font-size: 22px;
            color: #fff;
        }

        .card-text h3 {
            font-size: 18px;
            font-weight: 600;
            color: #222;
        }

        .card-text p {
            font-size: 13px;
            color: #6a6a6a;
            margin-top: 6px;
            max-width: 350px;
        }

        .arrow {
            position: absolute;
            right: 22px;
            font-size: 22px;
            color: #999;
        }

        .right {
            flex: 1;
            height: 360px;
            /* FIX Tinggi area kanan */
            overflow-y: hidden;
            /* supaya scroll hanya di scroll-box */
            padding-left: 40px;
            position: relative;
        }

        .scroll-box {
            height: 100%;
            /* ikut tinggi right */
            overflow-y: auto;
            /* SCROLL muncul di sini */
            padding-right: 12px;
        }

        /* Scroll bar tipis seperti contoh */
        .scroll-box::-webkit-scrollbar {
            width: 4px;
        }

        .scroll-box::-webkit-scrollbar-thumb {
            background: #0F6A59;
            /* warna hitam seperti contoh */
            border-radius: 20px;
        }

        .scroll-box::-webkit-scrollbar-track {
            background: transparent;
        }

        /* ==========================
   ABOUT SECTION
========================== */
        .about-section {
            padding: 175px 60px;
            background: white;
        }

        .about-container {
            display: flex;
            gap: 60px;
            align-items: center;
            max-width: 1200px;
            margin: auto;
        }

        .about-image {
            width: 1300px;
            /* lebar fix agar rapi (boleh kamu ubah) */
            height: 450px;
            /* tinggi fix – ini yg bikin konsisten */
            overflow: hidden;
            /* biar object-fit crop rapi */
            border-radius: 25px;
        }

        .about-image img {
            width: 100%;
            height: 100%;
            /* wajib! */
            object-fit: cover;
            /* biar zoom & center jadi rapi */
            border-radius: 25px;
            transition: opacity 0.5s ease;
        }

        .about-content h2 {
            font-size: 42px;
            font-weight: 700;
            color: #233042;
            /* navy charcoal */
            margin-bottom: 20px;
            letter-spacing: -0.5px;
        }

        .about-content p {
            color: #6b7280;
            /* grey soft */
            font-size: 15px;
            line-height: 26px;
            margin-bottom: 28px;
        }

        .title-soft {
            color: #53719c;
            /* abu ke arah navy */
            font-weight: 600;
            /* sedikit lebih ringan */
        }

        /* LIST FEATURES */
        .about-features {
            list-style: none;
        }

        .about-features li {
            display: flex;
            align-items: center;
            gap: 12px;
            background: white;
            padding: 14px 18px;
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
            margin-bottom: 14px;
            font-size: 15px;
            font-weight: 500;
            color: #374151;
        }

        .check-icon {
            background: #1f2937;
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ===================== SLIDER BARU ===================== */

        .gallery-section {
            position: relative;
            width: 100%;
            padding: 80px 0 35px;
            background: transparent;
        }

        .page-title {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }

        .page-title h1 {
            font-size: 38px;
            font-weight: 700;
        }

        .swiper {
            width: 100%;
            padding: 60px 0 120px;
        }

        .swiper-slide {
            width: 500px;
            height: 420px;
            border-radius: 16px;
            overflow: hidden;
            position: relative;
        }

        .swiper-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .detail {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 24px;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.6), transparent);
            color: white;
        }

        /* BUTTONS */
        .swiper-button-prev,
        .swiper-button-next {
            width: 55px;
            height: 55px;
            background: rgba(255, 255, 255, 0.85);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .swiper-button-prev::after,
        .swiper-button-next::after {
            content: "";
        }

        .swiper-button-prev {
            left: -100px;
        }

        .swiper-button-next {
            right: -100px;
        }

        .swiper-button-prev .bx,
        .swiper-button-next .bx {
            font-size: 28px;
            color: black;
        }

        .bg-dynamic {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            z-index: -2;
        }

        .bg-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.30);
            z-index: -1;
        }

        /* Pagination dots custom */
        .swiper-pagination-bullet {
            background: rgba(255, 255, 255, 0.35) !important;
            /* dot tidak aktif (abu putih) */
            opacity: 1;
        }

        .swiper-pagination-bullet-active {
            background: #ffffff !important;
            /* dot aktif PUTIH */
        }

        /* =============== INTERIOR SHOWCASE SECTION =============== */

        .interior-section {
            background: #ffffff;
            padding: 120px 30px;
            /* lebih kecil dan elegan */
            display: flex;
            justify-content: center;
        }

        .interior-container {
            width: 100%;
            max-width: 1100px;
            /* <== BATAS MAKS BIAR TIDAK MELEBAR */
            margin: auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px 35px;
        }

        /* LEFT TEXT BLOCK */
        .interior-text {
            color: #1a1a1a;
            padding-right: 20px;
        }

        .interior-text h1 {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 18px;
        }

        .interior-text p {
            font-size: 16px;
            line-height: 26px;
            color: #555;
            margin-bottom: 25px;
        }

        .interior-btn {
            padding: 12px 30px;
            border-radius: 40px;
            border: 1px solid #111;
            color: #111;
            background: transparent;
            cursor: pointer;
            transition: .3s;
        }

        .interior-btn:hover {
            background: #111;
            color: white;
        }

        /* IMAGES */
        .interior-img {
            width: 100%;
            height: 350px;
            border-radius: 15px;
            overflow: hidden;
        }

        .interior-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: .4s ease;
        }

        .interior-img img:hover {
            transform: scale(1.06);
        }

        /* ===================== RECENT PHOTOS SECTION ===================== */
        /* ===================== RECENT PHOTOS SECTION ===================== */

        .recent-photos {
            background: #cbd4e6;
            padding: 40px;
            border-bottom-left-radius: 25px;
            border-bottom-right-radius: 25px;
            border-top-left-radius: 350px;
            margin-bottom: 60px;
            width: 90%;
            margin-left: 77px;
        }

        /* HEADER */
        .rp-header h2 {
            font-size: 32px;
            font-weight: 700;
            color: #222;
        }

        .rp-header p {
            font-size: 14px;
            color: #666;
            margin-bottom: 25px;
        }

        /* SLIDER WRAPPER */
        .rp-card-slider {
            display: flex;
            gap: 25px;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            padding-bottom: 10px;
        }

        .rp-card-slider::-webkit-scrollbar {
            height: 6px;
        }

        .rp-card-slider::-webkit-scrollbar-thumb {
            background: #cfcfcf;
            border-radius: 10px;
        }

        /* CARD */
        .rp-card {
            min-width: 300px;
            /* ⬅ hanya 3 yang tampil, sisanya geser */
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            scroll-snap-align: start;
            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.08);
        }

        .rp-card img {
            width: 100%;
            height: 190px;
            object-fit: cover;
        }

        .rp-card-body {
            padding: 20px;
        }

        .rp-card-body h3 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .loc {
            font-size: 14px;
            color: #777;
            margin-bottom: 12px;
        }

        .infos {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 18px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .price-row h4 {
            font-size: 20px;
            font-weight: 700;
        }

        .details-btn {
            background: #304159;
            padding: 8px 14px;
            border-radius: 18px;
            font-size: 12px;
            color: white;
            border: none;
        }

        /* DOTS */
        .rp-dots {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin: 18px 0;
        }

        .rp-dots span {
            width: 35px;
            height: 4px;
            background: #d0d0d0;
            border-radius: 4px;
        }

        .rp-dots .active {
            background: #222;
        }

        /* ARROW (kanan) */
        .rp-arrow {
            text-align: right;
            margin-top: -5px;
            margin-bottom: 25px;
        }

        .rp-arrow i {
            font-size: 26px;
            background: #fff;
            padding: 10px;
            border-radius: 50%;
        }

        /* VIDEO */
        .rp-video {
            width: 100%;
            height: 420px;
            /* lebih tinggi biar video vertical tidak rusak */
            border-radius: 20px;
            overflow: hidden;
            position: relative;
            background: #000;
            /* biar rapi waktu video contain */
        }


        .rp-video img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .rp-play {
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 50%;
            position: absolute;
            top: 50%;
            left: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            transform: translate(-50%, -50%);
        }

        .rp-play i {
            font-size: 30px;
        }

        .rp-card-slider {
            overflow-x: auto;
            scrollbar-width: none;
            /* Firefox */
        }

        .rp-card-slider::-webkit-scrollbar {
            display: none;
            /* Chrome, Safari */
        }

        .rp-video video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            /* tidak dicrop lagi, muncul utuh */
            background: #000;
            border-radius: 20px;
        }

        /* NEW SEARCH STYLE — seperti contoh gambar */
        .search-section {
            width: 100%;
            background: #f3f3f3;
            padding: 60px 40px;
            border-radius: 35px;
            margin: 80px auto;
            text-align: center;
        }

        .search-header h2 {
            font-size: 40px;
            font-weight: 700;
            color: #1f2937;
            text-align: center;
            margin-top: 50px;
        }

        .search-header h2 span {
            color: #6b7280;
            /* grey soft like example */
        }

        .search-header p {
            margin-top: 8px;
            font-size: 14px;
            color: #6b7280;
            text-align: center;
        }

        /* FILTER BAR */
        .search-filters {
            margin-top: 35px;
            margin-bottom: 50px;
            display: flex;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .filter-box {
            background: white;
            padding: 12px 18px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            border: 1px solid #ddd;
            cursor: pointer;
        }

        .filter-box i {
            font-size: 16px;
            color: #444;
        }

        .dropdown {
            margin-left: auto;
        }

        .search-btn {
            background: #111;
            color: white;
            padding: 12px 28px;
            border-radius: 30px;
            border: none;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .dropdown-filter {
            position: relative;
        }

        .dropdown-menu {
            position: absolute;
            top: 52px;
            left: 0;
            background: white;
            width: 180px;
            border-radius: 14px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            padding: 10px 0;
            list-style: none;
            display: none;
            z-index: 20;
        }

        .dropdown-menu li {
            padding: 10px 18px;
            cursor: pointer;
            font-size: 14px;
            transition: 0.2s;
        }

        .dropdown-menu li:hover {
            background: #f2f2f2;
        }

        .search-wrapper {
            display: flex;
            align-items: center;
            background: white;
            border: 1px solid #ddd;
            padding: 10px 16px;
            border-radius: 30px;
            transition: 0.3s ease;
            width: 120px;
            /* default kecil */
            overflow: hidden;
        }

        .search-wrapper.active {
            width: 300px;
            /* saat aktif melebar */
        }

        #searchInput {
            flex: 1;
            border: none;
            outline: none;
            font-size: 14px;
            opacity: 0;
            width: 0;
            transition: 0.3s ease;
        }

        .search-wrapper.active #searchInput {
            opacity: 1;
            width: 100%;
        }

        .search-btn {
            background: #000;
            color: white;
            border-radius: 50%;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ===================== MODERN LUXURY INTERIOR ===================== */

        .luxury-interior {
            width: 100%;
            padding: 90px 0 30px;
            background: #ffffff;
            position: relative;
        }

        .li-title {
            position: absolute;
            top: 40px;
            left: 45px;
            font-size: 34px;
            font-weight: 700;
            color: #1f2937;
            line-height: 1.1;
            letter-spacing: -1px;
        }

        .li-title span {
            color: #8395ae;
        }

        .li-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0;
            width: 100%;
            margin-top: 70px;
        }

        /* ITEM STYLE */
        .li-item {
            position: relative;
            height: 520px;
            overflow: hidden;
        }

        /* GAMBAR NORMAL = AGAK GELAP */
        .li-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(65%);
            /* BUAT AGAK GELAP */
            transition: .5s ease;
        }

        /* SAAT DIHOVER = CERAH & ZOOM ELEGAN */
        .li-item:hover img {
            filter: brightness(100%);
            transform: scale(1.05);
        }

        /* TEKS DI BAWAH */
        .li-text {
            position: absolute;
            bottom: 25px;
            left: 25px;
            color: white;
            text-shadow: 0 3px 10px rgba(0, 0, 0, 0.6);
        }

        .li-text h3 {
            font-size: 22px;
            font-weight: 700;
        }

        .li-text p {
            font-size: 14px;
            opacity: 0.85;
        }

        /* ============================
   SURVEY FORM OVERLAY SECTION
=============================== */

        .survey-section {
            position: relative;
            width: 100%;
            height: 800px;
            margin-top: 80px;
            overflow: hidden;
        }

        /* Background image penuh landscape */
        .survey-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('img/pexels-heyho-11701123.jpg') center/cover no-repeat;
            filter: brightness(75%);
        }

        /* PANEL TRANSPARAN BESAR DI KANAN (PERSIS GAMBAR) */
        .survey-overlay {
            position: absolute;
            top: 0;
            right: 0;
            width: 42%;
            /* ukuran panel besar sesuai gambar */
            height: 100%;
            background: rgba(0, 0, 0, 0.50);
            /* transparansi hitam */
            backdrop-filter: blur(4px);
            padding: 60px 45px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        /* Title */
        .survey-overlay h2 {
            font-size: 26px;
            font-weight: 600;
            margin-bottom: 25px;
        }

        /* Label */
        .survey-overlay label {
            font-size: 14px;
            margin-bottom: 6px;
            opacity: 0.85;
        }

        /* Inputs */
        .survey-overlay input,
        .survey-overlay textarea {
            width: 100%;
            padding: 12px;
            margin-bottom: 18px;
            border-radius: 8px;
            border: none;
            outline: none;
            background: rgba(255, 255, 255, 0.18);
            color: white;
        }

        /* Placeholder */
        .survey-overlay input::placeholder,
        .survey-overlay textarea::placeholder {
            color: rgba(255, 255, 255, 0.75);
        }

        /* Textarea */
        .survey-overlay textarea {
            height: 90px;
            resize: none;
        }

        /* Button */
        .survey-overlay button {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            background: white;
            border: none;
            font-weight: 600;
            color: black;
            cursor: pointer;
            margin-top: 10px;
            transition: .3s;
        }

        .survey-overlay button:hover {
            background: #dcdcdc;
        }

        /* ============================
   CLEAN PROMO SHOWCASE SECTION
=============================== */

        .promo-showcase {
            width: 100%;
            padding: 90px 20px;
            background: white;
            /* soft grey premium */
            display: flex;
            justify-content: center;
            margin-top: 70px;

        }

        .promo-inner {
            width: 100%;
            max-width: 1200px;
            display: grid;
            grid-template-columns: 1fr 1.2fr 1fr;
            align-items: center;
            gap: 40px;
        }


        /* IMAGE BLOCK */
        .promo-img img {
            width: 100%;
            height: 300px;
            object-fit: cover;
            border-radius: 14px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .promo-stat {
            margin-top: 15px;
            text-align: center;
        }

        .promo-stat h3 {
            font-size: 32px;
            font-weight: 700;
            color: #1f2937;
        }

        .promo-stat p {
            font-size: 13px;
            color: #777;
        }


        /* CENTER TEXT */
        .promo-center {
            text-align: center;
        }

        .promo-sub {
            font-size: 13px;
            letter-spacing: 2px;
            color: #777;
        }

        .promo-center h1 {
            margin-top: 10px;
            font-size: 38px;
            line-height: 1.2;
            font-weight: 700;
            color: #324155;
        }

        .promo-center p {
            margin-top: 14px;
            font-size: 15px;
            color: #555;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        /* BUTTON */
        .promo-btn {
            margin-top: 22px;
            padding: 12px 28px;
            background: #1f2937;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: 0.25s ease;
        }

        .promo-btn:hover {
            background: #333;
        }

        /* ============================
   MEDIA PARTNER PROMO CARDS
=============================== */

        .partner-section {
            background: white;
            display: flex;
            justify-content: center;
            margin-top: 100px;
        }

        .partner-container {
            display: flex;
            gap: 30px;
            max-width: 1150px;
            width: 100%;
        }

        .partner-card {
            flex: 1;
            display: flex;
            align-items: center;
            background: #aec1db;
            border-radius: 16px;
            overflow: hidden;
            padding: 20px;
            height: 220px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
            transition: .3s;
        }

        .partner-card:hover {
            transform: translateY(-6px);
        }

        /* IMAGE */
        .partner-card img {
            width: 45%;
            height: 100%;
            object-fit: cover;
            border-radius: 12px;
        }

        /* TEXT CONTENT */
        .partner-info {
            padding-left: 22px;
            color: white;
            flex: 1;
        }

        .partner-info h4 {
            font-size: 16px;
            opacity: .85;
        }

        .partner-info h4,
        .partner-info h1 {
            color: #ffffff;
            text-shadow: 0 2px 8px rgba(31, 41, 55, 0.45);
        }

        .partner-info .outline {
            font-size: 22px;
            font-weight: 700;
            color: transparent;
            -webkit-text-stroke: 1px #1f2937;
            /* gold outline */
        }

        .partner-info h1 {
            font-size: 36px;
            margin: 5px 0;
            font-weight: 700;
        }

        /* BUTTON */
        .partner-btn {
            display: inline-block;
            margin-top: 6px;
            font-size: 14px;
            color: #1f2937;
            /* gold accent */
            text-decoration: none;
            font-weight: 600;
        }

        .partner-btn:hover {
            text-decoration: underline;
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

        /* ===== NAVBAR ===== */
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

            background: rgba(29, 36, 51, 0.55);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);

            z-index: 999;
            transition: all 0.35s ease;
        }

        /* Saat scroll */
        .navbar.scrolled {
            top: 10px;
            background: #1d2433;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
        }

        /* LEFT */
        .nav-left {
            font-size: 18px;
            font-weight: 600;
            color: #ffffff;
            letter-spacing: 0.5px;
        }

        /* CENTER MENU */
        .nav-center{
            display:flex;
            align-items:center;
            gap:34px;
            position: static;    
            left: auto;
            transform: none;
        }

        .nav-center a{
            text-decoration:none;
            font-size:14px;
            color:#cfd3dc;
            position:relative;
            transition:.3s;
            background:transparent;
            border:none;
            cursor:pointer;
            line-height:1;
            display:inline-flex;
            align-items:center;
            gap:6px;
        }

        .nav-center a:hover,
            .prop-btn:hover{
            color:#fff;
        }

        .nav-center a.active {
            color: #ffffff;
            font-weight: 500;
        }

        .prop-wrap{
            position: relative;
            display: inline-flex;
            align-items: center;
        }

        .prop-menu .prop-item{
            display: flex !important;   
            width: 100%;
            padding:12px 14px; 
        }

        /* RIGHT BUTTON */
        .login-btn {
            padding: 10px 26px;
            border-radius: 40px;
            border: none;
            background: #ffffff;
            color: #1d2433;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }

        .login-btn:hover {
            background: #e5e8ef;
        }

        /* ==============================
   DARK MODE — SUPER LUXURY
   NAVY + SILVER
============================== */
        body.dark {
            background: #020617;
            color: #e5e7eb;
        }

        /* NAVBAR */
        body.dark .navbar {
            background: rgba(2, 6, 23, 0.85);
            box-shadow: 0 12px 40px rgba(0, 0, 0, .65);
        }

        /* HEADER */
        body.dark .header {
            background: linear-gradient(to bottom, #020617, #0b1220);
        }

        /* SECTIONS */
        body.dark .about-section,
        body.dark .interior-section,
        body.dark .promo-showcase,
        body.dark .partner-section,
        body.dark .search-section,
        body.dark .luxury-interior {
            background: #020617;
        }

        /* TITLES */
        body.dark h1,
        body.dark h2,
        body.dark h3,
        body.dark h4 {
            color: #e5e7eb;
        }

        /* PARAGRAPH */
        body.dark p,
        body.dark span {
            color: #9ca3af;
        }

        /* CARDS */
        body.dark .card,
        body.dark .rp-card,
        body.dark .filter-box {
            background: linear-gradient(180deg, #0f172a, #020617);
        }

        /* BUTTON SILVER */
        body.dark .login-btn,
        body.dark .details-btn,
        body.dark .promo-btn,
        body.dark .interior-btn {
            background: linear-gradient(135deg, #cbd5e1, #94a3b8);
            color: #020617;
            font-weight: 600;
            border: none;
        }

        /* FOOTER */
        body.dark .main-footer {
            background: #020617;
        }

        body.dark #sonder {
            background: linear-gradient(90deg,
                    #1e293b 0%,
                    /* navy tua (kiri) */
                    #475569 35%,
                    /* abu navy */
                    #94a3b8 70%,
                    /* silver */
                    #e5e7eb 100%
                    /* silver muda (kanan) */
                );
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        body.dark .search-header h2 {
            color: #1e293b;
            /* silver soft */
        }

        body.dark .search-header h2 span {
            background: linear-gradient(90deg,
                    #64748b,
                    #94a3b8);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        body.dark .search-header p {
            color: #9ca3af;
            /* silver abu tipis */
        }

        .favorite-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, .9);
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            z-index: 10;
        }

        .favorite-btn i {
            font-size: 20px;
            color: #1d2433;
        }

        .favorite-btn.active i {
            color: #ef4444;
        }

        .rp-card {
            min-width: 300px;
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            scroll-snap-align: start;
            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.08);

            position: relative;
            /* ⬅️ INI WAJIB */
        }

        /* ===========================
   RESPONSIVE — TABLET
=========================== */
        @media (max-width: 992px) {

            /* NAVBAR */
            .nav-center {
                gap: 20px;
            }

            .nav-center a {
                font-size: 13px;
            }

            /* HERO TEXT */
            .big-text {
                font-size: 110px;
                line-height: 90px;
                left: 30px;
            }

            .big-text span:nth-child(2) {
                margin-left: 360px;
                top: -20px;
            }

            /* HERO IMAGE */
            .house-img {
                max-height: 65vh;
                object-fit: contain;
            }

            /* ABOUT */
            .about-container {
                flex-direction: column;
            }

            .about-image {
                width: 100%;
                height: 350px;
            }

            /* INTERIOR GRID */
            .interior-container {
                grid-template-columns: 1fr;
            }

            /* LUXURY INTERIOR GRID */
            .li-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            /* SURVEY */
            .survey-overlay {
                width: 55%;
            }
        }

        /* ===========================
   RESPONSIVE — MOBILE
=========================== */
        @media (max-width: 600px) {

            /* NAVBAR */
            .navbar {
                padding: 0 18px;
            }

            .nav-center {
                display: none;
            }

            /* HERO */
            .header {
                height: auto;
                padding-bottom: 60px;
            }

            .big-text {
                font-size: 64px;
                line-height: 58px;
                left: 20px;
                top: 120px;
            }

            .big-text span:nth-child(2) {
                margin-left: 0;
                display: block;
                top: 0;
            }

            .house-img {
                position: relative;
                width: 100%;
                margin-top: 60px;
            }

            .left-content {
                position: relative;
                left: 20px;
                bottom: auto;
                margin-top: 30px;
            }

            /* ABOUT */
            .about-section {
                padding: 80px 20px;
            }

            .about-image {
                height: 260px;
            }

            /* GALLERY SLIDER */
            .swiper-slide {
                width: 300px;
                height: 360px;
            }

            .swiper-button-prev,
            .swiper-button-next {
                display: none;
            }

            /* RECENT PHOTOS */
            .recent-photos {
                width: 100%;
                margin-left: 0;
                border-top-left-radius: 120px;
                padding: 30px 20px;
            }

            .rp-card {
                min-width: 260px;
            }

            /* SEARCH FILTER */
            .search-filters {
                gap: 10px;
            }

            .search-wrapper.active {
                width: 100%;
            }

            /* LUXURY INTERIOR */
            .li-title {
                position: relative;
                left: 0;
                text-align: center;
                margin-bottom: 30px;
            }

            .li-grid {
                grid-template-columns: 1fr;
            }

            .li-item {
                height: 360px;
            }

            /* PROMO */
            .promo-inner {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            /* SURVEY */
            .survey-section {
                height: auto;
            }

            .survey-overlay {
                position: relative;
                width: 100%;
                height: auto;
                padding: 40px 25px;
            }

            .survey-bg {
                display: none;
            }

            /* PARTNER */
            .partner-container {
                flex-direction: column;
                padding: 0 20px;
            }

            /* FOOTER */
            .footer-container {
                grid-template-columns: 1fr;
                text-align: left;
            }

            .details-btn {
                padding: 6px 12px;
                font-size: 11px;
            }

        }

        @media (max-width: 600px) {

            /* ===== HERO WRAPPER ===== */
            .header {
                height: auto;
                padding-bottom: 0;
                overflow: hidden;
            }

            /* ===== SONDER HOUSE TEXT ===== */
            .big-text {
                position: relative;
                top: 0;
                left: 0;
                margin-top: 90px;
                padding: 0 20px;

                font-size: 56px;
                line-height: 1.05;
                letter-spacing: -2px;
                z-index: 2;
            }

            .big-text span:nth-child(2) {
                margin-left: 0;
                top: 0;
                display: block;
            }

            /* ===== HOUSE IMAGE ===== */
            .house-img {
                position: relative;
                width: 100%;
                max-height: 260px;
                /* FIX tinggi gambar */
                object-fit: contain;
                /* FIX tidak kepotong */
                margin-top: 20px;
                z-index: 1;
            }

            /* ===== INFO TEXT (PRIVATE HOMES...) ===== */
            .left-content {
                position: relative;
                left: 0;
                bottom: 0;
                margin-top: 18px;
                padding: 0 20px;
                z-index: 3;
            }

            .left-content .info {
                font-size: 12px;
                line-height: 18px;
                opacity: .9;
            }

            /* ===== MORNING / NIGHT SWITCH ===== */
            .mode-switch {
                position: relative;
                top: auto;
                bottom: auto;
                left: 0;
                transform: none;

                margin: 24px auto 0;
                width: fit-content;
                padding: 6px;
                gap: 6px;
            }

            .switch-btn {
                padding: 10px 22px;
                font-size: 14px;
            }

        }

        @media (max-width: 600px) {

            .mode-switch {
                order: 99;
                margin-top: 30px;
            }

            .header {
                display: flex;
                flex-direction: column;
            }
        }

        /* tombol hamburger (default disembunyikan di desktop) */
        .nav-toggle {
            display: none;
            background: transparent;
            border: none;
            color: #fff;
            font-size: 22px;
            cursor: pointer;
        }

        /* dropdown mobile (default hidden) */
        .nav-mobile {
            display: none;
            position: absolute;
            top: 76px;
            /* turun dikit dari navbar */
            left: 50%;
            transform: translateX(-50%);
            width: 92%;
            padding: 12px;
            border-radius: 18px;
            background: rgba(29, 36, 51, 0.85);
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
            background: rgba(255, 255, 255, 0.08);
        }

        /* aktif (dibuka) */
        .nav-mobile.show {
            display: block;
        }

        /* khusus mobile */
        @media (max-width: 600px) {
            .nav-toggle {
                display: block;
            }

            /* .nav-center kamu sudah display:none di file kamu :contentReference[oaicite:1]{index=1} */
        }

        /* ARROW WRAPPER */
        .rp-arrows {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 18px;
            margin-bottom: 23px;
        }

        /* BUTTON STYLE */
        .rp-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: none;
            background: white;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: .3s ease;
        }

        .rp-btn i {
            font-size: 22px;
            color: #1d2433;
        }

        .rp-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
        }
        .profile-wrap{position:relative}
        .profile-btn{
        width:44px;height:44px;border-radius:50%;
        border:none;overflow:hidden;cursor:pointer;
        background:rgba(255,255,255,.2);color:#fff;
        }
        .profile-btn img{width:100%;height:100%;object-fit:cover}
        .profile-btn span{font-weight:700}

        .profile-menu{
        position:absolute;right:0;top:54px;width:230px;
        background:rgba(29,36,51,.95);
        backdrop-filter:blur(12px);
        border-radius:14px;padding:12px;
        display:none;z-index:9999;
        }
        .profile-menu.show{display:block}
        .profile-email{font-size:13px;color:#cfd3dc;margin-bottom:8px}
        .profile-menu input{width:100%;font-size:12px;margin-bottom:6px}
        .profile-menu button{
        width:100%;padding:6px;border-radius:8px;border:none;
        font-size:12px;cursor:pointer
        }
        .profile-email,
        .profile-name{
        text-align:center;
        }

        .profile-email{
        font-size:13px;
        color:#cfd3dc;
        margin-bottom:4px;
        }

        .profile-name{
        font-size:12px;
        color:#ffffff;  
        font-weight:600;
        margin-bottom:10px;
        opacity:.95;
        }
        .profile-logout{
        display:block;margin-top:8px;
        text-align:center;color:#ffb4b4;text-decoration:none
        }
        .profile-edit-btn{
        width:100%;
        padding:10px;
        border-radius:10px;
        border:none;
        background:rgba(255,255,255,.15);
        color:#fff;
        font-weight:600;
        cursor:pointer;
        margin-bottom:10px;
        }

        .edit-profile-form{ display:none; }
        .edit-profile-form.show{ display:block; }

        .profile-menu input{
        width:100%;
        padding:10px;
        border-radius:10px;
        border:1px solid rgba(255,255,255,.15);
        background:rgba(255,255,255,.08);
        color:#fff;
        outline:none;
        margin-bottom:8px;
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
        .prop-wrap{ position: relative; display:inline-flex; align-items:center; }
        .prop-btn{
        background: transparent;
        border: none;
        color:#cfd3dc;
        font-size:14px;
        cursor:pointer;
        padding:0;                 
        display:inline-flex;
        align-items:center;
        gap:6px;
        transition:.3s;
        line-height:1;
        }
        .prop-btn:hover{ color:#fff; background: rgba(255,255,255,.08); }
        .prop-menu{
        position:absolute;
        left: 0;
        top: 54px;
        width: 280px;
        max-height: 340px;
        overflow:auto;
        background:rgba(29, 36, 51, 0.85);
        backdrop-filter: blur(12px);
        border-radius: 14px;
        padding: 10px;
        display:none;
        z-index: 9999;
        }

        .prop-menu.show{ display:block; }

        .prop-item{
        display:flex;
        align-items:center;
        gap:10px;
        padding: 10px 12px;
        border-radius: 12px;
        text-decoration:none;
        color:#cfd3dc;
        font-size: 13px;
        }

        .prop-item:hover{ background: rgba(255,255,255,.08); color:#fff; }
        /* LEFT GROUP (hamburger + logo) */
        .nav-left-group{
        display:flex;
        align-items:center;
        gap:12px;
        }

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

        /* Samakan prop-menu background (desktop) */
        .prop-menu{
        background: rgba(29,36,51,.95);
        }
    </style>
</head>

<body>
    <!-- ===================== HEADER ===================== -->
    <div class="header">
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

        <!-- ===================== HERO SECTION ===================== -->
        <div class="mode-switch">
            <button class="switch-btn active" id="btnMorning">Morning</button>
            <button class="switch-btn" id="btnNight">Night</button>
        </div>

        <!-- TEXT BACK -->
        <h1 class="big-text animate-down">
            <span id="sonder">MODERN</span>
            <span id="house">PLACE</span>
        </h1>

        <!-- IMAGE -->
        <img src="img/Anggun_Halisa_landscape_version_4a75b606-8d0f-443c-82e4-b84f3f7eca72__1_-removebg-preview (2).png"
            class="house-img animate-up">

        <!-- LEFT TEXT -->
        <div class="left-content animate-left">
            <p class="info">
                PRIVATE HOMES<br>
                SURROUNDED BY NATURE.<br>
                20 MINUTES FROM<br>
                THE CITY CENTER.
            </p>
        </div>

    </div>

    <section class="about-section" id="trusted">
        <div class="about-container">

            <!-- LEFT IMAGE -->
            <div class="about-image">
                <img id="autoImage" src="https://images.unsplash.com/photo-1586105251261-72a756497a11" alt="">
            </div>

            <!-- RIGHT TEXT CONTENT -->
            <div class="about-content">
                <h2>
                    Find Trusted Properties<br>
                    <span class="title-soft">With Confidence</span>
                </h2>

                <p>
                    PropertyKu dibangun untuk memudahkan proses pencarian rumah dan apartemen tanpa ribet.
                    Kami menyediakan daftar properti yang sudah melalui verifikasi sehingga
                    pengguna bisa mengambil keputusan dengan rasa aman.
                </p>

                <ul class="about-features">
                    <li>
                        <span class="check-icon">✔</span>
                        Transparent price & no hidden fees
                    </li>

                    <li>
                        <span class="check-icon">✔</span>
                        Verified and curated listings
                    </li>

                    <li>
                        <span class="check-icon">✔</span>
                        Personalized recommendations
                    </li>
                </ul>
            </div>

        </div>
    </section>

    <!-- ===================== SLIDER SECTION ===================== -->

    <section class="gallery-section">

        <div class="page-title">
            <h1>Our Gallery</h1>
            <span>Explore Our Premium Properties</span>
        </div>

        <div class="swiper">

            <div class="swiper-wrapper">

                <div class="swiper-slide">
                    <img src="img/pexels-heyho-11 (1).jpg">
                    <div class="detail">
                        <h3>Grand City Apartemen</h3>
                        <span>Surabaya</span>
                    </div>
                </div>

                <div class="swiper-slide">
                    <img src="img/pexels-heyho-11 (2).jpg">
                    <div class="detail">
                        <h3>Citraland</h3>
                        <span>Surabaya</span>
                    </div>
                </div>

                <div class="swiper-slide">
                    <img src="img/pexels-heyho-6312358.jpg">
                    <div class="detail">
                        <h3>Ketintang Regency</h3>
                        <span>Surabaya</span>
                    </div>
                </div>

                <div class="swiper-slide">
                    <img src="img/pexels-heyho-6587800.jpg">
                    <div class="detail">
                        <h3>Pondok Indah Karah</h3>
                        <span>Surabaya</span>
                    </div>
                </div>

            </div>

            <div class="swiper-button-prev nav-btn"><i class="bx bx-chevron-left"></i></div>
            <div class="swiper-button-next nav-btn"><i class="bx bx-chevron-right"></i></div>

            <div class="swiper-pagination"></div>

        </div>

        <div class="bg-dynamic"></div>
        <div class="bg-overlay"></div>

    </section>

    <section class="interior-section">
        <div class="interior-container">

            <!-- TEXT BOX -->
            <div class="interior-text">
                <h1>The Interior Speaks Volumes</h1>
                <p>
                    Koleksi properti pilihan dengan desain interior modern, elegan, dan nyaman.
                    Setiap ruangan dirancang untuk memberikan suasana tenang dan pengalaman hunian premium.
                </p>
                <button class="interior-btn" onclick="scrollToSection('catalog')">Explore</button>
            </div>

            <!-- IMAGE 1 -->
            <div class="interior-img">
                <img src="img/pexels-heyho-6312358.jpg" />
            </div>

            <!-- IMAGE 2 -->
            <div class="interior-img">
                <img src="img/pexels-heyho-6782346.jpg" />
            </div>

            <!-- TEXT BOX 2 -->
            <div class="interior-text">
                <h1>Why Choose Our Interiors?</h1>
                <p>
                    Interior yang dirancang oleh profesional terbaik.
                    Mengutamakan kenyamanan, keindahan, serta fungsionalitas untuk kebutuhan sehari-hari.
                </p>
                <button class="interior-btn" type="button" onclick="scrollToSection('trusted')">See Details</button>
            </div>

        </div>
    </section>


    <!-- ===================== RECENT PHOTOS / KATALOG APARTEMEN ===================== -->

    <section class="recent-photos" id="catalog">

        <div class="search-header">
            <h2>Find your <span>dream home</span></h2>
            <p>Connecting you with the perfect property for your loved ones</p>
        </div>

        <div class="search-filters">

            <!-- Property -->
            <div class="filter-box dropdown-filter">
                <i class="ri-building-line"></i>
                <span id="propertyText">Property</span>
                <i class="ri-arrow-drop-down-line dropdown"></i>

                <ul class="dropdown-menu">
                    <li onclick="setProperty('All')">All</li>
                    <li onclick="setProperty('Apartemen')">Apartemen</li>
                    <li onclick="setProperty('Rumah')">Rumah</li>
                </ul>
            </div>

            <!-- Area -->
            <div class="filter-box dropdown-filter">
                <i class="ri-map-pin-line"></i>
                <span id="areaText">Area</span>
                <i class="ri-arrow-drop-down-line dropdown"></i>

                <ul class="dropdown-menu">
                    <li onclick="setArea('All')">All</li>
                    <li onclick="setArea('Surabaya Barat')">Surabaya Barat</li>
                    <li onclick="setArea('Surabaya Timur')">Surabaya Timur</li>
                    <li onclick="setArea('Surabaya Utara')">Surabaya Utara</li>
                    <li onclick="setArea('Surabaya Selatan')">Surabaya Selatan</li>
                    <li onclick="setArea('Pusat Kota')">Pusat Kota</li>
                </ul>
            </div>

            <!-- Price -->
            <div class="filter-box dropdown-filter">
                <i class="ri-price-tag-3-line"></i>
                <span id="priceText">Price</span>
                <i class="ri-arrow-drop-down-line dropdown"></i>

                <ul class="dropdown-menu">
                    <li onclick="setPrice('All')">All</li>
                    <li onclick="setPrice('Termurah → Tertinggi')">Termurah → Tertinggi</li>
                    <li onclick="setPrice('Tertinggi → Termurah')">Tertinggi → Termurah</li>
                </ul>
            </div>

            <!-- Status -->
            <div class="filter-box dropdown-filter">
                <i class="ri-home-5-line"></i>
                <span id="statusText">Status</span>
                <i class="ri-arrow-drop-down-line dropdown"></i>

                <ul class="dropdown-menu">
                    <li onclick="setStatus('All')">All</li>
                    <li onclick="setStatus('Dijual')">Dijual</li>
                    <li onclick="setStatus('Disewa')">Disewa</li>
                </ul>
            </div>

            <div class="search-wrapper" id="searchWrapper">
                <input type="text" id="searchInput" placeholder="Search property..." />
                <button class="search-btn" id="searchBtn">
                    <i class="ri-search-line"></i>
                </button>
            </div>

        </div>


        <!-- CARD SLIDER -->
        <div class="rp-card-slider">
            <?php foreach ($properties as $p): ?>
                <div class="rp-card"
                    data-id="<?= (int)$p['id'] ?>"
                    data-kind="<?= htmlspecialchars($p['type']) ?>"
                    data-type="<?= $p['type'] === 'RUMAH' ? 'Rumah' : 'Apartemen' ?>"
                    data-area="<?= htmlspecialchars($p['lokasi']) ?>"
                    data-price="<?= (int)$p['harga'] ?>"
                    data-status="<?= statusUIfromType($p['type']) ?>">  

                    <div class="favorite-btn" onclick="event.stopPropagation(); toggleFavorite(this)">
                        <i class="ri-heart-3-fill"></i>
                    </div>

                    <img src="uploads/<?= htmlspecialchars($p['cover']) ?>" alt="">

                    <div class="rp-card-body">
                        <h3><?= htmlspecialchars($p['nama']) ?></h3>

                        <p class="loc">
                            <i class="ri-map-pin-line"></i>
                            <?= htmlspecialchars($p['lokasi']) ?>
                        </p>

                        <div class="infos">
                            <span><i class="ri-price-tag-3-line"></i> <?= htmlspecialchars($p['kategori']) ?></span>
                            <span><i class="ri-hashtag"></i> <?= htmlspecialchars($p['jumlah_unit']) ?> Unit</span>
                        </div>

                        <div class="price-row">
                            <h4 style="font-size: 17px;">
                                <?= rupiah($p['harga']) ?>
                                <?= $p['type'] === 'APARTEMENT' ? '<span style="font-size:12px;color:#666"> /bulan</span>' : '' ?>
                            </h4>
                            <button class="details-btn" onclick="event.stopPropagation(); viewDetail(this);">View Details</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- DOTS + ARROW -->
        <div class="rp-dots">
            <span class="active"></span>
            <span></span>
            <span></span>
        </div>

        <div class="rp-arrows">
            <button class="rp-btn" id="rpPrev">
                <i class="ri-arrow-left-s-line"></i>
            </button>

            <button class="rp-btn" id="rpNext">
                <i class="ri-arrow-right-s-line"></i>
            </button>
        </div>


        <!-- VIDEO -->
        <div class="rp-video">
            <video id="promoVideo" muted playsinline>
                <source src="img/snaptik_7358257704831503621_hd.mp4" type="video/mp4">
            </video>
        </div>

    </section>

    <!-- ===================== MODERN LUXURY INTERIOR SHOWCASE ===================== -->

    <section class="luxury-interior">

        <div class="li-title">
            <span>Modern</span><br>
            Luxury Interior
        </div>

        <div class="li-grid">

            <div class="li-item">
                <img src="img/pexels-heyho-11 (1).jpg">
                <div class="li-text">
                    <h3>Citraland</h3>
                    <p>Modern Luxe Style</p>
                </div>
            </div>

            <div class="li-item">
                <img src="img/pexels-heyho-22 (2).jpg">
                <div class="li-text">
                    <h3>Ketintang Regency</h3>
                    <p>Warm Neutral Interior</p>
                </div>
            </div>

            <div class="li-item">
                <img src="img/pexels-heyho-11 (2).jpg">
                <div class="li-text">
                    <h3>Bess Mansion</h3>
                    <p>Contemporary Living</p>
                </div>
            </div>

            <div class="li-item">
                <img src="img/pexels-heyho-6587800.jpg">
                <div class="li-text">
                    <h3>Klaska Residence</h3>
                    <p>Contemporary Living</p>
                </div>
            </div>

        </div>

    </section>

    <section class="promo-showcase">

        <div class="promo-inner">

            <!-- LEFT IMAGE -->
            <div class="promo-img">
                <img src="img/pexels-heyho-11701127.jpg" alt="">
                <div class="promo-stat">
                    <h3>+15</h3>
                    <p>Years of Experience</p>
                </div>
            </div>

            <!-- CENTER TEXT -->
            <div class="promo-center">
                <span class="promo-sub">— OUR PROMO —</span>
                <h1>EXCLUSIVE OFFERS<br>FOR YOUR NEW HOME</h1>
                <p>
                    Dapatkan benefit spesial mulai dari DP 0%, cashback jutaan rupiah,
                    hingga free biaya notaris untuk unit pilihan.
                </p>

                <button class="promo-btn">Lihat Promo</button>
            </div>

            <!-- RIGHT IMAGE -->
            <div class="promo-img">
                <img src="img/pexels-heyho-6782349.jpg" alt="">
                <div class="promo-stat">
                    <h3>99+</h3>
                    <p>Satisfied Clients</p>
                </div>
            </div>

        </div>

    </section>


    <section class="survey-section">
        <div class="survey-bg"></div>

        <div class="survey-overlay">
            <h2>Form Survei Properti</h2>

            <form action="HOMEPAGEFIX.php" method="POST">
                <input type="hidden" name="action" value="survey_submit">
                <label>Nama Lengkap</label>
                <input type="text" name="full_name" placeholder="Masukkan nama anda..." required>

                <label>Nomor Telepon</label>
                <input type="text" name="phone" placeholder="08xxxxxxxx" required>

                <label>Pesan</label>
                <textarea name="pesan" placeholder="Tulis pesan atau kebutuhan survei anda..." required></textarea>

                <button type="submit"
                    <?= !isset($_SESSION["user_id"]) ? 'onclick="window.location.href=\'regislogin.php\'; return false;"' : '' ?>>
                    Kirim
                </button>
            </form>
        </div>
    </section>

    <section class="partner-section" id="partnerPromo">

        <div class="partner-container">

            <!-- CARD 1 -->
            <div class="partner-card">
                <img src="img/Logo-Bank-Mandiri.jpeg" alt="Partner Image">

                <div class="partner-info">
                    <h4>Bank Mandiri</h4>
                    <h2 class="outline">SPECIAL RATE</h2>
                    <h1>3.5%</h1>
                    <a href="https://www.bankmandiri.co.id/fomo-year-end-2025?utm_source=google&utm_medium=sem&utm_campaign=PMD156_B01_L2_LC_DG_FST_DEC25&utm_content=CF05_DG_GeneralBranded&gad_source=1&gad_campaignid=23329661501&gbraid=0AAAAABhQExAcd8V6Yh5a-gXC_i2EXbmFr&gclid=Cj0KCQiAo4TKBhDRARIsAGW29bfBsJRl2IILNc66v2L2A9_AaEmJ56cwnCQQbVr-GjH_laHxubkAHusaAnUDEALw_wcB"
                        class="partner-btn">Lihat Detail →</a>
                </div>
            </div>

            <!-- CARD 2 -->
            <div class="partner-card">
                <img src="img/Press-Release-BTN-Properti.png" alt="Partner Image">

                <div class="partner-info">
                    <h4>BTN Property</h4>
                    <h2 class="outline">KPR SALE</h2>
                    <h1>0% DP</h1>
                    <a href="https://www.btn.co.id/id" class="partner-btn">Lihat Detail →</a>
                </div>
            </div>

        </div>

    </section>

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
                    <li><a href="contact.php">Contact us</a></li>
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
                    <a href="https://web.facebook.com/?locale=id_ID&_rdc=1&_rdr#"><i
                            class="ri-facebook-circle-line"></i></a>
                    <a href="https://x.com/?lang=id"><i class="ri-twitter-x-line"></i></a>
                </div>
            </div>

        </div>

        <div class="footer-bottom">
            © 2025 PropertyKu — All Rights Reserved.
        </div>

    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Swiper/8.4.5/swiper-bundle.min.js"></script>

    <script>
  // =========================
  // NAVBAR SCROLL (shadow)
  // =========================
  const navbar = document.getElementById("navbar");
  window.addEventListener("scroll", () => {
    if (!navbar) return;
    if (window.scrollY > 80) navbar.classList.add("scrolled");
    else navbar.classList.remove("scrolled");
  });

  // =========================
  // ANIMATION (load)
  // =========================
  window.addEventListener("load", () => {
    document
      .querySelectorAll(".animate-up, .animate-down, .animate-left, .animate-right")
      .forEach((el) => {
        setTimeout(() => el.classList.add("show"), 300);
      });

    // show morning image at first load
    const img = document.querySelector(".house-img");
    if (img) img.classList.add("show");
  });

  // =========================
  // MORNING / NIGHT SWITCH
  // =========================
  const header = document.querySelector(".header");
  const houseImg = document.querySelector(".house-img");
  const btnMorning = document.getElementById("btnMorning");
  const btnNight = document.getElementById("btnNight");

  function animateTitle(mode) {
    const sonder = document.getElementById("sonder");
    const house = document.getElementById("house");
    if (!sonder || !house) return;

    sonder.classList.remove("text-highlight", "text-highlight-dark");
    house.classList.remove("text-highlight", "text-highlight-dark");

    if (mode === "night") sonder.classList.add("text-highlight-dark");
    if (mode === "morning") house.classList.add("text-highlight");
  }

  if (btnMorning) {
    btnMorning.addEventListener("click", () => {
      document.body.classList.remove("dark");
      btnMorning.classList.add("active");
      if (btnNight) btnNight.classList.remove("active");

      if (header) header.style.background = "linear-gradient(to bottom, #637A92, #C8D6E4)";

      if (houseImg) {
        houseImg.classList.remove("show");
        setTimeout(() => {
          houseImg.src =
            "img/Anggun_Halisa_landscape_version_4a75b606-8d0f-443c-82e4-b84f3f7eca72__1_-removebg-preview (2).png";
          houseImg.classList.add("show");
          animateTitle("morning");
        }, 300);
      } else {
        animateTitle("morning");
      }
    });
  }

  if (btnNight) {
    btnNight.addEventListener("click", () => {
      document.body.classList.add("dark");
      btnNight.classList.add("active");
      if (btnMorning) btnMorning.classList.remove("active");

      if (header) header.style.background = "linear-gradient(to bottom, #020617, #0b1220)";

      if (houseImg) {
        houseImg.classList.remove("show");
        setTimeout(() => {
          houseImg.src =
            "img/Anggun_Halisa_Make_it_exactly_like_this_picture__but_the_house_lights_are_on__t_dd5aa423-db20-421a-a7e8-2d23321edb17-removebg-preview.png";
          houseImg.classList.add("show");
          animateTitle("night");
        }, 300);
      } else {
        animateTitle("night");
      }
    });
  }

  // =========================
  // AUTO IMAGE ABOUT
  // =========================
  const autoImg = document.getElementById("autoImage");
  const images = [
    "img/pexels-vika-glitter-392079-1648768.jpg",
    "img/pexels-heyho-11701123.jpg",
    "img/pexels-heyho-6782369.jpg",
  ];

  if (autoImg) {
    let index = 0;
    setInterval(() => {
      index = (index + 1) % images.length;
      autoImg.style.opacity = 0;
      setTimeout(() => {
        autoImg.src = images[index];
        autoImg.style.opacity = 1;
      }, 300);
    }, 3000);
  }

  // =========================
  // SWIPER GALLERY
  // =========================
  let swiper;
  if (typeof Swiper !== "undefined" && document.querySelector(".swiper")) {
    swiper = new Swiper(".swiper", {
      effect: "coverflow",
      grabCursor: true,
      centeredSlides: true,
      loop: true,
      slidesPerView: "auto",
      loopAdditionalSlides: 5,
      coverflowEffect: {
        rotate: 0,
        stretch: 0,
        depth: 150,
        modifier: 1.3,
        slideShadows: false,
      },
      navigation: {
        nextEl: ".swiper-button-next",
        prevEl: ".swiper-button-prev",
      },
      pagination: {
        el: ".swiper-pagination",
        clickable: true,
      },
    });

    function updateBackground() {
      const activeSlide = document.querySelector(".swiper-slide-active img");
      const bg = document.querySelector(".bg-dynamic");
      if (activeSlide && bg) bg.style.backgroundImage = `url('${activeSlide.src}')`;
    }

    updateBackground();
    swiper.on("slideChange", updateBackground);
  }

  // =========================
  // DROPDOWN FILTERS (Property/Area/Price/Status)
  // =========================
  function closeAllDropdowns() {
    document.querySelectorAll(".dropdown-menu").forEach((menu) => {
      menu.style.display = "none";
    });
  }

  document.querySelectorAll(".dropdown-filter").forEach((box) => {
    box.addEventListener("click", function (e) {
      e.stopPropagation();
      closeAllDropdowns();
      const menu = this.querySelector(".dropdown-menu");
      if (menu) menu.style.display = "block";
    });
  });

  document.addEventListener("click", closeAllDropdowns);

  // =========================
  // FILTERING + SORT + SEARCH
  // =========================
  function applyFilters() {
    const propertyText = document.getElementById("propertyText");
    const areaText = document.getElementById("areaText");
    const priceText = document.getElementById("priceText");
    const statusText = document.getElementById("statusText");
    const searchInput = document.getElementById("searchInput");

    const selectedType = propertyText ? propertyText.innerText : "All";
    const selectedArea = areaText ? areaText.innerText : "All";
    const selectedPrice = priceText ? priceText.innerText : "All";
    const selectedStatus = statusText ? statusText.innerText : "All";
    const searchText = searchInput ? searchInput.value.toLowerCase() : "";

    const cards = document.querySelectorAll(".rp-card");
    let cardArray = Array.from(cards);

    // sorting
    if (selectedPrice === "Termurah → Tertinggi") {
      cardArray.sort((a, b) => parseInt(a.dataset.price || "0") - parseInt(b.dataset.price || "0"));
    } else if (selectedPrice === "Tertinggi → Termurah") {
      cardArray.sort((a, b) => parseInt(b.dataset.price || "0") - parseInt(a.dataset.price || "0"));
    }

    const container = document.querySelector(".rp-card-slider");
    if (container) {
      container.innerHTML = "";
      cardArray.forEach((card) => container.appendChild(card));
    }

    // filtering
    cardArray.forEach((card) => {
      let match = true;

      const name = (card.querySelector("h3")?.innerText || "").toLowerCase();
      const area = (card.dataset.area || "").toLowerCase();
      const type = (card.dataset.type || "").toLowerCase();
      const status = (card.dataset.status || "").toLowerCase();

      if (selectedType !== "All" && selectedType !== "Property" && (card.dataset.type || "") !== selectedType) {
        match = false;
      }

      if (selectedArea !== "All" && selectedArea !== "Area" && (card.dataset.area || "") !== selectedArea) {
        match = false;
      }

      if (selectedStatus !== "All" && selectedStatus !== "Status" && (card.dataset.status || "") !== selectedStatus) {
        match = false;
      }

      if (searchText !== "") {
        if (!name.includes(searchText) && !area.includes(searchText) && !type.includes(searchText)) {
          match = false;
        }
      }

      card.style.display = match ? "block" : "none";
    });
  }

  // SETTERS (1x saja, ini yg dipakai onclick di HTML)
  function setProperty(value) {
    const el = document.getElementById("propertyText");
    if (el) el.innerText = value;
    closeAllDropdowns();
    applyFilters();
  }

  function setArea(value) {
    const el = document.getElementById("areaText");
    if (el) el.innerText = value;
    closeAllDropdowns();
    applyFilters();
  }

  function setPrice(value) {
    const el = document.getElementById("priceText");
    if (el) el.innerText = value;
    closeAllDropdowns();
    applyFilters();
  }

  function setStatus(value) {
    const el = document.getElementById("statusText");
    if (el) el.innerText = value;
    closeAllDropdowns();
    applyFilters();
  }

  // supaya onclick di HTML bisa manggil fungsi ini
  window.setProperty = setProperty;
  window.setArea = setArea;
  window.setPrice = setPrice;
  window.setStatus = setStatus;

  // SEARCH BUTTON
  const searchWrapper = document.getElementById("searchWrapper");
  const searchBtn = document.getElementById("searchBtn");
  const searchInput = document.getElementById("searchInput");

  if (searchBtn && searchWrapper) {
    searchBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      searchWrapper.classList.toggle("active");
      if (searchWrapper.classList.contains("active")) {
        setTimeout(() => searchInput && searchInput.focus(), 150);
      } else {
        if (searchInput) searchInput.value = "";
        applyFilters();
      }
    });
  }

  if (searchInput) {
    searchInput.addEventListener("keyup", applyFilters);
  }

  // klik luar: tutup search
  document.addEventListener("click", () => {
    if (searchWrapper && searchWrapper.classList.contains("active")) {
      searchWrapper.classList.remove("active");
      if (searchInput) searchInput.value = "";
      applyFilters();
    }
  });

    // =========================
    // FAVORITES (DB)
    // =========================

    // biar bisa dipakai di JS untuk cek login dari PHP
    const IS_LOGGED_IN = <?= isset($_SESSION["user_id"]) ? "true" : "false" ?>;

    async function api(url, bodyObj=null) {
    const opt = bodyObj
        ? {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams(bodyObj).toString()
        }
        : {};
    const res = await fetch(url, opt);
    return res.json();
    }

    // nyalain icon favorite yang sudah ada di DB
    async function syncFavoriteButtons() {
    if (!IS_LOGGED_IN) return;

    const ids = await api("favorite.php?api=ids"); // return: [1,2,3]
    const set = new Set((ids || []).map(String));

    document.querySelectorAll(".rp-card").forEach((card) => {
        const id = String(card.dataset.id || "");
        const btn = card.querySelector(".favorite-btn");
        if (!btn) return;
        if (set.has(id)) btn.classList.add("active");
        else btn.classList.remove("active");
    });
    }

    // klik hati
    async function toggleFavorite(btn) {
    if (!btn) return;

    if (!IS_LOGGED_IN) {
        window.location.href =
        "regislogin.php?redirect=" + encodeURIComponent(window.location.href);
        return;
    }

    const card = btn.closest(".rp-card");
    if (!card) return;

    const propertyId = card.dataset.id;

    // (opsional) biar langsung berubah tanpa nunggu
    btn.classList.toggle("active");

    try {
        const out = await api("favorite.php?api=toggle", { property_id: propertyId });

        // validasi
        if (!out || out.ok !== true) {
        // rollback kalau gagal
        btn.classList.toggle("active");
        alert("Gagal update favorite.");
        return;
        }

        // SESUAIKAN dengan response dari API (action)
        if (out.action === "added") btn.classList.add("active");
        if (out.action === "removed") btn.classList.remove("active");

    } catch (e) {
        // rollback kalau error
        btn.classList.toggle("active");
        alert("Error koneksi saat update favorite.");
    }
    }

    window.toggleFavorite = toggleFavorite;

    document.addEventListener("DOMContentLoaded", syncFavoriteButtons);

  // =========================
  // MOBILE NAV + PROFILE + DROPDOWN PROPERTI
  // =========================
  document.addEventListener("DOMContentLoaded", () => {
    // Login button
    const btnLogin = document.getElementById("btnLogin");
    if (btnLogin) {
      btnLogin.addEventListener("click", () => (window.location.href = "regislogin.php"));
    }

    // Mobile nav
    const navToggle = document.getElementById("navToggle");
    const navMobile = document.getElementById("navMobile");
    const mPropMenu = document.getElementById("mPropMenu");

    if (navToggle && navMobile) {
      navToggle.addEventListener("click", (e) => {
        e.stopPropagation();
        navMobile.classList.toggle("show");
        if (!navMobile.classList.contains("show") && mPropMenu) mPropMenu.classList.remove("show");
      });

      navMobile.querySelectorAll("a").forEach((a) => {
        a.addEventListener("click", () => {
          navMobile.classList.remove("show");
          if (mPropMenu) mPropMenu.classList.remove("show");
        });
      });
    }

    // Desktop prop dropdown
    const propBtn = document.getElementById("propBtn");
    const propMenu = document.getElementById("propMenu");

    if (propBtn && propMenu) {
      propBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        propMenu.classList.toggle("show");
      });
      propMenu.addEventListener("click", (e) => e.stopPropagation());
    }

    // Mobile prop dropdown
    const mPropBtn = document.getElementById("mPropBtn");
    if (mPropBtn && mPropMenu) {
      mPropBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        mPropMenu.classList.toggle("show");
      });
      mPropMenu.addEventListener("click", (e) => e.stopPropagation());
    }

    // Profile dropdown
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
    }

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
        const pw = formEdit.querySelector('input[name="new_password"]');
        const cpw = formEdit.querySelector('input[name="confirm_password"]');
        if (pw) pw.value = "";
        if (cpw) cpw.value = "";
      });
    }

    // Global click close
    document.addEventListener("click", () => {
      if (propMenu) propMenu.classList.remove("show");
      if (mPropMenu) mPropMenu.classList.remove("show");
      if (profileMenu) profileMenu.classList.remove("show");
      if (formEdit) formEdit.classList.remove("show");
      if (navMobile) navMobile.classList.remove("show");
    });
  });

  // =========================
  // CARD DETAIL
  // =========================
  function viewDetail(btn) {
    const card = btn.closest(".rp-card");
    if (!card) return;

    const id = card.dataset.id;
    const kind = card.dataset.kind; // "RUMAH" atau "APARTEMENT"
    const page = kind === "RUMAH" ? "detailhome.php" : "detailapart.php";
    window.location.href = `${page}?id=${encodeURIComponent(id)}`;
  }
  window.viewDetail = viewDetail;

  // =========================
  // CARD SLIDER ARROWS + DOTS
  // =========================
  document.addEventListener("DOMContentLoaded", () => {
    const slider = document.querySelector(".rp-card-slider");
    const btnNext = document.getElementById("rpNext");
    const btnPrev = document.getElementById("rpPrev");
    const dots = document.querySelectorAll(".rp-dots span");

    if (slider && btnNext) {
      btnNext.addEventListener("click", () => slider.scrollBy({ left: 320, behavior: "smooth" }));
    }
    if (slider && btnPrev) {
      btnPrev.addEventListener("click", () => slider.scrollBy({ left: -320, behavior: "smooth" }));
    }

    function getMaxScroll() {
      return slider ? slider.scrollWidth - slider.clientWidth : 0;
    }

    function updateActiveDot() {
      if (!slider || dots.length === 0) return;
      const maxScroll = getMaxScroll();
      const current = slider.scrollLeft;
      const idx = maxScroll === 0 ? 0 : Math.round((current / maxScroll) * (dots.length - 1));
      dots.forEach((d) => d.classList.remove("active"));
      if (dots[idx]) dots[idx].classList.add("active");
    }

    dots.forEach((dot, i) => {
      dot.addEventListener("click", () => {
        if (!slider) return;
        const maxScroll = getMaxScroll();
        slider.scrollTo({
          left: (i / Math.max(1, dots.length - 1)) * maxScroll,
          behavior: "smooth",
        });
      });
    });

    if (slider) slider.addEventListener("scroll", updateActiveDot);
  });

  // =========================
  // VIDEO TOGGLE
  // =========================
  const video = document.getElementById("promoVideo");
  if (video) {
    video.addEventListener("click", () => {
      if (video.paused) video.play();
      else video.pause();
    });
  }

  // =========================
  // PROMO SCROLL TO PARTNER
  // =========================
  document.addEventListener("DOMContentLoaded", () => {
    const btn = document.querySelector(".promo-btn");
    const target = document.querySelector("#partnerPromo");
    if (!btn || !target) return;
    btn.addEventListener("click", () => {
      target.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  });

  // =========================
  // SCROLL TO SECTION helper
  // =========================
  function scrollToSection(id) {
    const el = document.getElementById(id);
    if (el) el.scrollIntoView({ behavior: "smooth" });
  }
  window.scrollToSection = scrollToSection;
</script>
</body>
<?php if (isset($_GET['survey']) && $_GET['survey'] === 'success'): ?>
  <script>alert("Survei berhasil dikirim! Admin akan menghubungi kamu.");</script>
<?php endif; ?>
<?php if (isset($_GET['profile']) && $_GET['profile'] === 'success'): ?>
  <script>alert("Profil berhasil diperbarui!");</script>
<?php elseif (isset($_GET['profile']) && $_GET['profile'] === 'fail'): ?>
  <script>alert("Gagal update profil. Cek username/password kamu.");</script>
<?php endif; ?>
</html>