<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'listar':
            $categorias = fetchAll("SELECT * FROM Categorias ORDER BY nombreCategoria");
            echo json_encode(['success' => true, 'data' => $categorias]);
            break;

        case 'obtener':
            $id = $_GET['id'] ?? 0;
            $categoria = fetchOne("SELECT * FROM Categorias WHERE idCategoria = ?", [$id]);
            echo json_encode(['success' => true, 'data' => $categoria]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>