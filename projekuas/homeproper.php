<?php
include "database.php";
session_start();

$message = "";
$mode = $_GET['mode'] ?? 'add';
$id_property = isset($_GET['id']) ? intval($_GET['id']) : null;

// ambil data lama klo mo edit
$data = [];
$panorama = [];
$fasilitas = [];
$spec = [];
$denahMedia = null;

if ($mode === 'edit' && $id_property) {
    $res = $koneksi->query("SELECT * FROM property WHERE id='$id_property'");
    $data = $res->fetch_assoc() ?? [];

    $res2 = $koneksi->query("SELECT * FROM property_media WHERE id_property='$id_property'");
    while ($row = $res2->fetch_assoc()) {
        if ($row['type'] === 'PANORAMA') $panorama[] = $row;
        if ($row['type'] === 'FASILITAS') $fasilitas[] = $row;
        if ($row['type'] === 'SPEC') $spec[] = $row;
        if ($row['type'] === 'DENAH') $denahMedia = $row;
    }
}

// ambil data dari form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = $_POST['nama'] ?? '';
    $lokasi = $_POST['lokasi'] ?? '';
    $harga = str_replace('.', '', $_POST['harga'] ?? '');
    $jumlah_unit = $_POST['jumlah_unit'] ?? 0;
    $kategori = $_POST['kategori'] ?? '';
    $kode = $_POST['kode'] ?? '';
    $deskripsi = $_POST['deskripsi'] ?? '';
    $gmaps = $_POST['gmaps'] ?? '';
    $status = $_POST['status'] ?? 'Tersedia';
    $denah_desc = $_POST['denah_desc'] ?? '';

    // simpan media ke folder uploads
    function uploadFile($fileInput){
        if(!$fileInput || !isset($fileInput['name']) || $fileInput['name'] === '') return null;
        $filename = time() . "_" . preg_replace('/[^A-Za-z0-9_\-\.]/','_', $fileInput['name']);
        // pastikan folder uploads ada
        if(!is_dir("uploads")) mkdir("uploads", 0755, true);
        move_uploaded_file($fileInput['tmp_name'], "uploads/" . $filename);
        return $filename;
    }

    $coverName = uploadFile($_FILES['cover'] ?? ['name'=>'','tmp_name'=>'']);
    $denahName = uploadFile($_FILES['denah'] ?? ['name'=>'','tmp_name'=>'']);
    $videoName = uploadFile($_FILES['video'] ?? ['name'=>'','tmp_name'=>'']);

    // insert / update data property
    if ($mode === 'add') {
        $sql = "INSERT INTO property (type, cover, harga, status, nama, lokasi, jumlah_unit, kategori, kode, deskripsi, gmaps, denah, video)
        VALUES ('RUMAH', '".$koneksi->real_escape_string($coverName)."', '".$koneksi->real_escape_string($harga)."', '".$koneksi->real_escape_string($status)."', '".$koneksi->real_escape_string($nama)."', '".$koneksi->real_escape_string($lokasi)."', '".$koneksi->real_escape_string($jumlah_unit)."', '".$koneksi->real_escape_string($kategori)."', '".$koneksi->real_escape_string($kode)."', '".$koneksi->real_escape_string($deskripsi)."', '".$koneksi->real_escape_string($gmaps)."', '".$koneksi->real_escape_string($denahName)."', '".$koneksi->real_escape_string($videoName)."')";
        $koneksi->query($sql);
        $id_property = $koneksi->insert_id;
    } else {
        $updateSQL = "UPDATE property SET nama='". $koneksi->real_escape_string($nama) ."', lokasi='". $koneksi->real_escape_string($lokasi) ."', harga='". $koneksi->real_escape_string($harga) ."', jumlah_unit='". $koneksi->real_escape_string($jumlah_unit) ."', kategori='". $koneksi->real_escape_string($kategori) ."', kode='". $koneksi->real_escape_string($kode) ."', deskripsi='". $koneksi->real_escape_string($deskripsi) ."', gmaps='". $koneksi->real_escape_string($gmaps) ."', status='". $koneksi->real_escape_string($status) ."'";
        if ($coverName) $updateSQL .= ", cover='".$koneksi->real_escape_string($coverName)."'";
        if ($denahName) $updateSQL .= ", denah='".$koneksi->real_escape_string($denahName)."'";
        if ($videoName) $updateSQL .= ", video='".$koneksi->real_escape_string($videoName)."'";
        $updateSQL .= " WHERE id='$id_property'";
        $koneksi->query($updateSQL);
    }

    // === SIMPAN / UPDATE DESKRIPSI DENAH ke property_media (type DENAH) ===
    $denahFile = $denahName ?: ($data['denah'] ?? '');

    if ($denahFile !== '') {
        $idPropEsc = intval($id_property);
        $fileEsc   = $koneksi->real_escape_string($denahFile);
        $descEsc   = $koneksi->real_escape_string($denah_desc);

        $cek = $koneksi->query("SELECT id FROM property_media WHERE id_property=$idPropEsc AND type='DENAH' LIMIT 1");
        if ($cek && ($row = $cek->fetch_assoc())) {
            $idDenah = intval($row['id']);
            $koneksi->query("UPDATE property_media SET file='$fileEsc', deskripsi='$descEsc' WHERE id=$idDenah");
        } else {
            $koneksi->query("INSERT INTO property_media (id_property,type,file,deskripsi)
                            VALUES ($idPropEsc,'DENAH','$fileEsc','$descEsc')");
        }
    }

    // helper menyimpan file ke property_media
    function saveFile($file, $type, $id_property, $koneksi, $nama=null, $desc=null){
        if(!$file || !isset($file['name']) || $file['name']=='') return;
        $filename = time() . "_" . preg_replace('/[^A-Za-z0-9_\-\.]/','_', $file['name']);
        if(!is_dir("uploads")) mkdir("uploads", 0755, true);
        move_uploaded_file($file['tmp_name'], "uploads/" . $filename);
        $namaEsc = $koneksi->real_escape_string($nama ?? '');
        $descEsc = $koneksi->real_escape_string($desc ?? '');
        $koneksi->query("INSERT INTO property_media (id_property,type,file,nama,deskripsi) VALUES ('".$koneksi->real_escape_string($id_property)."','".$koneksi->real_escape_string($type)."','".$koneksi->real_escape_string($filename)."','".$namaEsc."','".$descEsc."')");
    }

    // hapus media yang ditandai untuk dihapus
    if (!empty($_POST['deleted_media_id'])) {
        foreach ($_POST['deleted_media_id'] as $delId) {
            $delId = intval($delId);
            $koneksi->query("DELETE FROM property_media WHERE id=$delId");
        }
    }

    // upload fasilitas baru (files)
    if (!empty($_FILES['fasilitas']['name'])) {
        foreach ($_FILES['fasilitas']['name'] as $i => $fn) {
            if ($fn == '') continue;
            $file = ['name' => $fn, 'tmp_name' => $_FILES['fasilitas']['tmp_name'][$i]];
            saveFile($file,'FASILITAS',$id_property,$koneksi);
        }
    }

    // tambah panorama baru (text inputs panorama_link[])
    if (!empty($_POST['panorama_link'])) {
        foreach ($_POST['panorama_link'] as $link) {
            if (trim($link) == '') continue;
            $linkEsc = $koneksi->real_escape_string($link);
            $koneksi->query("INSERT INTO property_media (id_property,type,file) VALUES ('".$koneksi->real_escape_string($id_property)."','PANORAMA','".$linkEsc."')");
        }
    }

    // update spec lama (spec_id[], spec_nama[], spec_desc[], optional file spec[] for replace)
    if (!empty($_POST['spec_id'])) {
        foreach ($_POST['spec_id'] as $i => $sid) {
            $sidInt = intval($sid);
            $name = $_POST['spec_nama'][$i] ?? '';
            $desc = $_POST['spec_desc'][$i] ?? '';
            $fileNew = $_FILES['spec']['tmp_name'][$i] ?? null;
            if ($fileNew && !empty($_FILES['spec']['name'][$i])) {
                $filename = uploadFile(['name'=>$_FILES['spec']['name'][$i],'tmp_name'=>$fileNew]);
                $nameEsc = $koneksi->real_escape_string($name);
                $descEsc = $koneksi->real_escape_string($desc);
                $koneksi->query("UPDATE property_media SET file='".$koneksi->real_escape_string($filename)."', nama='".$nameEsc."', deskripsi='".$descEsc."' WHERE id='$sidInt'");
            } else {
                $nameEsc = $koneksi->real_escape_string($name);
                $descEsc = $koneksi->real_escape_string($desc);
                $koneksi->query("UPDATE property_media SET nama='".$nameEsc."', deskripsi='".$descEsc."' WHERE id='$sidInt'");
            }
        }
    }

    // spec baru (spec_baru[] + spec_nama_new[], spec_desc_new[])
    if (!empty($_FILES['spec_baru']['name'])) {
        foreach ($_FILES['spec_baru']['name'] as $i => $fn) {
            if ($fn == '') continue;
            $file = ['name' => $fn, 'tmp_name' => $_FILES['spec_baru']['tmp_name'][$i]];
            $namaSpec = $_POST['spec_nama_new'][$i] ?? null;
            $descSpec = $_POST['spec_desc_new'][$i] ?? null;
            saveFile($file,'SPEC',$id_property,$koneksi,$namaSpec,$descSpec);
        }
    }

    header("Location: dataproper.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $mode==='edit'?'Edit':'Tambah' ?> Rumah - PropertyKu</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.4.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
:root{--primary:#5c6a65;--primary-soft:#EAF1FF;--bg:#F4F6F8;--text-main:#1F2937;--text-soft:#6B7280;--card:#FFFFFF;--border-soft:#E5E7EB;}
*{margin:0;padding:0;box-sizing:border-box;font-family:"Poppins",sans-serif;}
body{background:var(--bg);padding:40px;}
.app{background:var(--card);border-radius:32px;box-shadow:0 18px 45px rgba(0,0,0,.08);display:flex;overflow:hidden;min-height:600px;}
.sidebar{width:230px;background:#fff;border-right:1px solid var(--border-soft);padding:28px 22px;display:flex;flex-direction:column;z-index:100;}
.profile{display:flex;align-items:center;gap:15px;margin-bottom:10px;flex-direction:column;text-align:center;margin-top:10px;}
.profile-avatar{width:54px;height:54px;border-radius:16px;background-image:url("profile.jpeg");background-size:cover;background-position:center;background-repeat:no-repeat;}
.profile-info h3{font-size:12px;font-weight:600;color:var(--primary);margin:0;}
.profile-info span{font-size:10px;color:var(--text-soft);display:block;margin-top:4px;}
.sidebar-title{font-size:13px;font-weight:500;text-transform:uppercase;margin:18px 0 8px;color:var(--text-soft);}
.menu{list-style:none;}
.menu li a{display:flex;align-items:center;gap:10px;padding:10px 12px;margin-bottom:5px;text-decoration:none;font-size:14px;color:var(--text-soft);border-radius:12px;}
.menu li a i{font-size:18px;}
.menu li a.active,.menu li a:hover{background:var(--primary-soft);color:var(--primary);}
.main{flex:1.4;background:#F7F8FA;padding:32px;min-width:0;}
.form-box{background:#fff;padding:30px;border-radius:20px;box-shadow:0 8px 22px rgba(0,0,0,.04);width:100%;}
.form-box h2{margin-bottom:6px;font-size:24px;color:var(--text-main);}
.form-box p{margin-bottom:26px;color:var(--text-soft);font-size:14px;}
.form-row{display:flex;gap:20px;margin-bottom:20px;flex-wrap:wrap;}
.form-col{flex:1;display:flex;flex-direction:column;min-width:240px;}
label{font-size:14px;margin-bottom:6px;color:var(--text-main);}
input,textarea,select{padding:12px 14px;border-radius:12px;border:1px solid var(--border-soft);font-size:14px;background:#fff;width:100%;}
textarea{resize:none;height:110px;width:100%;}
.upload-box{width:100%;max-width:240px;height:140px;border-radius:14px;border:2px dashed var(--border-soft);display:flex;justify-content:center;align-items:center;color:var(--text-soft);font-size:13px;margin-bottom:10px;overflow:hidden;}
.upload-box img{width:100%;height:100%;object-fit:cover;}
.btn-row{margin-top:25px;display:flex;gap:12px;}
.btn{padding:12px 20px;border-radius:12px;border:none;font-size:14px;cursor:pointer;}
.btn-save{background:var(--primary);color:#fff;}
.btn-cancel{background:#fff;border:1px solid var(--primary);color:var(--primary);}
.section-title{font-size:16px;font-weight:600;margin:25px 0 10px;color:var(--text-main);}
.upload-wide{width:100%;height:400px;border-radius:14px;border:2px dashed var(--border-soft);display:flex;justify-content:center;align-items:center;overflow:hidden;margin-bottom:10px;}
.upload-wide img,.upload-wide video{width:100%;height:100%;object-fit:cover;}
.facility-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:12px;}
.facility-grid img{width:100%;height:90px;border-radius:10px;object-fit:cover;}
.spec-box{background:#fff;padding:20px;border-radius:16px;box-shadow:0 0 10px rgba(0,0,0,.04);margin-bottom:20px;}
.spec-row{display:flex;gap:12px;margin-bottom:8px;}
.spec-row input{flex:1;}
.add-btn{background:var(--primary-soft);padding:8px 16px;border-radius:8px;cursor:pointer;font-size:13px;color:var(--primary);border:none;margin-top:8px;}
.add-btn:hover{background:#DEE8FF;}
.small-note{font-size:12px;color:#6B7280;margin-top:6px;}
.file-item{position:relative;display:block;border-radius:12px;overflow:hidden;background:#fff;border:1px solid var(--border-soft);margin-bottom:10px;}
.file-thumb{width:100%;height:150px;object-fit:cover;display:block;}
.file-item .inner{padding:8px;}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;}
.delete-btn{position:absolute;top:8px;right:8px;background:rgba(31,41,55,.8);color:#fff;width:28px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:50%;cursor:pointer;font-weight:700;border:none;}
.media-list{display:flex;flex-direction:column;gap:10px;}
.media-row{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#fff;border-radius:12px;border:1px solid var(--border-soft);}
.media-text{font-size:14px;color:var(--text-main);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;}
.btn-x{width:28px;height:28px;border-radius:50%;background:rgba(107,114,128,.75);color:#fff;border:none;cursor:pointer;font-size:18px;font-weight:700;line-height:28px;text-align:center;margin-left:8px;}
.panorama-field{display:flex;align-items:center;gap:10px;margin-top:8px;}
.sidebar-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;}
.sidebar-top-title{font-size:18px;color:var(--text-main);margin:0;line-height:1;}
.hamburger{background:var(--primary-soft);border:none;border-radius:12px;padding:6px 8px;cursor:pointer;color:var(--primary);font-size:18px;display:inline-flex;align-items:center;justify-content:center;}
.sidebar.collapsed{width:60px;padding:18px 8px;}
.sidebar.collapsed .profile,.sidebar.collapsed .sidebar-title,.sidebar.collapsed .menu{display:none;}
.sidebar.collapsed .sidebar-top-title{display:none;}
.sidebar.collapsed .sidebar-top{justify-content:center;}
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);opacity:0;pointer-events:none;transition:.2s;z-index:90;}
.mobile-topbar{display:none;align-items:center;gap:12px;padding:8px 10px;background:rgba(244,246,248,.9);border-radius:16px;margin-bottom:12px;backdrop-filter:blur(6px);top:10px;z-index:50;}

@media (max-width:600px){
body{padding:12px;}
.app{border-radius:24px;}
.sidebar{position:fixed;left:0;top:0;bottom:0;width:260px;transform:translateX(-105%);transition:.25s;box-shadow:0 18px 45px rgba(0,0,0,.18);}
.sidebar.open{transform:translateX(0);}
.overlay.show{opacity:1;pointer-events:auto;}
.main{padding:14px;}
.form-box h2{font-size:20px;}
.form-box p{font-size:13px;}
.upload-box{max-width:100%;height:150px;}
.upload-wide{height:220px;}
.grid{grid-template-columns:1fr;}
.btn{width:100%;}
.mobile-topbar{display:flex;}
.sidebar .hamburger{display:none;}
}
</style>
</head>
<body>
<div id="overlay" class="overlay"></div>
<div class="app">
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-top">
            <h4 class="sidebar-top-title">Dashboard</h4>
            <button id="toggleSidebar" class="hamburger" type="button">
                <i class="ri-menu-line"></i>
            </button>
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
                <li><a class="active" href="homeproper.php"><i class="ri-home-6-line"></i> Rumah</a></li>
                <li><a href="apartproper.php"><i class="ri-building-4-line"></i> Apartemen</a></li>
                <li><a href="dataproper.php"><i class="ri-database-2-line"></i> Data Properti</a></li>
                <li><a href="survey.php"><i class="ri-send-plane-line"></i> Survey</a></li>
                <li><a href="message.php"><i class="ri-mail-line"></i> Messages</a></li>
                <li><a href="riwayatadmin.php"><i class="ri-file-list-3-line"></i> Riwayat</a></li>
            </ul>
            <div class="sidebar-title">Akun</div>
            <ul class="menu">
                <li><a href="logout.php"><i class="ri-logout-circle-r-line"></i> Logout</a></li>
            </ul>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main">
        <!-- TOPBAR MOBILE -->
        <div class="mobile-topbar">
            <button id="mobileHamburger" class="hamburger" type="button" aria-label="Menu">
                <i class="ri-menu-line"></i>
            </button>
            <div class="mobile-topbar-title">Rumah</div>
        </div>
        <div class="form-box">
            <h2><?= $mode==='edit'?'Edit':'Tambah' ?> Data Rumah</h2>
            <p>Isi informasi lengkap mengenai rumah yang ingin ditambahkan.</p>
            <form method="POST" enctype="multipart/form-data" id="propertyForm">
                <div class="form-row">
                    <div class="form-col">
                        <label>Foto Rumah</label>
                        <div class="upload-box">
                            <?php if(!empty($data['cover'])): ?>
                                <img id="preview" src="uploads/<?= htmlspecialchars($data['cover']) ?>" class="file-thumb" alt="">
                            <?php else: ?>
                                <img id="preview" style="display:none;" alt="">
                            <?php endif; ?>
                        </div>
                        <input type="file" name="cover" accept="image/*"onchange="previewMedia(event, 'preview')"<?= $mode==='add' ? 'required' : '' ?>>
                    </div>

                    <div class="form-col">
                        <label>Nama Rumah</label>
                        <input type="text" name="nama" placeholder="Contoh: Emerald Residence" required value="<?= htmlspecialchars($data['nama'] ?? '') ?>">

                        <label>Lokasi</label>
                        <input type="text" name="lokasi" placeholder="Contoh: Surabaya" required value="<?= htmlspecialchars($data['lokasi'] ?? '') ?>">

                        <label>Jumlah Unit</label>
                        <input type="number" name="jumlah_unit" placeholder="Contoh: 12" required value="<?= htmlspecialchars($data['jumlah_unit'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <label>Harga</label>
                        <input type="text" id="harga" name="harga" placeholder="Contoh: 850.000.000" required value="<?= isset($data['harga']) ? number_format($data['harga'], 0, ',', '.') : '' ?>"oninput="formatHarga(this)">
                    </div>

                    <div class="form-col">
                        <label>Kategori</label>
                        <select name="kategori">
                            <?php
                            $kategori_options = ['Cluster','Rumah Subsidi','Rumah Mewah'];
                            foreach($kategori_options as $k){
                                $sel = ($data['kategori'] ?? '')==$k ? 'selected' : '';
                                echo "<option value=\"".htmlspecialchars($k)."\" $sel>".htmlspecialchars($k)."</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <!-- Status & Kode -->
                <div class="form-row">
                    <div class="form-col">
                        <label>Status Unit</label>
                        <select name="status">
                            <?php
                            $status_options = ['Tersedia','Terjual'];
                            foreach($status_options as $s){
                                $sel = ($data['status'] ?? '')==$s ? 'selected' : '';
                                echo "<option value=\"".htmlspecialchars($s)."\" $sel>".htmlspecialchars($s)."</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-col">
                        <label>Kode Unit (opsional)</label>
                        <input type="text" name="kode" placeholder="Contoh: A-12 / Unit 05" value="<?= htmlspecialchars($data['kode'] ?? '') ?>">
                    </div>
                </div>

                <!-- Deskripsi -->
                <label>Deskripsi Rumah</label><br>
                <textarea name="deskripsi" placeholder="Tuliskan deskripsi singkat rumah" required><?= htmlspecialchars($data['deskripsi'] ?? '') ?></textarea>

                <!-- Denah -->
                <h3 class="section-title">Denah Rumah</h3>
                <div class="upload-wide">
                    <?php if(!empty($data['denah'])): ?>
                        <img id="previewDenah" src="uploads/<?= htmlspecialchars($data['denah']) ?>" alt="">
                    <?php else: ?>
                        <img id="previewDenah" style="display:none;" alt="">
                    <?php endif; ?>
                </div>
                <input type="file" name="denah" accept="image/*" onchange="previewMedia(event, 'previewDenah')"<?= $mode==='add' ? 'required' : '' ?>>
                <label>Deskripsi Denah</label><br>
                <textarea name="denah_desc" placeholder="Contoh: Total Area: 900 m² Bedrooms: 2 Bathrooms: 1 Floors: 2" style="height:90px;"><?= htmlspecialchars($denahMedia['deskripsi'] ?? '') ?></textarea>

                <!-- Video -->
                <h3 class="section-title">Video Bangunan Rumah</h3>
                <div class="upload-wide">
                    <?php if(!empty($data['video'])): ?>
                        <video id="previewVideo" src="uploads/<?= htmlspecialchars($data['video']) ?>" controls></video>
                    <?php else: ?>
                        <video id="previewVideo" autoplay muted loop></video>
                    <?php endif; ?>
                </div>
                <input type="file" name="video" accept="video/*" onchange="previewMedia(event, 'previewVideo')" <?= $mode==='add' ? 'required' : '' ?>>

                <!-- Panorama -->
                <h3 class="section-title">Panorama 360° Interior</h3>
                <div id="panoramaBox" class="media-list">
                    <?php if(!empty($panorama)): ?>
                        <?php foreach($panorama as $p): ?>
                            <div class="media-row" id="media-<?= intval($p['id']) ?>">
                                <!-- Teks link -->
                                <a href="<?= htmlspecialchars($p['file']) ?>" target="_blank" class="media-text"><?= htmlspecialchars($p['file']) ?></a>
                                <!-- Tombol hapus -->
                                <button type="button" class="btn-x" onclick="confirmDelete(<?= intval($p['id']) ?>, <?= intval($id_property) ?>)">×</button>
                            </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                            <div class="panorama-field">
                                <input type="text" name="panorama_link[]" placeholder="Link Panorama 360°">
                                <button type="button" class="btn-x" onclick="this.parentElement.remove()">×</button>
                            </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="add-btn" onclick="addPanoramaField()">+ Tambah Panorama</button>

                <!-- Fasilitas -->
                <h3 class="section-title">Fasilitas Unit</h3>
                <div id="facilityGrid" class="grid">
                    <?php if(!empty($fasilitas)): ?>
                        <?php foreach($fasilitas as $f): ?>
                            <div class="file-item" id="media-<?= intval($f['id']) ?>">
                                <img src="uploads/<?= htmlspecialchars($f['file']) ?>" class="file-thumb" alt="">
                                <button type="button" class="delete-btn" title="Hapus" onclick="confirmDelete(<?= intval($f['id']) ?>, <?= intval($id_property) ?>)">×</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <button type="button" class="add-btn" onclick="document.getElementById('facilityInput').click()">+ Tambah Foto Fasilitas</button>
                <input type="file" id="facilityInput" name="fasilitas[]" accept="image/*" multiple hidden onchange="previewFacilities(event)">

                <!-- GMaps -->
                <h3 class="section-title">Lokasi (Google Maps)</h3>
                <input type="text" name="gmaps" placeholder="Link Google Maps" value="<?= htmlspecialchars($data['gmaps'] ?? '') ?>">

                <!-- Unit Specification (SPEC) -->
                <h3 class="section-title">Fasilitas Rumah</h3>
                <div id="specGrid" class="grid">
                    <?php if(!empty($spec)): ?>
                        <?php foreach($spec as $s): ?>
                            <div class="file-item" id="media-<?= intval($s['id']) ?>">
                                <?php if(!empty($s['file']) && $s['type'] !== 'PANORAMA'): ?>
                                    <img id="spec_old_<?= $s['id'] ?>"
                                        src="uploads/<?= htmlspecialchars($s['file']) ?>" 
                                        class="file-thumb">
                                <?php endif; ?>
                                <button class="delete-btn" title="Hapus"onclick="confirmDelete(<?= intval($s['id']) ?>, <?= intval($id_property) ?>)">×</button>
                                <div class="inner">
                                    <input type="hidden" name="spec_id[]" value="<?= intval($s['id']) ?>">
                                    <div style="margin-top:8px;"><input type="file" name="spec[]" onchange="previewMedia(event, 'spec_old_<?= $s['id'] ?>')"></div>
                                    <div style="margin-top:8px;"><label>Nama Kategori</label><input type="text" name="spec_nama[]" value="<?= htmlspecialchars($s['nama']) ?>"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div id="spec_box"></div>
                <button type="button" class="add-btn" onclick="addSpec()">+ Tambah Kategori</button>

                <div class="btn-row">
                    <a class="btn btn-cancel" href="<?= ($mode === 'edit') ? 'dataproper.php' : 'homeproper.php' ?>">Batal</a>
                    <button type="submit" class="btn btn-save"><?= $mode==='edit'?'Simpan Perubahan':'Simpan Data' ?></button>
                </div>

            </form>
        </div>
    </main>
</div>

<script>
    function formatHarga(input) {
        let angka = input.value.replace(/[^0-9]/g, ""); 
        input.value = angka.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }
    function previewMedia(event, targetId) {
        const file = event.target.files[0];
        if (!file) return;
        const target = document.getElementById(targetId);
        if (!target) return;
        target.src = URL.createObjectURL(file);
        target.style.display = "block";
    }
    function previewFacilities(e) {
        const container = document.getElementById("facilityGrid");
        Array.from(e.target.files).forEach(file => {
            const id = "facility_new_" + Math.random().toString(36).substr(2, 9);
            const wrapper = document.createElement("div");
            wrapper.className = "file-item";
            wrapper.innerHTML = `<img id="${id}" class="file-thumb"><button type="button" class="delete-btn" onclick="this.parentElement.remove()">×</button>`;
            container.appendChild(wrapper);
            document.getElementById(id).src = URL.createObjectURL(file);
        });
    }
    function addPanoramaField() {
    const box = document.getElementById("panoramaBox");
    const div = document.createElement("div");
    div.className = "panorama-field";
    div.innerHTML = `
        <input type="text" name="panorama_link[]" placeholder="Link Panorama 360°">
        <button type="button" class="btn-x" onclick="this.parentElement.remove()">×</button>
    `;
    box.appendChild(div);
    }
    function addSpec() {
        const box = document.getElementById("spec_box");
        const id = "spec_preview_" + Math.random().toString(36).substr(2, 9);
        const div = document.createElement("div");
        div.classList.add('spec-box');
        div.innerHTML = `
            <label>Nama Kategori</label>
            <input type="text" name="spec_nama_new[]" placeholder="Contoh: Lantai"><br><br>
            <div class="upload-wide"><img id="${id}" style="display:none;"></div>
            <input type="file" name="spec_baru[]" accept="image/*" onchange="previewMedia(event, '${id}')"><br>
        `;
        box.appendChild(div);
    }
    function confirmDelete(mediaId){
        const el = document.getElementById('media-' + mediaId);
        if(el) el.remove();
        const del = document.createElement('input');
        del.type = 'hidden';
        del.name = 'deleted_media_id[]';
        del.value = mediaId;
        document.getElementById('spec_box').appendChild(del);
    }
    document.addEventListener("DOMContentLoaded", function () {
        const sidebar = document.querySelector(".sidebar");
        const toggleBtn = document.getElementById("toggleSidebar"); // desktop
        const mobileBtn = document.getElementById("mobileHamburger"); // mobile topbar
        const overlay = document.getElementById("overlay");
        function isMobile(){ return window.matchMedia("(max-width: 600px)").matches; }
        function openSidebar(){
            sidebar.classList.add("open");
            overlay.classList.add("show");
            document.body.style.overflow = "hidden";
        }
        function closeSidebar(){
            sidebar.classList.remove("open");
            overlay.classList.remove("show");
            document.body.style.overflow = "";
        }
        function toggleSidebar(){
            if (isMobile()){
            sidebar.classList.contains("open") ? closeSidebar() : openSidebar();
            } else {
            sidebar.classList.toggle("collapsed");
            }
        }
        if (toggleBtn) toggleBtn.addEventListener("click", toggleSidebar);
        if (mobileBtn) mobileBtn.addEventListener("click", toggleSidebar);
        overlay.addEventListener("click", closeSidebar);
        window.addEventListener("resize", () => {
            if (!isMobile()) closeSidebar();
        });
    });
</script>
</body>
</html>
