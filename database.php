<?php
$hostname = 'localhost';
$dbuser = 'root';
$dbpass = '';
$dbname = 'uas';

$koneksi = mysqli_connect($hostname, $dbuser, $dbpass, $dbname);

if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>