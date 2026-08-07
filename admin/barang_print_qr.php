<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['level'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

if (!isset($_GET['id'])) {
    die("ID Barang tidak ditemukan!");
}

$id = intval($_GET['id']);
$query = "SELECT b.*, u.nama_unit, r.nama_ruang 
          FROM barang b 
          LEFT JOIN unit u ON b.id_unit = u.id 
          LEFT JOIN ruang r ON b.id_ruang = r.id 
          WHERE b.id = $id";
$res = $conn->query($query);
$row = $res->fetch_assoc();

if (!$row) {
    die("Data barang tidak ditemukan!");
}

// Format ID as 5 digit padded string
$item_code = str_pad($row['id'], 5, "0", STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak QR Code - <?= $item_code ?></title>
    <style>
        /* Thermal Sticker 60x40mm */
        @page {
            size: 60mm 40mm;
            margin: 0;
        }
        body {
            width: 60mm;
            height: 40mm;
            margin: 0;
            padding: 2mm;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
            overflow: hidden;
            background: #fff;
        }
        .container {
            display: flex;
            width: 100%;
            height: 100%;
            align-items: center;
            box-sizing: border-box;
        }
        .qr-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 25mm;
            flex-shrink: 0;
        }
        #qrcode {
            width: 24mm;
            height: 24mm;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #qrcode img, #qrcode canvas {
            max-width: 100%;
            max-height: 100%;
        }
        .item-code {
            font-size: 11px;
            font-weight: 800;
            margin-top: 2px;
            text-align: center;
            letter-spacing: 0.5px;
            width: 100%;
        }
        .info-section {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding-left: 3mm;
            overflow: hidden;
        }
        .item-name {
            font-size: 11px;
            font-weight: 800;
            margin-bottom: 4px;
            line-height: 1.2;
            word-break: break-word;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-transform: uppercase;
        }
        .item-detail {
            font-size: 9px;
            color: #000;
            line-height: 1.3;
            margin-bottom: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        @media print {
            .no-print {
                display: none;
            }
            .container {
                border: none;
            }
        }
    </style>
    <!-- Use qrcode.js for client-side generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body onload="window.print(); setTimeout(window.close, 500);">
    <div class="container">
        <div class="qr-section">
            <div id="qrcode"></div>
            <div class="item-code"><?= $item_code ?></div>
        </div>
        <div class="info-section">
            <div class="item-name"><?= htmlspecialchars($row['nama_barang']) ?></div>
            <div class="item-detail"><strong>Unit:</strong> <?= htmlspecialchars($row['nama_unit'] ?? '-') ?></div>
            <div class="item-detail"><strong>Ruang:</strong> <?= htmlspecialchars($row['nama_ruang'] ?? '-') ?></div>
            <div class="item-detail" style="margin-top: 2px; font-size: 7px; color: #666;">
                PT PLN NP UP PAITON
            </div>
        </div>
    </div>

    <script>
        new QRCode(document.getElementById("qrcode"), {
            text: "<?= $item_code ?>",
            width: 128,
            height: 128,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.M
        });
    </script>
</body>
</html>
