<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'listar':
            $marcas = fetchAll("SELECT * FROM Marcas ORDER BY nombreMarca");
            echo json_encode(['success' => true, 'data' => $marcas]);
            break;

        case 'obtener':
            $id = $_GET['id'] ?? 0;
            $marca = fetchOne("SELECT * FROM Marcas WHERE idMarca = ?", [$id]);
            echo json_encode(['success' => true, 'data' => $marca]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>