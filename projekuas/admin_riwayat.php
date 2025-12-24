<?php
session_start();
include "database.php";

// pastikan admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header("Location: regislogin.php");
  exit;
}

$q = $koneksi->query("
  SELECT 
    bp.*,
    b.property_id,
    b.unit_type,
    b.checkin_date,
    u.name AS user_name,
    p.nama AS property_name
  FROM booking_payments bp
  JOIN booking b ON bp.booking_id = b.id
  JOIN users u ON bp.user_id = u.id
  JOIN property p ON b.property_id = p.id
  WHERE bp.status = 'wait_verify'
  ORDER BY bp.submitted_at DESC
");
?>

<!DOCTYPE html>
<html>
<head>
  <title>Admin - Verifikasi Pembayaran</title>
  <style>
    body { font-family:Poppins; background:#f5f6fa; }
    .card {
      background:#fff;
      padding:20px;
      margin-bottom:20px;
      border-radius:14px;
      box-shadow:0 10px 30px rgba(0,0,0,.1);
    }
    img { max-width:260px; border-radius:10px; }
    button { padding:10px 18px; margin-right:8px; }
  </style>
</head>

<body>

<h2>Riwayat Pembayaran (Menunggu Verifikasi)</h2>

<?php if ($q->num_rows == 0): ?>
  <p>Tidak ada pembayaran menunggu verifikasi.</p>
<?php endif; ?>

<?php while($row = $q->fetch_assoc()): ?>
  <div class="card">
    <h3><?= htmlspecialchars($row['property_name']) ?></h3>
    <p>User: <?= htmlspecialchars($row['user_name']) ?></p>
    <p>Unit: <?= htmlspecialchars($row['unit_type']) ?></p>
    <p>Nominal: Rp <?= number_format($row['amount'],0,',','.') ?></p>

    <img src="uploads/<?= $row['proof_file'] ?>" alt="Bukti Transfer">

    <form method="POST" action="admin_verify.php">
      <input type="hidden" name="payment_id" value="<?= $row['id'] ?>">
      <input type="hidden" name="booking_id" value="<?= $row['booking_id'] ?>">

      <button name="action" value="approve">Setujui</button>
      <button name="action" value="reject">Tolak</button>
    </form>
  </div>
<?php endwhile; ?>

</body>
</html>
