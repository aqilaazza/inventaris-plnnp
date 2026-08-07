<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['level'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

if (!isset($_GET['ids'])) {
    die("ID Barang tidak ditemukan!");
}

$ids_str = $_GET['ids'];
$ids = array_map('intval', explode(',', $ids_str));
$ids_safe = implode(',', $ids);

$query = "SELECT b.*, u.nama_unit, r.nama_ruang 
          FROM barang b 
          LEFT JOIN unit u ON b.id_unit = u.id 
          LEFT JOIN ruang r ON b.id_ruang = r.id 
          WHERE b.id IN ($ids_safe)
          ORDER BY b.id DESC";
$res = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Massal QR Code</title>
    <style>
        /* Thermal Sticker 60x40mm */
        @page {
            size: 60mm 40mm;
            margin: 0;
        }
        body {
            margin: 0;
            padding: 0;
            background: #fff;
        }
        .label-page {
            width: 60mm;
            height: 40mm;
            padding: 2mm;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
            overflow: hidden;
            display: flex;
            align-items: center;
            page-break-after: always;
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
            width: 25mm; /* Fixed width */
            flex-shrink: 0; /* Prevent shrinking */
        }
        .qrcode {
            width: 24mm;
            height: 24mm;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .qrcode img, .qrcode canvas {
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
            min-width: 0; /* Important for flex overflow */
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
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body onload="generateAll(); setTimeout(window.print, 1000);">
    <?php while ($row = $res->fetch_assoc()): 
        $item_code = str_pad($row['id'], 5, "0", STR_PAD_LEFT);
    ?>
    <div class="label-page">
        <div class="container">
            <div class="qr-section">
                <div class="qrcode" data-code="<?= $item_code ?>"></div>
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
    </div>
    <?php endwhile; ?>

    <script>
        function generateAll() {
            document.querySelectorAll('.qrcode').forEach(function(el) {
                let code = el.getAttribute('data-code');
                new QRCode(el, {
                    text: code,
                    width: 128,
                    height: 128,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.M
                });
            });
        }
    </script>
</body>
</html>
