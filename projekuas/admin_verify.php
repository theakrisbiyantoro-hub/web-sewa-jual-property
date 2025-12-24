<?php
session_start();
include "database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  exit("Unauthorized");
}

$payment_id = (int)$_POST['payment_id'];
$booking_id = (int)$_POST['booking_id'];
$action = $_POST['action'];

if ($action === 'approve') {
  $koneksi->query("
    UPDATE booking_payments 
    SET status='verified', verified_at=NOW(), verified_by={$_SESSION['user_id']}
    WHERE id=$payment_id
  ");

  $koneksi->query("
    UPDATE booking 
    SET status='verified'
    WHERE id=$booking_id
  ");

} elseif ($action === 'reject') {
  $koneksi->query("
    UPDATE booking_payments 
    SET status='rejected'
    WHERE id=$payment_id
  ");

  $koneksi->query("
    UPDATE booking 
    SET status='wait_payment'
    WHERE id=$booking_id
  ");
}

header("Location: admin_riwayat.php");
exit;
