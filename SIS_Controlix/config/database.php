<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'SIS_Controlix');
define('DB_USER', 'root');
define('DB_PASS', '');

// Conexión PDO
try {
    $pdo = new PDO("sqlsrv:Server=".DB_HOST.";Database=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Si no hay SQL Server, intentar con MySQL (para desarrollo)
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

// Función para ejecutar consultas
function query($sql, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
// Función para registrar en bitácora
function registrarBitacora($idUsuario, $accion, $tablaAfectada = null) {
    $sql = "INSERT INTO Bitacora (idUsuario, accion, tablaAfectada) VALUES (?, ?, ?)";
    execute($sql, [$idUsuario, $accion, $tablaAfectada]);
}

// Función para obtener un registro
function fetchOne($sql, $params = []) {
    return query($sql, $params)->fetch();
}

// Función para obtener todos los registros
function fetchAll($sql, $params = []) {
    return query($sql, $params)->fetchAll();
}

// Función para ejecutar una consulta y obtener el ID insertado
function insert($sql, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $pdo->lastInsertId();
}

// Función para ejecutar una consulta sin retorno
function execute($sql, $params = []) {
    return query($sql, $params);
}

// Configuración de la aplicación
define('APP_NAME', 'SIS_Controlix');
define('APP_URL', 'http://localhost/SIS_Controlix/');
define('MONEDA', 'C$');
?>