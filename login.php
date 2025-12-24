<?php
include "database.php";
session_start();

$mes = "";

if (isset($_POST["login"])) {
    $email = $_POST["email"];
    $password = $_POST["password"];

    // 1. Ambil user berdasarkan email
    $query = "SELECT * FROM users WHERE email='$email'";
    $hasil = $koneksi->query($query);

    if ($hasil->num_rows === 0) {
        $mes = "Email tidak ditemukan!";
    } else {
        $user = $hasil->fetch_assoc();

        // 2. Cek password
        if (password_verify($password, $user["password"])) {

            // Set session
            $_SESSION["name"] = $user["name"];
            $_SESSION["email"] = $user["email"];
            $_SESSION["role"] = $user["role"];
            $_SESSION["is_login"] = true; //klo mau hapus?

            // Redirect berdasarkan role
            if ($user["role"] === "admin") {
                header("location: admin.php");
            } else {
                header("location: home.php");
            }
            exit;

        } else {
            $mes = "Password salah!";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>
<?php include 'header.html' ?>
<h2>Login</h2>
<p><i><?= $mes ?></i></p>
<form action="" method="POST">
    <input type="email" name="email" placeholder="Email" required><br><br>
    <input type="password" name="password" placeholder="Password" required><br><br>
    <button type="submit" name="login">Login</button>
</form>

<p>Belum punya akun? <a href="regis.php">Register</a></p>

</body>
</html>