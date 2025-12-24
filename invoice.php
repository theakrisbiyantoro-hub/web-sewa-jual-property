<?php
ob_start();
include "database.php";

// ============================
// VALIDASI BOOKING ID
// ============================
if (!isset($_GET['booking_id'])) {
    die("Invoice ID tidak ditemukan.");
}
$id = (int) $_GET['booking_id'];

// ============================
// QUERY DATA BOOKING (HARUS VERIFIED)
// ============================
$q = $koneksi->query("
    SELECT 
    b.id,
    b.full_name,
    b.email,
    b.phone,
    b.unit_type,
    b.checkin_date,
    p.nama AS property_name,

    bp.amount AS paid_amount
FROM booking b
JOIN booking_payments bp ON bp.booking_id = b.id
JOIN property p ON b.property_id = p.id
WHERE b.id = $id
  AND bp.status = 'verified'
");

if (!$q || $q->num_rows === 0) {
    die("Invoice belum tersedia / belum diverifikasi admin.");
}

$d = $q->fetch_assoc();

// ============================
// INVOICE NUMBER
// ============================
$invoiceNo = "INV-" . str_pad($d['id'], 6, "0", STR_PAD_LEFT);

// ============================
// LOAD QR CODE
// ============================
require_once __DIR__ . "/phpqrcode/qrlib.php";

$qrDir = "uploads/qr/";
if (!is_dir($qrDir)) {
    mkdir($qrDir, 0777, true);
}

$qrData = "PROPERTYKU|BOOKING|" . $invoiceNo;
$qrFile = $qrDir . "QR_" . $invoiceNo . ".png";

QRcode::png($qrData, $qrFile, QR_ECLEVEL_H, 6);

// ============================
// LOAD PDF
// ============================
require_once __DIR__ . "/fpdf/fpdf.php";

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetMargins(15, 15, 15);

// ============================
// HEADER
// ============================
$pdf->SetFont("Arial", "B", 22);
$pdf->Cell(0, 10, "PropertyKu", 0, 1);

$pdf->SetFont("Arial", "", 11);
$pdf->Cell(0, 6, "Luxury Property Booking Invoice", 0, 1);

$pdf->Ln(6);
$pdf->Line(15, 40, 195, 40);
$pdf->Ln(10);

// ============================
// INVOICE INFO
// ============================
$pdf->SetFont("Arial", "", 11);
$pdf->Cell(40, 8, "Invoice Number", 1);
$pdf->Cell(0, 8, $invoiceNo, 1, 1);

$pdf->Cell(40, 8, "Invoice Date", 1);
$pdf->Cell(0, 8, date("d M Y"), 1, 1);

$pdf->Ln(8);

// ============================
// CUSTOMER & PROPERTY
// ============================
$pdf->SetFont("Arial", "B", 11);
$pdf->Cell(95, 8, "Customer Information", 1);
$pdf->Cell(0, 8, "Property Information", 1, 1);

$pdf->SetFont("Arial", "", 11);
$pdf->Cell(95, 8, "Name : " . $d['full_name'], 1);
$pdf->Cell(0, 8, "Property : " . $d['property_name'], 1, 1);

$pdf->Cell(95, 8, "Email : " . $d['email'], 1);
$pdf->Cell(0, 8, "Unit : " . $d['unit_type'], 1, 1);

$pdf->Cell(95, 8, "Phone : " . $d['phone'], 1);
$pdf->Cell(0, 8, "Check-in : " . $d['checkin_date'], 1, 1);

$pdf->Ln(10);

// ============================
// TOTAL PAYMENT
// ============================
$total = (int) ($d['paid_amount'] ?? 0);

$pdf->SetFont("Arial", "B", 14);
$pdf->Cell(130, 12, "TOTAL PAYMENT", 1);
$pdf->Cell(0, 12, "Rp " . number_format($total, 0, ',', '.'), 1, 1, "R");

// ============================
// QR CODE
// ============================
$pdf->Ln(14);
$pdf->SetFont("Arial", "B", 11);
$pdf->Cell(0, 6, "QR Code Booking", 0, 1);

$pdf->SetFont("Arial", "", 10);
$pdf->Cell(0, 5, "Show this QR Code during property check-in", 0, 1);

$pdf->Image($qrFile, 150, $pdf->GetY() + 4, 40);

// ============================
// FOOTER
// ============================
$pdf->SetY(-25);
$pdf->SetFont("Arial", "I", 8);
$pdf->Cell(0, 6, "This is a system-generated invoice. No signature required.", 0, 0, "C");

// ============================
// OUTPUT
// ============================
ob_end_clean();
$pdf->Output("I", "Invoice_$invoiceNo.pdf");
exit;
