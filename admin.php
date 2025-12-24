<?php
include "database.php";
session_start();

// Cek role admin
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    die("Akses ditolak!");
}

// Ambil data properti
$dataProperti = $koneksi->query("SELECT * FROM properti ORDER BY id DESC");

// Tangkap pesan dari form / delete
$mes = $_SESSION['mes'] ?? '';
unset($_SESSION['mes']);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel</title>
</head>
<body>
<?php include 'header.html'; ?>

<h2>Admin Panel</h2>
<?php if($mes): ?>
<p><i><?= $mes ?></i></p>
<?php endif; ?>

<a href="adminDashboard.php">Tambah Properti</a><br><br><br>

<h3>Daftar Properti</h3>
<table border="1" cellpadding="5" cellspacing="0">
<tr>
    <th>Nama</th>
    <th>Tipe</th>
    <th>Harga</th>
    <th>Status</th>
    <th>Aksi</th>
</tr>
<?php while($row = $dataProperti->fetch_assoc()): ?>
<tr>
    <td><?= $row['nama'] ?></td>
    <td><?= $row['tipe'] ?></td>
    <td>Rp <?= number_format($row['harga'],0,'.','.') ?></td>
    <td><?= $row['status'] ?></td>
    <td>
        <a href="adminDashboard.php?edit=<?= $row['id'] ?>">Edit</a> |
        <a href="adminDashboard.php?delete=<?= $row['id'] ?>" onclick="return confirm('Hapus properti ini?')">Hapus</a>
    </td>
</tr>
<?php endwhile; ?>
</table>
</body>
</html>
