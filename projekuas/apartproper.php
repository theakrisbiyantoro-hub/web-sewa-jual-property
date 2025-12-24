<?php
include "database.php";
session_start();

$message = "";
$mode = $_GET['mode'] ?? 'add';
$id_property = isset($_GET['id']) ? intval($_GET['id']) : null;

// ambil data lama klo mo edit
$data = [];
$hargaTipe = [];
$panorama = [];
$fasilitas = [];
$spec = [];
$publicDomain = [];
$layout = [];
$denahMedia = null;

if ($mode === 'edit' && $id_property) {
    // ambil data property
    $res = $koneksi->query("SELECT * FROM property WHERE id='$id_property'");
    $data = $res->fetch_assoc() ?? [];

    // ambil media
    $res2 = $koneksi->query("SELECT * FROM property_media WHERE id_property='$id_property'");
    while ($row = $res2->fetch_assoc()) {
        if ($row['type'] === 'PANORAMA') $panorama[] = $row;
        if ($row['type'] === 'FASILITAS') $fasilitas[] = $row;
        if ($row['type'] === 'PUBLIC_DOMAIN') $publicDomain[] = $row;
        if ($row['type'] === 'UNIT_LAYOUT') $layout[] = $row;
        if ($row['type'] === 'SPEC') $spec[] = $row;
        if ($row['type'] === 'DENAH') $denahMedia = $row;
    }

    // ambil harga per tipe
    $resHarga = $koneksi->query("SELECT * FROM property_apart_harga WHERE id_property='$id_property' ORDER BY id ASC");
    if ($resHarga) $hargaTipe = $resHarga->fetch_all(MYSQLI_ASSOC);
}

// ===== Helper =====
function safeKey($s){
    $s = strtolower(trim((string)$s));
    $s = preg_replace('/[^a-z0-9]+/','_', $s);
    return trim($s, '_');
}
function normTipe($s){
    $s = strtolower(trim((string)$s));
    // hilangkan spasi, tanda baca umum
    $s = preg_replace('/\s+/', '', $s);
    $s = str_replace(['-','_','.'], '', $s);
    return $s;
}

$layoutByType = [];
if (!empty($layout)) {
    foreach ($layout as $l) {
        $tipe = trim($l['nama'] ?? '');
        if ($tipe === '') continue;
        if (!isset($layoutByType[$tipe]) || $l['id'] > $layoutByType[$tipe]['id']) {
            $layoutByType[$tipe] = $l;
        }
    }
}

$specByTypeAll = [];
if (!empty($spec)) {
    foreach ($spec as $s) {
        $tipe = trim((string)($s['nama'] ?? ''));
        if ($tipe === '') continue;
        if (!isset($specByTypeAll[$tipe])) $specByTypeAll[$tipe] = [];
        $specByTypeAll[$tipe][] = $s;
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
        VALUES ('APARTEMENT', '".$koneksi->real_escape_string($coverName)."', '".$koneksi->real_escape_string($harga)."', '".$koneksi->real_escape_string($status)."', '".$koneksi->real_escape_string($nama)."', '".$koneksi->real_escape_string($lokasi)."', '".$koneksi->real_escape_string($jumlah_unit)."', '".$koneksi->real_escape_string($kategori)."', '".$koneksi->real_escape_string($kode)."', '".$koneksi->real_escape_string($deskripsi)."', '".$koneksi->real_escape_string($gmaps)."', '".$koneksi->real_escape_string($denahName)."', '".$koneksi->real_escape_string($videoName)."')";
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

    // SIMPAN HARGA PER TIPE (property_apart_harga)
    $tipeArr  = $_POST['harga_tipe_nama'] ?? [];
    $hargaArr = $_POST['harga_tipe_nominal'] ?? [];

    $idPropEsc = intval($id_property);

    // reset dulu (biar edit gampang)
    $koneksi->query("DELETE FROM property_apart_harga WHERE id_property=$idPropEsc");
    $minHarga = null;
    for ($i = 0; $i < count($tipeArr); $i++) {
        $namaTipe = trim($tipeArr[$i] ?? '');
        $hargaRaw = $hargaArr[$i] ?? '';

        $hargaNominal = (int) str_replace('.', '', preg_replace('/[^0-9\.]/', '', $hargaRaw));
        if ($namaTipe === '' || $hargaNominal <= 0) continue;
        $namaEsc = $koneksi->real_escape_string($namaTipe);
        $koneksi->query("INSERT INTO property_apart_harga (id_property, nama_tipe, harga) VALUES ($idPropEsc, '$namaEsc', $hargaNominal)");
        if ($minHarga === null || $hargaNominal < $minHarga) $minHarga = $hargaNominal;
    }

    // set harga mulai dari = harga termurah
    if ($minHarga !== null) {
        $koneksi->query("UPDATE property SET harga=$minHarga WHERE id=$idPropEsc");
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
            $koneksi->query("INSERT INTO property_media (id_property,type,file,deskripsi) VALUES ($idPropEsc,'DENAH','$fileEsc','$descEsc')");
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

    // UPDATE PUBLIC DOMAIN LAMA
    if (!empty($_POST['pd_id'])) {
        foreach ($_POST['pd_id'] as $i => $pid) {
            $pid  = intval($pid);
            $nama = $koneksi->real_escape_string($_POST['pd_nama'][$i] ?? '');
            $hasNewFile = !empty($_FILES['pd_file']['name'][$i]);

            if ($hasNewFile) {
                $file = ['name' => $_FILES['pd_file']['name'][$i], 'tmp_name' => $_FILES['pd_file']['tmp_name'][$i]];
                $filename = uploadFile($file);
                $koneksi->query("UPDATE property_media SET file='$filename', nama='$nama' WHERE id='$pid'");
            } else {
                $koneksi->query("UPDATE property_media SET nama='$nama' WHERE id='$pid'");
            }
        }
    }

    // TAMBAH PUBLIC DOMAIN BARU (1 item = 1 nama + 1 file)
    if (!empty($_FILES['pd_file_new']['name'])) {
        foreach ($_FILES['pd_file_new']['name'] as $i => $fn) {
            if ($fn == '') continue;
            $file = ['name' => $fn, 'tmp_name' => $_FILES['pd_file_new']['tmp_name'][$i]];
            $namaBaru = $koneksi->real_escape_string($_POST['pd_nama_new'][$i] ?? '');
            saveFile($file, 'PUBLIC_DOMAIN', $id_property, $koneksi, $namaBaru, null);
        }
    }

    // UPDATE panorama existing
    if (!empty($_POST['panorama_id_existing'])) {
        foreach ($_POST['panorama_id_existing'] as $i => $idP) {
            $nama = $_POST['panorama_nama_existing'][$i] ?? "";
            $link = $_POST['panorama_link_existing'][$i] ?? "";
            if ($nama == "" || $link == "") continue;
            $namaEsc = $koneksi->real_escape_string($nama);
            $linkEsc = $koneksi->real_escape_string($link);
            $idPEsc = intval($idP);
            $koneksi->query("UPDATE property_media SET nama='$namaEsc', file='$linkEsc' WHERE id='$idPEsc'");
        }
    }

    // INSERT panorama baru
    if (!empty($_POST['panorama_nama'])) {
        foreach ($_POST['panorama_nama'] as $i => $namaBaru) {
            $linkBaru = $_POST['panorama_link'][$i] ?? "";
            if ($namaBaru == "" || $linkBaru == "") continue;
            $namaEsc = $koneksi->real_escape_string($namaBaru);
            $linkEsc = $koneksi->real_escape_string($linkBaru);
            $idPropEsc = intval($id_property);
            $koneksi->query("INSERT INTO property_media (id_property, type, nama, file) VALUES ('$idPropEsc', 'PANORAMA', '$namaEsc', '$linkEsc')");
        }
    }

    $existingTipeFromEdit = [];
    if (!empty($_POST['layout_nama'])) {
        foreach ($_POST['layout_nama'] as $ln) {
            $nk = normTipe($ln);
            if ($nk !== '') $existingTipeFromEdit[$nk] = true;
        }
    }

    // kalau ada layout yang dihapus, jangan dianggap existing lagi
    if (!empty($_POST['deleted_layout_tipe'])) {
        foreach ($_POST['deleted_layout_tipe'] as $tDel) {
            $nk = normTipe($tDel);
            if ($nk !== '') unset($existingTipeFromEdit[$nk]);
        }
    }
    
    // UPDATE UNIT LAYOUT LAMA
    if (!empty($_POST['layout_id'])) {
        foreach ($_POST['layout_id'] as $i => $lid) {
            $lid  = intval($lid);
            $nama = $koneksi->real_escape_string($_POST['layout_nama'][$i] ?? '');
            $desc = $koneksi->real_escape_string($_POST['layout_desc'][$i] ?? '');
            $hasNewFile = !empty($_FILES['layout_file']['name'][$i]);
            if ($hasNewFile) {
                $file = ['name' => $_FILES['layout_file']['name'][$i], 'tmp_name' => $_FILES['layout_file']['tmp_name'][$i]];
                $filename = uploadFile($file);
                $koneksi->query("UPDATE property_media SET file='$filename', nama='$nama', deskripsi='$desc' WHERE id='$lid'");
            } else {
                $koneksi->query("UPDATE property_media SET nama='$nama', deskripsi='$desc' WHERE id='$lid'");
            }
        }
    }

    // TAMBAH / UPSERT UNIT LAYOUT BARU (ANTI DOBEL PER TIPE)
    if (!empty($_FILES['layout_file_new']['name'])) {

        $idPropEsc = (int)$id_property;
        $stmtGet = $koneksi->prepare("SELECT id, nama FROM property_media WHERE id_property=? AND type='UNIT_LAYOUT'");
        $stmtGet->bind_param("i", $idPropEsc);
        $stmtGet->execute();
        $existingRows = $stmtGet->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtGet->close();

        // bikin map normalized nama -> id (kalau dobel sebelumnya, yang terakhir menang)
        $existingMap = [];
        foreach ($existingRows as $er) {
            $nk = normTipe($er['nama'] ?? '');
            if ($nk !== '') $existingMap[$nk] = (int)$er['id'];
        }

        // siapkan statement update / insert
        $stmtUpd = $koneksi->prepare("UPDATE property_media SET file=?, nama=?, deskripsi=? WHERE id=?");
        $stmtIns = $koneksi->prepare("INSERT INTO property_media (id_property, type, file, nama, deskripsi) VALUES (?, 'UNIT_LAYOUT', ?, ?, ?)");

        foreach ($_FILES['layout_file_new']['name'] as $i => $fn) {
            if ($fn == '') continue;

            $tipeRaw = trim($_POST['layout_nama_new'][$i] ?? '');
            $descRaw = $_POST['layout_desc_new'][$i] ?? '';

            // wajib ada tipe
            if ($tipeRaw === '') continue;

            // upload file (pakai fungsi uploadFile yang sudah kamu punya)
            $fileArr = [
                'name' => $fn,
                'tmp_name' => $_FILES['layout_file_new']['tmp_name'][$i]
            ];
            $filename = uploadFile($fileArr);
            if (!$filename) continue;

            $tipeNorm = normTipe($tipeRaw);
            if ($tipeNorm !== '' && isset($existingTipeFromEdit[$tipeNorm])) {
                continue;
            }
            $fileEsc = $filename;               // sudah aman karena uploadFile sanitasi nama
            $namaEsc = $tipeRaw;                // pakai prepared statement, aman
            $descEsc = $descRaw;

            // kalau sudah ada tipe ini -> UPDATE
            if ($tipeNorm !== '' && isset($existingMap[$tipeNorm])) {
                $existingId = (int)$existingMap[$tipeNorm];
                $stmtUpd->bind_param("sssi", $fileEsc, $namaEsc, $descEsc, $existingId);
                $stmtUpd->execute();
            } 
            // kalau belum ada -> INSERT
            else {
                $stmtIns->bind_param("isss", $idPropEsc, $fileEsc, $namaEsc, $descEsc);
                $stmtIns->execute();

                // update map supaya kalau ada input dobel dalam 1 submit, berikutnya jadi UPDATE
                $newId = (int)$koneksi->insert_id;
                if ($tipeNorm !== '') $existingMap[$tipeNorm] = $newId;
            }
        }

        $stmtUpd->close();
        $stmtIns->close();
    }

    // UPDATE SPEC LAMA
    if (!empty($_POST['spec_id'])) {
        foreach ($_POST['spec_id'] as $i => $sid) {
            $sid = intval($sid);
            $nama = $koneksi->real_escape_string($_POST['spec_nama'][$i] ?? '');
            $desc = $koneksi->real_escape_string($_POST['spec_desc'][$i] ?? '');
            $hasNewFile = !empty($_FILES['spec_file']['name'][$i]);
            if ($hasNewFile) {
                $file = ['name' => $_FILES['spec_file']['name'][$i], 'tmp_name' => $_FILES['spec_file']['tmp_name'][$i]];
                $filename = uploadFile($file);
                $koneksi->query("UPDATE property_media SET file='$filename', nama='$nama', deskripsi='$desc' WHERE id='$sid'");
            } else {
                $koneksi->query("UPDATE property_media SET nama='$nama', deskripsi='$desc' WHERE id='$sid'");
            }
        }
    }

    // TAMBAH SPEC BARU
    if (!empty($_FILES['spec_file_new']['name'])) {
        foreach ($_FILES['spec_file_new']['name'] as $i => $fn) {
            if ($fn == '') continue;
            $file = ['name' => $fn, 'tmp_name' => $_FILES['spec_file_new']['tmp_name'][$i]];
            $namaBaru = $koneksi->real_escape_string($_POST['spec_nama_new'][$i] ?? '');
            $descBaru = $koneksi->real_escape_string($_POST['spec_desc_new'][$i] ?? '');
            saveFile($file, 'SPEC', $id_property, $koneksi, $namaBaru, $descBaru);
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
    <title><?= $mode==='edit'?'Edit':'Tambah' ?> Apartemen - PropertyKu</title>
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
.layout-box,.spec-box{background:#fff;padding:20px;border-radius:16px;box-shadow:0 0 10px rgba(0,0,0,.04);margin-bottom:20px;}
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
.media-row{display:flex;align-items:center;gap:10px;flex-wrap:nowrap;}
.media-text{font-size:14px;color:var(--text-main);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;}
.btn-x{flex:0 0 28px;width:28px;height:28px;border-radius:50%;background:rgba(107,114,128,.75);color:#fff;border:none;cursor:pointer;font-size:18px;font-weight:700;line-height:28px;text-align:center;}
.panorama-input{flex:1 1 360px;max-width:360px;min-width:180px;}
.sidebar-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;}
.sidebar-top-title{font-size:18px;color:var(--text-main);margin:0;line-height:1;}
.hamburger{background:var(--primary-soft);border:none;border-radius:12px;padding:6px 8px;cursor:pointer;color:var(--primary);font-size:18px;display:inline-flex;align-items:center;justify-content:center;}
.sidebar.collapsed{width:60px;padding:18px 8px;}
.sidebar.collapsed .profile,.sidebar.collapsed .sidebar-title,.sidebar.collapsed .menu{display:none;}
.sidebar.collapsed .sidebar-top-title{display:none;}
.sidebar.collapsed .sidebar-top{justify-content:center;}
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);opacity:0;pointer-events:none;transition:.2s;z-index:150;}
.mobile-topbar{display:none;align-items:center;gap:12px;padding:8px 10px;border-radius:16px;margin-bottom:12px;top:10px;z-index:200;position:sticky;}

@media(max-width:900px){
body{padding:20px;}
.main{padding:20px;}
.grid{grid-template-columns:repeat(2,1fr);}
}
@media(max-width:600px){
body{padding:12px;}
.app{border-radius:24px;}
.sidebar{position:fixed;left:0;top:0;bottom:0;width:260px;transform:translateX(-105%);transition:.25s;box-shadow:0 18px 45px rgba(0,0,0,.18);z-index:300;}
.sidebar.open{transform:translateX(0);}
.overlay.show{opacity:1;pointer-events:auto;}
.main{padding:14px;}
.form-box h2{font-size:20px;}
.form-box p{font-size:13px;}
.upload-box{max-width:100%;height:150px;}
.upload-wide{height:220px;}
.grid{grid-template-columns:1fr;}
.btn-row{flex-wrap:column;}
.btn{width:100%;}
#panoramaBox .media-row{display:grid;grid-template-columns:1fr 44px;gap:10px;align-items:center;}
#panoramaBox .media-row .panorama-input{grid-column:1;width:100%;max-width:100%;}
#panoramaBox .media-row .btn-x{grid-column:2;grid-row:1/span 2;justify-self:end;}
.mobile-topbar{display:flex;}
.sidebar .hamburger{display:none;}
}

#hargaTipeBox .media-row{
  display:grid;
  grid-template-columns:1fr 44px;
  gap:10px;
  align-items:center;
}
#hargaTipeBox .media-row .panorama-input{
  grid-column:1;
  width:100%;
  max-width:100%;
  min-width:0;
}
#hargaTipeBox .media-row .btn-x{
  grid-column:2;
  grid-row:1/span 2;
  justify-self:end;
}
#layoutByType{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;align-items:start;}
@media (max-width: 900px){
  #layoutByType{grid-template-columns:1fr;}
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
                <li><a href="homeproper.php"><i class="ri-home-6-line"></i> Rumah</a></li>
                <li><a class="active" href="apartproper.php"><i class="ri-building-4-line"></i> Apartemen</a></li>
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
            <div class="mobile-topbar-title">Apartemen</div>
        </div>
        <div class="form-box">
            <h2><?= $mode==='edit'?'Edit':'Tambah' ?> Data Apartemen</h2>
            <p>Isi informasi lengkap mengenai apartemen yang ingin ditambahkan.</p>
            <form method="POST" enctype="multipart/form-data" id="propertyForm">
                <div class="form-row">
                    <div class="form-col">
                        <label>Foto Apartemen</label>
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
                        <label>Nama Apartemen</label>
                        <input type="text" name="nama" placeholder="Contoh: Gajayana Luxury Apartement" required value="<?= htmlspecialchars($data['nama'] ?? '') ?>">

                        <label>Lokasi</label>
                        <input type="text" name="lokasi" placeholder="Contoh: Surabaya" required value="<?= htmlspecialchars($data['lokasi'] ?? '') ?>">

                        <label>Jumlah Unit</label>
                        <input type="number" name="jumlah_unit" placeholder="Contoh: 120" required value="<?= htmlspecialchars($data['jumlah_unit'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <label>Harga Sewa mulai dari (perbulan)</label>
                        <input type="text" id="harga" name="harga" placeholder="Contoh: 850.000.000" required value="<?= isset($data['harga']) ? number_format($data['harga'], 0, ',', '.') : '' ?>"oninput="formatHarga(this)"><br>
                        <div id="hargaTipeBox" class="media-list">
                            <?php if(!empty($hargaTipe)): ?>
                                <?php foreach($hargaTipe as $ht): ?>
                                <div class="media-row">
                                    <input type="text"
                                        name="harga_tipe_nama[]"
                                        placeholder="Nama tipe (contoh: Studio / 1BR / 2BR)"
                                        value="<?= htmlspecialchars($ht['nama_tipe']) ?>"
                                        class="panorama-input">

                                    <input type="text"
                                        name="harga_tipe_nominal[]"
                                        placeholder="Harga per bulan (contoh: 2.500.000)"
                                        value="<?= number_format((int)$ht['harga'], 0, ',', '.') ?>"
                                        class="panorama-input"
                                        oninput="formatHarga(this)">
                                    <button type="button" class="btn-x" onclick="removeHargaTipeRow(this)">×</button>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="media-row">
                                    <input type="text"
                                        name="harga_tipe_nama[]"
                                        placeholder="Nama tipe (contoh: Studio / 1BR / 2BR)"
                                        class="panorama-input">

                                    <input type="text"
                                        name="harga_tipe_nominal[]"
                                        placeholder="Harga per bulan (contoh: 2.500.000)"
                                        class="panorama-input"
                                        oninput="formatHarga(this)">

                                    <button type="button" class="btn-x" onclick="removeHargaTipeRow(this)">×</button>
                                </div>
                            <?php endif; ?>
                            </div>

                            <button type="button" class="add-btn" onclick="addHargaTipeField()">+ Tambah Harga Tipe</button>
                            <p class="small-note">Isi beberapa tipe unit beserta harga sewanya per bulan.</p>
                    </div>

                    <div class="form-col">
                        <label>Kategori</label>
                        <select name="kategori">
                            <?php
                                $kategori_options = ['Highrise','Midrise','Lowrise','Luxury','Premium Residence'];
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
                            $status_options = ['Tersedia','Terbooking'];
                            foreach($status_options as $s){
                                $sel = ($data['status'] ?? '')==$s ? 'selected' : '';
                                echo "<option value=\"".htmlspecialchars($s)."\" $sel>".htmlspecialchars($s)."</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-col">
                        <label>Kode Tower / Blok (optional)</label>
                        <input type="text" name="kode" placeholder="Contoh: Tower A / Tower Selatan" value="<?= htmlspecialchars($data['kode'] ?? '') ?>">
                    </div>
                </div>

                <!-- Deskripsi -->
                <label>Deskripsi Apartemen</label><br>
                <textarea name="deskripsi" placeholder="Tuliskan deskripsi singkat Apartemen" required><?= htmlspecialchars($data['deskripsi'] ?? '') ?></textarea>

                <!-- Denah -->
                <h3 class="section-title">Denah Apartemen</h3>
                <div class="upload-wide">
                    <?php if(!empty($data['denah'])): ?>
                        <img id="previewDenah" src="uploads/<?= htmlspecialchars($data['denah']) ?>" alt="">
                    <?php else: ?>
                        <img id="previewDenah" style="display:none;" alt="">
                    <?php endif; ?>
                </div>
                <input type="file" name="denah" accept="image/*" onchange="previewMedia(event, 'previewDenah')"<?= $mode==='add' ? 'required' : '' ?>>
                <label>Deskripsi Denah</label><br>
                <textarea name="denah_desc" placeholder="Contoh: Total Area: 400 m² Bedrooms: 2 Bathrooms: 1 Floors: 2" style="height:90px;"><?= htmlspecialchars($denahMedia['deskripsi'] ?? '') ?></textarea>

                <!-- Video -->
                <h3 class="section-title">Video Bangunan Apartemen</h3>
                <div class="upload-wide">
                    <?php if(!empty($data['video'])): ?>
                        <video id="previewVideo" src="uploads/<?= htmlspecialchars($data['video']) ?>" controls></video>
                    <?php else: ?>
                        <video id="previewVideo" autoplay muted loop></video>
                    <?php endif; ?>
                </div>
                <input type="file" name="video" accept="video/*" onchange="previewMedia(event, 'previewVideo')" <?= $mode==='add' ? 'required' : '' ?>>

                <!-- Panorama -->
                <h3 class="section-title">Panorama 360° Interior per Tipe Unit</h3>
                <div id="panoramaBox" class="media-list">
                    <?php if(!empty($panorama)): ?>
                        <?php foreach($panorama as $p): ?>
                            <div id="media-<?= intval($p['id']) ?>" class="media-row">
                                <input type="hidden" name="panorama_id_existing[]" value="<?= intval($p['id']) ?>">
                                <input type="text" name="panorama_nama_existing[]" value="<?= htmlspecialchars($p['nama']) ?>" placeholder="Nama Tipe (contoh: 2 Bedroom, Type D)" class="panorama-input">
                                <input type="text" name="panorama_link_existing[]" value="<?= htmlspecialchars($p['file']) ?>" placeholder="Link Panorama 360° untuk tipe ini" class="panorama-input">
                                <button type="button" class="btn-x" onclick="confirmDelete(<?= intval($p['id']) ?>)">×</button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="media-row">
                            <input type="text" name="panorama_nama[]" placeholder="Nama Tipe (contoh: 2 Bedroom, Type D)" class="panorama-input">
                            <input type="text" name="panorama_link[]" placeholder="Link Panorama 360° untuk tipe ini" class="panorama-input">
                            <button type="button" class="btn-x" onclick="this.parentElement.remove()">×</button>
                        </div>
                    <?php endif; ?>
                </div>

                <button type="button" class="add-btn" onclick="addPanoramaField()">+ Tambah Panorama</button>

                <!-- Fasilitas -->
                <h3 class="section-title">Fasilitas Umum Apartemen</h3>
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
                <input type="file" id="facilityInput" name="fasilitas[]" accept="image/*" multiple hidden onchange="previewMultiple(event)">

                <!-- Public Domain -->
                <h3 class="section-title">Public Domain</h3>
                <div id="pdGrid" class="grid">
                <?php if(!empty($publicDomain)): ?>
                    <?php foreach($publicDomain as $pd): ?>
                    <div class="file-item" id="media-<?= intval($pd['id']) ?>">
                        <?php if(!empty($pd['file'])): ?>
                        <img id="pd_old_<?= intval($pd['id']) ?>" src="uploads/<?= htmlspecialchars($pd['file']) ?>" class="file-thumb">
                        <?php endif; ?>
                        <button type="button" class="delete-btn" title="Hapus"
                        onclick="confirmDelete(<?= intval($pd['id']) ?>)">×</button>
                        <div class="inner">
                        <input type="hidden" name="pd_id[]" value="<?= intval($pd['id']) ?>">
                        <div style="margin-top:8px;">
                            <input type="file" name="pd_file[]" accept="image/*"
                            onchange="previewMedia(event, 'pd_old_<?= intval($pd['id']) ?>')">
                        </div>
                        <div style="margin-top:8px;">
                            <label>Nama</label>
                            <input type="text" name="pd_nama[]" value="<?= htmlspecialchars($pd['nama'] ?? '') ?>" placeholder="Contoh: Taman, Lobby, Kolam Renang">
                        </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
                <div id="pd_box"></div>
                <button type="button" class="add-btn" onclick="addPublicDomain()">+ Tambah Public Domain</button>

                <!-- GMaps -->
                <h3 class="section-title">Lokasi (Google Maps)</h3>
                <input type="text" name="gmaps" placeholder="Link Google Maps" value="<?= htmlspecialchars($data['gmaps'] ?? '') ?>">

                <!-- Unit Layout -->
                <h3 class="section-title">Unit & Layout</h3>
                <div id="layoutByType">
                <?php
                    // tipeList dari hargaTipe
                    $tipeList = [];
                    if (!empty($hargaTipe)) {
                        foreach ($hargaTipe as $ht) {
                            $t = trim($ht['nama_tipe'] ?? '');
                            if ($t !== '') $tipeList[] = $t;
                        }
                    }
                    $tipeList = array_values(array_unique($tipeList));
                ?>

                <?php foreach ($tipeList as $tipe):
                    $key = safeKey($tipe);
                    $item = $layoutByType[$tipe] ?? null;
                ?>
                    <div class="layout-box" id="layoutSection-<?= $key ?>" style="margin-top:14px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                            <div>
                                <div style="font-weight:600;color:var(--text-main);"><?= htmlspecialchars($tipe) ?></div>
                                <div class="small-note">Upload gambar layout + isi deskripsi untuk tipe ini.</div>
                            </div>
                        </div>

                        <div style="margin-top:12px;">
                            <?php if ($item): ?>
                                <!-- EXISTING (EDIT) -->
                                <div class="file-item" id="media-<?= intval($item['id']) ?>">
                                    <?php if(!empty($item['file'])): ?>
                                        <img id="layout_old_<?= intval($item['id']) ?>" src="uploads/<?= htmlspecialchars($item['file']) ?>" class="file-thumb">
                                    <?php endif; ?>

                                    <div class="inner">
                                        <input type="hidden" name="layout_id[]" value="<?= intval($item['id']) ?>">
                                        <input type="hidden" name="layout_nama[]" value="<?= htmlspecialchars($tipe) ?>">

                                        <div style="margin-top:8px;">
                                            <input type="file" name="layout_file[]" accept="image/*"
                                                onchange="previewMedia(event, 'layout_old_<?= intval($item['id']) ?>')">
                                        </div>

                                        <div style="margin-top:8px;">
                                            <label>Deskripsi</label>
                                            <textarea name="layout_desc[]" placeholder="Deskripsi layout"><?= htmlspecialchars($item['deskripsi'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- BELUM ADA (akan diisi via SYNC atau manual upload setelah sync) -->
                                <div class="file-item" data-layout-empty="1" style="opacity:.85;">
                                    <div class="inner">
                                        <div class="small-note">
                                            Belum ada layout untuk tipe ini.
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- tempat form NEW untuk tipe ini (hasil sync) -->
                        <div id="layout_box-<?= $key ?>"></div>
                    </div>
                <?php endforeach; ?>
                </div>

                <!-- Unit Specification (SPEC) -->
                <h3 class="section-title">Unit Spesifikasi</h3>
                <div id="specByType">

                <?php foreach ($tipeList as $tipe):
                    $key = safeKey($tipe);
                    $items = $specByTypeAll[$tipe] ?? [];
                ?>
                <div class="layout-box" id="specSection-<?= $key ?>" style="margin-top:14px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                    <div>
                        <div style="font-weight:600;color:var(--text-main);"><?= htmlspecialchars($tipe) ?></div>
                        <div class="small-note">Tambah kategori (Kitchen/Bedroom/dll). Tiap kategori bisa upload gambar + isi deskripsi.</div>
                    </div>

                    <button type="button" class="add-btn" onclick="addSpecItem('<?= htmlspecialchars($tipe, ENT_QUOTES) ?>','<?= $key ?>')">
                        + Tambah
                    </button>
                    </div>

                    <!-- EXISTING ITEMS -->
                    <div class="grid" style="margin-top:12px;" id="specGrid-<?= $key ?>">
                    <?php foreach ($items as $s): ?>
                        <div class="file-item" id="media-<?= intval($s['id']) ?>">
                        <?php if(!empty($s['file'])): ?>
                            <img id="spec_old_<?= intval($s['id']) ?>" src="uploads/<?= htmlspecialchars($s['file']) ?>" class="file-thumb">
                        <?php endif; ?>

                        <button type="button" class="delete-btn" title="Hapus" onclick="confirmDelete(<?= intval($s['id']) ?>)">×</button>

                        <div class="inner">
                            <input type="hidden" name="spec_id[]" value="<?= intval($s['id']) ?>">
                            <input type="hidden" name="spec_nama[]" value="<?= htmlspecialchars($tipe) ?>">

                            <div style="margin-top:8px;">
                            <input type="file" name="spec_file[]" accept="image/*"
                                    onchange="previewMedia(event, 'spec_old_<?= intval($s['id']) ?>')">
                            </div>

                            <div style="margin-top:8px;">
                            <label>Nama Kategori</label>
                            <input type="text" name="spec_desc[]" value="<?= htmlspecialchars($s['deskripsi'] ?? '') ?>"
                                    placeholder="Contoh: Kitchen / Bedroom / Bathroom">
                            </div>
                        </div>
                        </div>
                    <?php endforeach; ?>
                    </div>

                    <!-- NEW ITEMS INJECT HERE -->
                    <div id="spec_box-<?= $key ?>"></div>
                </div>
                <?php endforeach; ?>
                </div>

                <div class="btn-row">
                    <a class="btn btn-cancel" href="<?= ($mode === 'edit') ? 'dataproper.php' : 'apartproper2.php' ?>">Batal</a>
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
    function previewMultiple(e) {
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
    function addPublicDomain() {
    const box = document.getElementById("pd_box");
    const id = "pd_preview_" + Math.random().toString(36).substr(2, 9);

    const div = document.createElement("div");
    div.classList.add("spec-box"); // boleh pakai class yang sama biar style-nya mirip
    div.innerHTML = `
        <label>Nama</label>
        <input type="text" name="pd_nama_new[]" placeholder="Contoh: Gym / Laundry"><br><br>
        <div class="upload-wide">
        <img id="${id}" class="file-thumb" style="display:none;">
        </div>
        <input type="file" name="pd_file_new[]" accept="image/*" onchange="previewMedia(event, '${id}')">
    `;
    box.appendChild(div);
    }
    function addPanoramaField() {
        const box = document.getElementById("panoramaBox");
        const row = document.createElement("div");
        row.className = "media-row";
        row.innerHTML = `
            <input type="text" name="panorama_nama[]" placeholder="Nama Tipe (contoh: 2 Bedroom, Type D)" class="panorama-input">
            <input type="text" name="panorama_link[]" placeholder="Link Panorama 360° untuk tipe ini" class="panorama-input">
            <button type="button" class="btn-x" onclick="this.parentElement.remove()">×</button>`;
        box.appendChild(row);
    }

    function initAutoSyncHargaTipe(){
  const box = document.getElementById('hargaTipeBox');
  if(!box) return;

  // event delegation: kalau user ngetik di input tipe
  box.addEventListener('input', (e) => {
    if(e.target && e.target.name === 'harga_tipe_nama[]'){
      scheduleAutoSync();
    }
  });

  // kalau user keluar dari input (rename final)
  box.addEventListener('change', (e) => {
    if(e.target && e.target.name === 'harga_tipe_nama[]'){
      scheduleAutoSync();
    }
  });

  // pertama kali load juga sinkron
  scheduleAutoSync();
}

let autoSyncTimer = null;
function scheduleAutoSync(){
  clearTimeout(autoSyncTimer);
  autoSyncTimer = setTimeout(autoSyncFromHargaTipe, 200); // debounce biar ga berat
}

function autoSyncFromHargaTipe(){
  const types = getHargaTipeUniq(); // kamu sudah punya ini (uniq by makeKey)
  const keysNow = new Set(types.map(t => makeKey(t)));

  // 1) ensure sections exist untuk tiap tipe
  types.forEach(tipe => {
    ensureLayoutSection(tipe);
    ensureSpecSection(tipe);
  });

  // 2) remove sections yang sudah tidak ada di harga tipe
  cleanupRemovedTypeSections(keysNow);
}

function ensureLayoutSection(tipe){
  const key = makeKey(tipe);
  if(!key) return;

  const container = document.getElementById('layoutByType');
  if(!container) return;

  let section = document.getElementById('layoutSection-' + key);
  if(!section){
    section = document.createElement('div');
    section.className = 'layout-box';
    section.id = 'layoutSection-' + key;
    section.style.marginTop = '14px';
    section.innerHTML = `
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div>
          <div style="font-weight:600;color:var(--text-main);">${escapeHtml(tipe)}</div>
          <div class="small-note">Upload gambar layout + isi deskripsi untuk tipe ini.</div>
        </div>
      </div>

      <div style="margin-top:12px;">
        <div class="file-item" data-layout-empty="1" style="opacity:.95;">
          <div class="inner">
            <div class="small-note">Silahkan upload layout untuk tipe ini.</div>
          </div>
        </div>
      </div>

      <div id="layout_box-${key}"></div>
    `;

    // sisipkan sebelum penutup container (karena kamu gak pakai tombol sync lagi)
    container.appendChild(section);
  } else {
    // update judul kalau user rename text (biar yang tampil sesuai input terbaru)
    const titleEl = section.querySelector('div[style*="font-weight:600"]');
    if(titleEl) titleEl.innerHTML = escapeHtml(tipe);
  }

  // pastiin ada 1 form NEW layout untuk tipe ini kalau belum ada layout existing & belum ada form new
  const hasExisting = !!section.querySelector('input[name="layout_id[]"]');      // layout lama dari DB
  const boxNew = document.getElementById('layout_box-' + key);
  if(!boxNew) return;

  if(!hasExisting && !boxNew.querySelector('[data-layout-new="1"]')){
    // hapus placeholder empty card
    const emptyCard = section.querySelector('[data-layout-empty="1"]');
    if(emptyCard) emptyCard.remove();

    const imgId = 'layout_preview_' + Math.random().toString(36).slice(2);

    const div = document.createElement('div');
    div.className = 'file-item';
    div.setAttribute('data-layout-new','1');
    div.innerHTML = `
      <div class="upload-wide" style="height:220px;">
        <img id="${imgId}" class="file-thumb" style="display:none;">
      </div>

      <input type="hidden" name="layout_nama_new[]" value="${escapeAttr(tipe)}">

      <div class="inner">
        <div style="margin-top:8px;">
          <input type="file" name="layout_file_new[]" accept="image/*" onchange="previewMedia(event, '${imgId}')">
        </div>

        <div style="margin-top:8px;">
          <label>Deskripsi</label>
          <textarea name="layout_desc_new[]" placeholder="Deskripsi layout"></textarea>
        </div>
      </div>
    `;
    boxNew.appendChild(div);
  }
}

function ensureSpecSection(tipe){
  const key = makeKey(tipe);
  if(!key) return;

  const container = document.getElementById('specByType');
  if(!container) return;

  let section = document.getElementById('specSection-' + key);
  if(!section){
    section = document.createElement('div');
    section.className = 'layout-box';
    section.id = 'specSection-' + key;
    section.style.marginTop = '14px';
    section.innerHTML = `
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div>
          <div style="font-weight:600;color:var(--text-main);">${escapeHtml(tipe)}</div>
          <div class="small-note">Tambah kategori (Kitchen/Bedroom/dll). Tiap kategori bisa upload gambar + isi deskripsi.</div>
        </div>
        <button type="button" class="add-btn" onclick="addSpecItem('${escapeJs(tipe)}','${key}')">+ Tambah</button>
      </div>

      <div class="grid" style="margin-top:12px;" id="specGrid-${key}"></div>
      <div id="spec_box-${key}"></div>
    `;
    container.appendChild(section);
  } else {
    // update judul kalau user rename
    const titleEl = section.querySelector('div[style*="font-weight:600"]');
    if(titleEl) titleEl.innerHTML = escapeHtml(tipe);
  }
}

function cleanupRemovedTypeSections(keysNow){
  // hapus layout section yang key-nya ga ada lagi
  document.querySelectorAll('[id^="layoutSection-"]').forEach(sec => {
    const key = sec.id.replace('layoutSection-','');
    if(!keysNow.has(key)){
      // kalau ada existing layout_id, tandai delete DB juga
      sec.querySelectorAll('input[name="layout_id[]"]').forEach(inp => markDeletedMedia(inp.value));
      sec.remove();
    }
  });

  // hapus spec section yang key-nya ga ada lagi
  document.querySelectorAll('[id^="specSection-"]').forEach(sec => {
    const key = sec.id.replace('specSection-','');
    if(!keysNow.has(key)){
      sec.querySelectorAll('input[name="spec_id[]"]').forEach(inp => markDeletedMedia(inp.value));
      sec.remove();
    }
  });
}

// helpers aman untuk inject text
function escapeHtml(str){
  return String(str || '')
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'","&#039;");
}
function escapeAttr(str){ return escapeHtml(str); }
function escapeJs(str){
  return String(str || '').replaceAll('\\','\\\\').replaceAll("'","\\'").replaceAll('"','\\"');
}

    function confirmDelete(mediaId){
        const el = document.getElementById('media-' + mediaId);

        // ambil tipe dulu SEBELUM remove (khusus layout)
        let tipeVal = "";
        if (el) {
            const tipeInput = el.querySelector('input[name="layout_nama[]"]');
            if (tipeInput) tipeVal = tipeInput.value;
            el.remove();
        }

        // tandai id yang dihapus
        const del = document.createElement('input');
        del.type = 'hidden';
        del.name = 'deleted_media_id[]';
        del.value = mediaId;
        document.getElementById('propertyForm').appendChild(del);

        // (optional) tandai tipe layout yang dihapus
        if (tipeVal) {
            const delTipe = document.createElement('input');
            delTipe.type = 'hidden';
            delTipe.name = 'deleted_layout_tipe[]';
            delTipe.value = tipeVal;
            document.getElementById('propertyForm').appendChild(delTipe);
        }
    }
    document.addEventListener("DOMContentLoaded", function () {
        const sidebar  = document.querySelector(".sidebar");
        const toggleBtn = document.getElementById("toggleSidebar");     // tombol di sidebar (desktop)
        const mobileBtn = document.getElementById("mobileHamburger");   // tombol topbar (hp)
        const overlay  = document.getElementById("overlay");
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
        toggleBtn && toggleBtn.addEventListener("click", toggleSidebar);
        mobileBtn && mobileBtn.addEventListener("click", toggleSidebar);
        overlay && overlay.addEventListener("click", closeSidebar);
        window.addEventListener("resize", () => {
            if (!isMobile()) closeSidebar(); 
        });
        initAutoSyncHargaTipe();
    });
    function addHargaTipeField() {
        const box = document.getElementById("hargaTipeBox");
        const row = document.createElement("div");
        row.className = "media-row";
        row.innerHTML = `
            <input type="text" name="harga_tipe_nama[]" placeholder="Nama tipe (contoh: Studio/1BR/2BR)" class="panorama-input">
            <input type="text" name="harga_tipe_nominal[]" placeholder="Harga per bulan (contoh: 2.500.000)" class="panorama-input" oninput="formatHarga(this)">
            <button type="button" class="btn-x" onclick="removeHargaTipeRow(this)">×</button>
        `;
        box.appendChild(row);
    }   
    function getHargaTipeUniq(){
  const tipeInputs = document.querySelectorAll('input[name="harga_tipe_nama[]"]');
  const arr = [];
  tipeInputs.forEach(inp => {
    const v = (inp.value || '').trim();
    if(v) arr.push(v);
  });
  // uniq by normalized key
  const map = new Map();
  arr.forEach(t => map.set(makeKey(t), t));
  return Array.from(map.values());
}

function makeKey(tipe){
  return (tipe || '').toLowerCase().trim()
    .replace(/[^a-z0-9]+/g,'_')
    .replace(/^_+|_+$/g,'');
}

function removeHargaTipeRow(btn){
  const row = btn.closest('.media-row');
  if(!row) return;

  // ambil tipe yang dihapus
  const tipeInput = row.querySelector('input[name="harga_tipe_nama[]"]');
  const tipe = (tipeInput?.value || '').trim();
  const key  = makeKey(tipe);

  // hapus row harga
  row.remove();

  // kalau tipe kosong, stop
  if(!key) return;

  // 1) HAPUS SECTION LAYOUT + tandai delete DB untuk layout_id
  const layoutSection = document.getElementById('layoutSection-' + key);
  if(layoutSection){
    // tandai delete layout yang existing (kalau ada)
    layoutSection.querySelectorAll('input[name="layout_id[]"]').forEach(inp => {
      markDeletedMedia(inp.value);     // delete property_media by id
      markDeletedLayoutTipe(tipe);     // supaya backend kamu paham tipe ini dihapus
    });
    layoutSection.remove();
  }

  // 2) HAPUS SECTION SPEC + tandai delete DB untuk spec_id
  const specSection = document.getElementById('specSection-' + key);
  if(specSection){
    specSection.querySelectorAll('input[name="spec_id[]"]').forEach(inp => {
      markDeletedMedia(inp.value); // delete property_media by id
    });
    specSection.remove();
  }
}

function markDeletedMedia(id){
  if(!id) return;
  const form = document.getElementById('propertyForm');
  const h = document.createElement('input');
  h.type = 'hidden';
  h.name = 'deleted_media_id[]';
  h.value = parseInt(id, 10);
  form.appendChild(h);
}

function markDeletedLayoutTipe(tipe){
  if(!tipe) return;
  const form = document.getElementById('propertyForm');
  const h = document.createElement('input');
  h.type = 'hidden';
  h.name = 'deleted_layout_tipe[]';
  h.value = tipe;
  form.appendChild(h);
}
function addSpecItem(tipe, key){
  const grid = document.getElementById('specGrid-' + key);
  if(!grid) return;

  const imgId = 'spec_new_' + Math.random().toString(36).slice(2);

  const card = document.createElement('div');
  card.className = 'file-item';
  card.innerHTML = `
    <div class="upload-wide" style="height:220px;">
      <img id="${imgId}" class="file-thumb" style="display:none;">
    </div>

    <div class="inner">
      <input type="hidden" name="spec_nama_new[]" value="${escapeAttr(tipe)}">

      <div style="margin-top:8px;">
        <input type="file" name="spec_file_new[]" accept="image/*"
          onchange="previewMedia(event, '${imgId}')">
      </div>

      <div style="margin-top:8px;">
        <label>Nama Kategori</label>
        <input type="text" name="spec_desc_new[]" placeholder="Contoh: Kitchen / Bedroom / Bathroom">
      </div>

      <button type="button" class="delete-btn" title="Hapus" onclick="this.closest('.file-item').remove()">×</button>
    </div>
  `;

  grid.appendChild(card);
}
</script>
</body>
</html>
