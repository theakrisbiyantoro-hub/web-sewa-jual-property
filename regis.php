<?php
include "database.php";
session_start();

$mes = "";

if (isset($_POST["register"])) {
    $name = $_POST["name"];
    $email = $_POST["email"];
    $password = $_POST["password"];

    // 2. Cek apakah email sudah terdaftar
    $cek = "SELECT * FROM users WHERE email='$email'";
    $hasil = $koneksi->query($cek);

    if ($hasil->num_rows > 0) {
        $mes = "Email sudah terdaftar!";
    } else {
        // 3. Hash password
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // 4. Insert user baru (role default: user)
        $query = "INSERT INTO users (name, email, password, role)
                VALUES ('$name', '$email', '$hash', 'user')";

        if ($koneksi->query($query)) {
            header("location:login.php");
        } else {
            $mes = "Gagal registrasi";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
</head>
<body>
<?php include 'header.html' ?>
<h2>Register</h2>
<p><i><?= $mes ?></i></p>
<form action="" method="POST">
    <input type="text" name="name" placeholder="Name" required><br><br>
    <input type="email" name="email" placeholder="Email" required><br><br>
    <input type="password" name="password" placeholder="Password" required><br><br>
    <button type="submit" name="register">Register</button>
</form>

</body>
</html>

