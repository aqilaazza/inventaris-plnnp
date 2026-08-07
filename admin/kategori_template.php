<?php
session_start();
include '../config/database.php';
require_once '../plugins/simplexlsx/SimpleXLSXGen.php';

// Sheet 1: Template
$header = [
    '<b>Nama Kategori</b>'
];
$data = [
    $header,
    ['Elektronik'],
    ['Mebel'],
    ['Alat Tulis Kantor']
];

$xlsx = Shuchkin\SimpleXLSXGen::fromArray($data, 'Template');
$xlsx->downloadAs('template_import_kategori.xlsx');
?>
