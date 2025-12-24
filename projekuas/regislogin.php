<?php
include "database.php";
session_start();

$redirect = $_GET["redirect"] ?? "";
// biar aman: hanya boleh redirect ke halaman lokal (bukan link luar)
if ($redirect && strpos($redirect, "/") === 0) {
    // OK (misal /HOMEPAGEFIX.php#catalog)
} elseif ($redirect && preg_match('/^https?:\/\//i', $redirect)) {
    $redirect = ""; // blok redirect ke luar
} elseif ($redirect) {
}
/* whitelist halaman yang boleh */
$allowed = [
  "HOMEPAGEFIX.php",
  "favorite.php",
  "riwayat.php",
  "detailhome.php",
  "detailapart.php",
  "contact.php"
];

if ($redirect) {
  $path = ltrim(parse_url($redirect, PHP_URL_PATH) ?? "", "/");

  if (!in_array($path, $allowed, true)) {
    $redirect = "";
  }
}

$error = "";
$success = "";

// kalau sudah login, arahkan
if (isset($_SESSION["user_id"])) {
    $role = strtolower($_SESSION["role"] ?? "user");

    if ($role === "admin") {
        header("Location: dashboard.php");
    } else {
        // kalau ada redirect, balikin ke situ
        if (!empty($redirect)) {
            header("Location: " . $redirect);
        } else {
            header("Location: HOMEPAGEFIX.php");
        }
    }
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    // =========================
    // REGISTER
    // =========================
    if ($action === "register") {
        $name = trim($_POST["name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";

        if ($name === "" || $email === "" || $password === "") {
            $error = "Semua field register wajib diisi.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Email tidak valid.";
        } elseif (strlen($password) < 6) {
            $error = "Password minimal 6 karakter.";
        } else {
            $cek = $koneksi->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
            $cek->bind_param("s", $email);
            $cek->execute();
            $exists = $cek->get_result()->num_rows > 0;
            $cek->close();

            if ($exists) {
                $error = "Email telah terdaftar! Gunakan yang lain.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $koneksi->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')");
                $stmt->bind_param("sss", $name, $email, $hash);

                if ($stmt->execute()) {
                    $success = "Akun berhasil dibuat. Silahkan login.";
                } else {
                    $error = "Gagal registrasi.";
                }
                $stmt->close();
            }
        }

    // =========================
    // LOGIN
    // =========================
    } elseif ($action === "login") {
        $email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";

        if ($email === "" || $password === "") {
            $error = "Email dan password wajib diisi.";
        } else {
            $stmt = $koneksi->prepare("SELECT id, name, email, password, role FROM users WHERE email=? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$user) {
                $error = "Email tidak ditemukan!";
            } elseif (!password_verify($password, $user["password"])) {
                $error = "Password salah!";
            } else {
                $role = strtolower(trim($user["role"] ?? "user"));

            $_SESSION["user_id"] = (int)$user["id"];
            $_SESSION["name"]    = $user["name"];
            $_SESSION["email"]   = $user["email"];
            $_SESSION["role"]    = $role;

            if ($role === "admin") {
                header("Location: dashboard.php");
            } else {
                if (!empty($redirect)) {
                    header("Location: " . $redirect);
                } else {
                    header("Location: HOMEPAGEFIX.php");
                }
            }
            exit;
            }
        }
    }
}

$openRegister = (isset($_POST["action"]) && $_POST["action"] === "register" && $error !== "");
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | Register - PropertyKu</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- FONT -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Poppins", sans-serif; }
        body { background: #0f172a; height: 100vh; display: flex; align-items: center; justify-content: center; }

        .auth-container {
            width: 100%;
            max-width: 1100px;
            height: 620px;
            background: #ffffff;
            border-radius: 26px;
            overflow: hidden;
            display: flex;
            box-shadow: 0 40px 100px rgba(0,0,0,.35);
        }

        .auth-left {
            flex: 1;
            background: linear-gradient(rgba(15,23,42,.15), rgba(15,23,42,.85)),
            url("img/pexels-heyho-11701127.jpg") center/cover no-repeat;
            color: white;
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .brand { font-size: 26px; font-weight: 700; letter-spacing: .5px; }
        .auth-left-content h2 { font-size: 36px; font-weight: 600; margin-bottom: 14px; }
        .auth-left-content p { font-size: 15px; opacity: .85; line-height: 1.7; margin-bottom: 26px; }
        .auth-right { flex: 1; padding: 60px 70px; display: flex; align-items: center; }
        .form-box { width: 100%; }
        .form-title { font-size: 30px; font-weight: 600; color: #1e293b; margin-bottom: 6px; }
        .form-subtitle { font-size: 14px; color: #64748b; margin-bottom: 32px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { font-size: 13px; color: #64748b; display: block; margin-bottom: 6px; }
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid #cbd5e1;
            font-size: 14px;
            outline: none;
        }
        .form-group input:focus { border-color: #475569; }
        .form-extra {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 26px;
            font-size: 13px;
            color: #64748b;
        }
        .form-btn {
            width: 100%;
            padding: 15px;
            border-radius: 40px;
            border: none;
            background: #1e293b;
            color: white;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: .3s;
        }
        .form-btn:hover { background: #020617; }
        .form-footer { text-align: center; margin-top: 26px; font-size: 13px; color: #64748b; }
        .form-footer span { color: #1e293b; cursor: pointer; font-weight: 500; }
        .register-form { display: none; }
        .auth-container.active .login-form { display: none; }
        .auth-container.active .register-form { display: block; }
        /* pesan */
        .msg { margin-bottom: 14px; padding: 10px 12px; border-radius: 12px; font-size: 13px; }
        .msg.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .msg.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }

        @media (max-width: 900px) {
            .auth-container { flex-direction: column; height: auto; }
            .auth-left { height: 260px; padding: 40px; }
            .auth-right { padding: 40px; }
        }
    </style>
</head>
<body>

<div class="auth-container <?= $openRegister ? 'active' : '' ?>" id="authBox">

    <!-- LEFT -->
    <div class="auth-left">
        <div class="brand">PropertyKu</div>

        <div class="auth-left-content">
            <h2>Create Account</h2>
            <p>
                Join PropertyKu and discover premium apartments,
                modern living, and exclusive experiences.
            </p>
        </div>
    </div>

    <!-- RIGHT -->
    <div class="auth-right">

        <!-- LOGIN (tetap DIV form-box, form di dalam) -->
        <div class="form-box login-form">
            <?php if ($error && (!$openRegister)): ?>
                <div class="msg error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="msg success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST" action="regislogin.php<?= !empty($redirect) ? '?redirect='.urlencode($redirect) : '' ?>">
                <input type="hidden" name="action" value="login">

                <h2 class="form-title">Log In</h2>
                <p class="form-subtitle">Welcome back, please login to your account</p>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="example@email.com" required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>

                <div class="form-extra">
                    <label><input type="checkbox"> Remember me</label>
                </div>

                <button class="form-btn" type="submit">Log In</button>

                <div class="form-footer">
                    Don’t have an account?
                    <span onclick="toggleAuth()">Sign Up</span>
                </div>
            </form>
        </div>

        <!-- REGISTER (tetap DIV form-box, form di dalam) -->
        <div class="form-box register-form">
            <?php if ($error && $openRegister): ?>
                <div class="msg error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success && $openRegister): ?>
                <div class="msg success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST" action="regislogin.php<?= !empty($redirect) ? '?redirect='.urlencode($redirect) : '' ?>">
                <input type="hidden" name="action" value="register">

                <h2 class="form-title">Sign Up</h2>
                <p class="form-subtitle">Create your account to get started</p>

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" placeholder="Your full name" required>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="example@email.com" required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Create password" required>
                </div>

                <button class="form-btn" type="submit">Create Account</button>

                <div class="form-footer">
                    Already have an account?
                    <span onclick="toggleAuth()">Log In</span>
                </div>
            </form>
        </div>

    </div>
</div>

<script>
    function toggleAuth() {
        document.getElementById("authBox").classList.toggle("active");
    }
</script>

</body>
</html>
