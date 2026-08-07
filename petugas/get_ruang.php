<?php
include '../config/database.php';

if (isset($_GET['unit_id'])) {
    $unit_id = intval($_GET['unit_id']);
    $stmt = $conn->prepare("SELECT id, nama_ruang FROM ruang WHERE id_unit = ? ORDER BY nama_ruang ASC");
    $stmt->bind_param("i", $unit_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $ruang = [];
    while ($row = $result->fetch_assoc()) {
        $ruang[] = $row;
    }

    echo json_encode($ruang);
}
