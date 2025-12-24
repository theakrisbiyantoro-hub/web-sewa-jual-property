<?php
include "database.php";
session_start();
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") die("Akses ditolak!");

// --- HAPUS DATA ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $koneksi->query("DELETE FROM properti WHERE id='$id'");
    $_SESSION['mes'] = "Properti berhasil dihapus!";
    header("Location: admin.php");
    exit;
}

// --- AMBIL DATA UNTUK EDIT ---
$editData = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $editData = $koneksi->query("SELECT * FROM properti WHERE id='$id'")->fetch_assoc();
}

// --- PROSES FORM SUBMIT ---
if (isset($_POST["submit"])) {
    $id_edit = $_POST['id_edit'] ?? null;

    // Ambil data umum
    $nama = $_POST["nama"];
    $harga = str_replace('.','',$_POST["harga"]); // hapus titik sebelum simpan
    $alamat = $_POST["alamat"];
    $gmaps = $_POST["gmaps"];
    $tipe = $_POST["tipe"];
    $luas = $_POST["luas"];
    $deskripsi = $_POST["deskripsi"];
    $status = $_POST["status"];

    // APT
    $jenis_apt = $_POST["jenis_apt"] ?? NULL;
    $lantai_apt = $_POST["lantai_apt"] ?? NULL;

    // RUMAH
    $tipe_rmh = $_POST["tipe_rmh"] ?? NULL;
    $kamar = $_POST["kamar"] ?? NULL;
    $kamar_mandi = $_POST["kamar_mandi"] ?? NULL;
    $lantai_rmh = $_POST["lantai_rmh"] ?? NULL;

    // Upload cover
    $cover_name = NULL;
    if (!empty($_FILES["cover"]["name"])) {
        $folder = "uploads/";
        if (!is_dir($folder)) mkdir($folder);
        $cover_name = time() . "_" . $_FILES["cover"]["name"];
        move_uploaded_file($_FILES["cover"]["tmp_name"], $folder . $cover_name);
    } else if ($id_edit) {
        $cover_name = $editData['cover']; 
    }

    if ($id_edit) {
        // UPDATE
        $query = "UPDATE properti SET
            cover='$cover_name', nama='$nama', harga='$harga', alamat='$alamat', gmaps='$gmaps', tipe='$tipe',
            jenis_apt=" . ($jenis_apt ? "'$jenis_apt'" : "NULL") . ",
            lantai_apt=" . ($lantai_apt ? "'$lantai_apt'" : "NULL") . ",
            tipe_rmh=" . ($tipe_rmh ? "'$tipe_rmh'" : "NULL") . ",
            kamar=" . ($kamar ? "'$kamar'" : "NULL") . ",
            kamar_mandi=" . ($kamar_mandi ? "'$kamar_mandi'" : "NULL") . ",
            lantai_rmh=" . ($lantai_rmh ? "'$lantai_rmh'" : "NULL") . ",
            luas='$luas', deskripsi='$deskripsi', status='$status'
            WHERE id='$id_edit'";
        if($koneksi->query($query)){
            $_SESSION['mes'] = "Properti berhasil diupdate!";
        } else {
            $_SESSION['mes'] = "Gagal update: " . $koneksi->error;
        }
    } else {
        // INSERT
        $query = "INSERT INTO properti 
        (cover, nama, harga, alamat, gmaps, tipe, jenis_apt, lantai_apt, tipe_rmh, kamar, kamar_mandi, lantai_rmh, luas, deskripsi, status)
        VALUES
        ('$cover_name','$nama','$harga','$alamat','$gmaps','$tipe',
         " . ($jenis_apt ? "'$jenis_apt'" : "NULL") . ",
         " . ($lantai_apt ? "'$lantai_apt'" : "NULL") . ",
         " . ($tipe_rmh ? "'$tipe_rmh'" : "NULL") . ",
         " . ($kamar ? "'$kamar'" : "NULL") . ",
         " . ($kamar_mandi ? "'$kamar_mandi'" : "NULL") . ",
         " . ($lantai_rmh ? "'$lantai_rmh'" : "NULL") . ",
         '$luas','$deskripsi','$status')";
        if ($koneksi->query($query)) {
            $properti_id = $koneksi->insert_id;
            // Upload fasilitas
            if (!empty($_FILES["fasilitas"]["name"][0])) {
                $total = count($_FILES["fasilitas"]["name"]);
                for ($i=0; $i<$total; $i++) {
                    $f_name = time() . "_" . $_FILES["fasilitas"]["name"][$i];
                    $f_path = "uploads/" . $f_name;
                    move_uploaded_file($_FILES["fasilitas"]["tmp_name"][$i], $f_path);
                    $koneksi->query("INSERT INTO fasilitas (properti_id,fasilitas) VALUES ('$properti_id','$f_name')");
                }
            }
            $_SESSION['mes'] = "Properti berhasil ditambah!";
        } else $_SESSION['mes'] = "Gagal: ".$koneksi->error;
    }

    // Kembali ke admin.php
    header("Location: admin.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= $editData ? "Edit Properti" : "Tambah Properti" ?></title>
</head>
<body>
<?php include 'header.html' ?>
<h2><?= $editData ? "Edit Properti" : "Tambah Properti" ?></h2>
<form action="" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="id_edit" value="<?= $editData['id'] ?? '' ?>">
    <label>Cover Properti:</label><br>
    <input type="file" name="cover"><br><br>
    <label>Nama Properti:</label><br>
    <input type="text" name="nama" value="<?= $editData['nama'] ?? '' ?>" required><br><br>
    <label>Harga:</label><br>
    <input type="text" name="harga" value="<?= isset($editData['harga']) ? number_format($editData['harga'],0,'.','.') : '' ?>" required><br><br>
    <label>Alamat:</label><br>
    <textarea name="alamat"><?= $editData['alamat'] ?? '' ?></textarea><br><br>
    <label>Link Google Maps:</label><br>
    <input type="text" name="gmaps" value="<?= $editData['gmaps'] ?? '' ?>"><br><br>
    <label>Tipe Properti:</label><br>
    <select name="tipe" id="tipe" onchange="ubahForm()" required>
        <option value="">-- Pilih --</option>
        <option value="Rumah" <?= (isset($editData) && $editData['tipe']=='Rumah')?'selected':'' ?>>Rumah</option>
        <option value="Apartement" <?= (isset($editData) && $editData['tipe']=='Apartement')?'selected':'' ?>>Apartement</option>
    </select><br><br>

    <!-- FORM APARTEMENT -->
    <div id="formApart" style="display:none">
        <label>Jenis Unit:</label><br>
        <select name="jenis_apt">
            <option <?= (isset($editData) && $editData['jenis_apt']=='Studio')?'selected':'' ?>>Studio</option>
            <option <?= (isset($editData) && $editData['jenis_apt']=='1 Bedroom')?'selected':'' ?>>1 Bedroom</option>
            <option <?= (isset($editData) && $editData['jenis_apt']=='2 Bedroom')?'selected':'' ?>>2 Bedroom</option>
            <option <?= (isset($editData) && $editData['jenis_apt']=='3 Bedroom')?'selected':'' ?>>3 Bedroom</option>
        </select><br><br>
        <label>Lantai Unit:</label><br>
        <input type="number" name="lantai_apt" value="<?= $editData['lantai_apt'] ?? '' ?>"><br><br>
        <label>Fasilitas:</label><br>
        <input type="file" name="fasilitas[]" multiple><br><br>
    </div>

    <!-- FORM RUMAH -->
    <div id="formRumah" style="display:none">
        <label>Type Rumah:</label><br>
        <select name="tipe_rmh">
            <option <?= (isset($editData) && $editData['tipe_rmh']=='Type 1')?'selected':'' ?>>Type 1</option>
            <option <?= (isset($editData) && $editData['tipe_rmh']=='Type 2')?'selected':'' ?>>Type 2</option>
            <option <?= (isset($editData) && $editData['tipe_rmh']=='Type 3')?'selected':'' ?>>Type 3</option>
            <option <?= (isset($editData) && $editData['tipe_rmh']=='Type 4')?'selected':'' ?>>Type 4</option>
        </select><br><br>
        <label>Jumlah Kamar:</label><br>
        <input type="number" name="kamar" value="<?= $editData['kamar'] ?? '' ?>"><br><br>
        <label>Jumlah Kamar Mandi:</label><br>
        <input type="number" name="kamar_mandi" value="<?= $editData['kamar_mandi'] ?? '' ?>"><br><br>
        <label>Jumlah Lantai:</label><br>
        <input type="number" name="lantai_rmh" value="<?= $editData['lantai_rmh'] ?? '' ?>"><br><br>
        <label>Fasilitas:</label><br>
        <input type="file" name="fasilitas[]" multiple><br><br>
    </div>

    <label>Luas Bangunan (m²):</label><br>
    <input type="number" name="luas" value="<?= $editData['luas'] ?? '' ?>"><br><br>
    <label>Deskripsi:</label><br>
    <textarea name="deskripsi"><?= $editData['deskripsi'] ?? '' ?></textarea><br><br>
    <label>Status:</label><br>
    <input type="radio" name="status" value="Tersedia" <?= (!isset($editData) || $editData['status']=='Tersedia')?'checked':'' ?>> Tersedia
    <input type="radio" name="status" value="Tidak Tersedia" <?= (isset($editData) && $editData['status']=='Tidak Tersedia')?'checked':'' ?>> Tidak Tersedia<br><br>

    <button type="submit" name="submit"><?= $editData ? "Update" : "Simpan" ?></button>
</form>

<script>
function ubahForm() {
    var tipe = document.getElementById("tipe").value;
    document.getElementById("formApart").style.display = "none";
    document.getElementById("formRumah").style.display = "none";
    if (tipe === "Apartement") document.getElementById("formApart").style.display = "block";
    if (tipe === "Rumah") document.getElementById("formRumah").style.display = "block";
}
// tampilkan form sesuai tipe saat edit
window.onload = function() { ubahForm(); };

// Auto-format harga pakai titik
const hargaInput = document.querySelector('input[name="harga"]');
hargaInput.addEventListener('input', function(){
    let val = this.value.replace(/\./g,'');
    this.value = val.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
});
</script>
</body>
</html>
