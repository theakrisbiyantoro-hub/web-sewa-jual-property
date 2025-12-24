<?php
include "database.php";
session_start();

/* ===== PROTEKSI ADMIN ===== */
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: regislogin.php");
    exit;
}

/* ===== PROSES APPROVE / REJECT ===== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {

    $payment_id = (int)($_POST["payment_id"] ?? 0);
    $booking_id = (int)($_POST["booking_id"] ?? 0);
    $admin_id   = (int)($_SESSION["user_id"] ?? 0);

    // VALIDASI WAJIB
    if ($booking_id <= 0) {
        die("BOOKING ID TIDAK VALID");
    }

    if ($_POST["action"] === "approve") {

        // WAJIB ADA PAYMENT
        if ($payment_id <= 0) {
            die("PAYMENT ID TIDAK VALID");
        }

        // 1️⃣ UPDATE BOOKING_PAYMENTS (INI YANG SEBELUMNYA HILANG)
        $koneksi->query("
            UPDATE booking_payments
            SET status='verified',
                verified_at=NOW(),
                verified_by=$admin_id
            WHERE id=$payment_id
        ");

        // 2️⃣ UPDATE BOOKING
        $koneksi->query("
            UPDATE booking
            SET status='verified',
                verified_at=NOW(),
                verified_by=$admin_id
            WHERE id=$booking_id
        ");

        header("Location: riwayatadmin.php");
        exit;
    }

    if ($_POST["action"] === "reject") {

        if ($payment_id > 0) {
            $koneksi->query("
                UPDATE booking_payments
                SET status='rejected',
                    verified_at=NOW(),
                    verified_by=$admin_id
                WHERE id=$payment_id
            ");
        }

        $koneksi->query("
            UPDATE booking
            SET status='rejected'
            WHERE id=$booking_id
        ");

        header("Location: riwayatadmin.php");
        exit;
    }
}


/* ===== AMBIL DATA (PAKAI LEFT JOIN) ===== */
$res = $koneksi->query("
    SELECT 
        b.id AS booking_id,
        b.full_name,
        b.unit_type,
        b.checkin_date,
        b.status AS booking_status,

        bp.id AS payment_id,
        bp.amount,
        bp.proof_file,
        COALESCE(NULLIF(bp.status, ''), 'wait_verify') AS payment_status,
        bp.submitted_at
    FROM booking b
    LEFT JOIN booking_payments bp ON bp.booking_id = b.id
    ORDER BY b.created_at DESC
");


$data = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$total = count($data);


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Riwayat Booking - Admin</title>

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
            --border-soft: #E5E7EB;
            --card: #fff;
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
            box-shadow: 0 18px 45px rgba(0, 0, 0, .08);
            display: flex;
            overflow: hidden;
            min-height: 600px;
        }

        /* SIDEBAR */
        .sidebar {
            flex: 0 0 230px;
            width: 230px;
            background: #fff;
            border-right: 1px solid var(--border-soft);
            padding: 28px 22px;
            display: flex;
            flex-direction: column;
            transition: width .25s ease, padding .25s ease;
            overflow: hidden;
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

        .profile {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-direction: column;
            text-align: center;
            margin-top: 10px;
            margin-bottom: 10px;
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

        /* COLLAPSE SIDEBAR */
        .sidebar.collapsed {
            width: 60px;
            flex: 0 0 60px;
            padding: 18px 8px;
        }

        .sidebar.collapsed .sidebar-top {
            justify-content: center;
        }

        .sidebar.collapsed .sidebar-top-title {
            display: none;
        }

        .sidebar.collapsed .profile,
        .sidebar.collapsed .sidebar-title,
        .sidebar.collapsed .menu {
            display: none;
        }

        /* MAIN */
        .main {
            flex: 1;
            padding: 32px;
            background: #F7F8FA;
            min-width: 0;
        }

        .header-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .header-box h2 {
            margin-left: 10px;
            font-size: 22px;
            color: var(--text-main);
        }

        .search-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-box {
            background: #fff;
            border-radius: 999px;
            padding: 8px 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--border-soft);
            width: 320px;
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
        }

        .badge {
            background: var(--primary-soft);
            color: var(--primary);
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid var(--border-soft);
        }

        /* ALERT */
        .alert {
            background: #e8fff1;
            border: 1px solid #b7f0cc;
            color: #166534;
            padding: 10px 14px;
            border-radius: 14px;
            font-size: 13px;
            margin-bottom: 14px;
        }

        /* TABLE */
        .table-box {
            background: #fff;
            padding: 20px;
            border-radius: 20px;
            box-shadow: 0 8px 22px rgba(0, 0, 0, .05);
        }

        .table-scroll {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            touch-action: pan-x;
        }

        .table-scroll table {
            width: 100%;
            min-width: 1000px;
            border-collapse: collapse;
        }

        th,
        td {
            text-align: center;
            padding: 10px 8px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        th {
            color: var(--text-main);
            font-weight: 600;
            font-size: 15px;
        }

        td.msg-cell {
            text-align: left;
            max-width: 560px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        td.waktu-cell {
            color: var(--text-soft);
            font-size: 13px;
            white-space: nowrap;
        }

        /* BUTTON */
        .action-btn {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-block;
        }

        .delete-btn {
            background: #ffe8e8;
            color: #d11a1a;
        }

        .delete-btn:hover {
            background: #ffd1d1;
        }

        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .35);
            opacity: 0;
            pointer-events: none;
            transition: .2s;
            z-index: 150;
        }

        .overlay.show {
            opacity: 1;
            pointer-events: auto;
        }

        .mobile-topbar {
            display: none;
            align-items: center;
            gap: 12px;
            padding: 8px 10px;
            border-radius: 16px;
            margin-bottom: 12px;
            top: 10px;
            z-index: 200;
            position: sticky;
        }

        /* RESPONSIVE */
        @media(max-width:1100px) {
            .header-box {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .search-wrapper {
                width: 100%;
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }

            .search-box {
                width: 100%;
            }
        }

        @media(max-width:600px) {
            body {
                padding: 12px;
            }

            .app {
                border-radius: 24px;
                overflow: visible;
            }

            .main {
                padding: 14px;
            }

            .mobile-topbar {
                display: flex;
            }

            .sidebar .hamburger {
                display: none;
            }

            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                bottom: 0;
                width: 260px;
                transform: translateX(-105%);
                transition: .25s;
                box-shadow: 0 18px 45px rgba(0, 0, 0, .18);
                z-index: 300;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .table-scroll table {
                min-width: 1050px;
                width: 1050px;
            }
        }

        .badge.wait_verify {
            background: #fff4e5;
            color: #b45309;
        }

        .badge.approved {
            background: #e8fff1;
            color: #166534;
        }

        .badge.rejected {
            background: #ffe8e8;
            color: #b91c1c;
        }


        .badge.verified {
            background: #e8fff1;
            color: #166534;
        }

        <?php include "style_message_admin.css"; ?>
    </style>
</head>

<body>
    <div id="overlay" class="overlay"></div>

    <div class="app">

        <!-- SIDEBAR -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-top">
                <h4 class="sidebar-top-title">Dashboard</h4>
                <button id="toggleSidebar" class="hamburger"><i class="ri-menu-line"></i></button>
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
                    <li><a href="survey.php"><i class="ri-send-plane-line"></i> Survey</a></li>
                    <li><a href="message.php"><i class="ri-mail-line"></i> Messages</a></li>
                    <li><a class="active" href="riwayatadmin.php"><i class="ri-file-list-3-line"></i> Riwayat</a></li>
                </ul>

                <div class="sidebar-title">Akun</div>
                <ul class="menu">
                    <li><a href="logout.php"><i class="ri-logout-circle-r-line"></i> Logout</a></li>
                </ul>
            </div>
        </aside>

        <!-- MAIN -->
        <main class="main">

            <div class="header-box">
                <h2>Riwayat Booking</h2>

                <div class="search-wrapper">
                    <div class="search-box">
                        <i class="ri-search-line"></i>
                        <input type="text" id="searchInput" placeholder="Cari nama / unit">
                    </div>
                    <div class="badge">Total: <?= $total ?></div>
                </div>
            </div>

            <div class="table-box">
                <div class="table-scroll">
                    <table id="tbl">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama</th>
                                <th>Unit</th>
                                <th>Check-in</th>
                                <th>Nominal</th>
                                <th>Bukti</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (empty($data)): ?>
                                <tr>
                                    <td colspan="8" style="padding:20px;color:#6B7280">Belum ada booking</td>
                                </tr>

                                <?php else: foreach ($data as $d): ?>
                                    <tr>
                                        <td>#<?= $d["booking_id"] ?></td>
                                        <td><?= htmlspecialchars($d["full_name"]) ?></td>
                                        <td><?= htmlspecialchars($d["unit_type"]) ?></td>
                                        <td><?= $d["checkin_date"] ?></td>
                                        <td>
                                            <?= $d["amount"] ? "Rp " . number_format($d["amount"]) : "-" ?>
                                        </td>

                                        <td>
                                            <?php if ($d["proof_file"]): ?>
                                                <a href="uploads/payments/<?= $d["proof_file"] ?>" target="_blank">
                                                    <img src="uploads/payments/<?= $d["proof_file"] ?>" style="width:60px;border-radius:8px">
                                                </a>
                                            <?php else: ?> -
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="badge <?= $d["payment_status"] ?>">
                                                <?= ucfirst($d["payment_status"]) ?>
                                            </span>

                                        </td>

                                        <td>
                                            <?php if (in_array($d["payment_status"], ["pending", "wait_verify"]) && $d["payment_id"]): ?>

                                                <form method="POST" action="" style="display:flex;gap:6px;justify-content:center">
                                                    <input type="hidden" name="payment_id" value="<?= $d["payment_id"] ?>">
                                                    <input type="hidden" name="booking_id" value="<?= $d["booking_id"] ?>">

                                                    <button type="submit" name="action" value="approve" class="action-btn">
                                                        Approve
                                                    </button>

                                                    <button type="submit" name="action" value="reject" class="action-btn delete-btn">
                                                        Reject
                                                    </button>
                                                </form>

                                            <?php else: ?> -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                            <?php endforeach;
                            endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <script>
        document.getElementById("searchInput").addEventListener("keyup", e => {
            let q = e.target.value.toLowerCase();
            document.querySelectorAll("#tbl tbody tr").forEach(r => {
                r.style.display = r.innerText.toLowerCase().includes(q) ? "" : "none";
            });
        });
    </script>
</body>

</html>