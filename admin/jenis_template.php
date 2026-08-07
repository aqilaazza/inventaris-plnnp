<?php
session_start();
include '../config/database.php';
require_once '../plugins/simplexlsx/SimpleXLSXGen.php';

// Sheet 1: Template
$header = [
    '<b>Nama Jenis</b>'
];
$data = [
    $header,
    ['Barang Modal'],
    ['Habis Pakai'],
    ['Elektronik'],
    ['Peralatan Kantor']
];

$xlsx = Shuchkin\SimpleXLSXGen::fromArray($data, 'Template');
$xlsx->downloadAs('template_import_jenis.xlsx');
?>
