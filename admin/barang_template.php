<?php
session_start();
include '../config/database.php';
require_once '../plugins/simplexlsx/SimpleXLSXGen.php';

// Prepare Data Reference
$merk_res = $conn->query("SELECT id, nama_merk FROM merk ORDER BY nama_merk ASC");
$merk = []; while($r = $merk_res->fetch_assoc()) $merk[] = $r;

$jenis_res = $conn->query("SELECT id, nama_jenis FROM jenis ORDER BY nama_jenis ASC");
$jenis = []; while($r = $jenis_res->fetch_assoc()) $jenis[] = $r;

$kategori_res = $conn->query("SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori ASC");
$kategori = []; while($r = $kategori_res->fetch_assoc()) $kategori[] = $r;

$unit_res = $conn->query("SELECT id, nama_unit FROM unit ORDER BY nama_unit ASC");
$unit = []; while($r = $unit_res->fetch_assoc()) $unit[] = $r;

$ruang_res = $conn->query("SELECT r.id, r.nama_ruang, u.nama_unit FROM ruang r JOIN unit u ON r.id_unit = u.id ORDER BY u.nama_unit ASC, r.nama_ruang ASC");
$ruang = []; while($r = $ruang_res->fetch_assoc()) $ruang[] = $r;

// Sheet 1: Template
$header = [
    '<b>Nama Barang</b>', 
    '<b>ID Merk</b>', 
    '<b>Jumlah</b>', 
    '<b>ID Jenis</b>', 
    '<b>ID Kategori</b>', 
    '<b>ID Unit</b>', 
    '<b>ID Ruang</b>', 
    '<b>Kondisi (Baik/Rusak/Hilang)</b>',
    '<b>Tgl Pembelian (YYYY-MM-DD)</b>'
];
$data = [
    $header,
    ['Kursi Kantor', '1', '10', '1', '1', '1', '1', 'Baik', '2023-01-15'],
    ['Laptop Core i7', '2', '5', '2', '2', '1', '1', 'Baik', '2023-05-20']
];

// Sheet 2: Referensi ID
$ref_data = [['<b>KATEGORI</b>', '', '<b>MERK</b>', '', '<b>JENIS</b>', '', '<b>UNIT</b>', '']];
$max_rows = max(count($kategori), count($merk), count($jenis), count($unit));

for ($i = 0; $i < $max_rows; $i++) {
    $ref_data[] = [
        $kategori[$i]['id'] ?? '', $kategori[$i]['nama_kategori'] ?? '',
        $merk[$i]['id'] ?? '', $merk[$i]['nama_merk'] ?? '',
        $jenis[$i]['id'] ?? '', $jenis[$i]['nama_jenis'] ?? '',
        $unit[$i]['id'] ?? '', $unit[$i]['nama_unit'] ?? ''
    ];
}

// Sheet 3: Referensi Ruang
$ruang_ref = [['<b>ID RUANG</b>', '<b>NAMA RUANG</b>', '<b>UNIT</b>']];
foreach ($ruang as $r) {
    $ruang_ref[] = [$r['id'], $r['nama_ruang'], $r['nama_unit']];
}

$xlsx = Shuchkin\SimpleXLSXGen::fromArray($data, 'Template');
$xlsx->addSheet($ref_data, 'Referensi ID');
$xlsx->addSheet($ruang_ref, 'Referensi Ruang');

$xlsx->downloadAs('template_import_barang.xlsx');
?>
