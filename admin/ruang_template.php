<?php
session_start();
include '../config/database.php';
require_once '../plugins/simplexlsx/SimpleXLSXGen.php';

// Prepare Unit Reference
$unit_res = $conn->query("SELECT id, nama_unit FROM unit ORDER BY nama_unit ASC");
$unit_ref = [['<b>ID UNIT</b>', '<b>NAMA UNIT</b>']];
while($u = $unit_res->fetch_assoc()) {
    $unit_ref[] = [$u['id'], $u['nama_unit']];
}

// Sheet 1: Template
$header = [
    '<b>ID Unit</b>',
    '<b>Nama Ruang</b>'
];
$data = [
    $header,
    ['1', 'Ruang Kepala Sekolah'],
    ['1', 'Ruang Guru'],
    ['2', 'Laboratorium Komputer']
];

$xlsx = Shuchkin\SimpleXLSXGen::fromArray($data, 'Template');
$xlsx->addSheet($unit_ref, 'Referensi Unit');
$xlsx->downloadAs('template_import_ruang.xlsx');
?>
