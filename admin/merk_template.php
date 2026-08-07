<?php
session_start();
include '../config/database.php';
require_once '../plugins/simplexlsx/SimpleXLSXGen.php';

// Sheet 1: Template
$header = [
    '<b>Nama Merk</b>'
];
$data = [
    $header,
    ['Samsung'],
    ['Asus'],
    ['IKEA'],
    ['Honda']
];

$xlsx = Shuchkin\SimpleXLSXGen::fromArray($data, 'Template');
$xlsx->downloadAs('template_import_merk.xlsx');
?>
