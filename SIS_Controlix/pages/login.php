<?php
session_start();
require_once '../config/database.php';

// Si hay parámetro logout, cerrar sesión
if (isset($_GET['logout'])) {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header('Location: login.php');
    exit();
}

if (isset($_SESSION['usuario_id'])) {
    header('Location: menu_de_inicio.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $password = $_POST['password'] ?? '';

   

    // Verificar en usuarios de prueba primero
    if (isset($usuariosPrueba[$usuario]) && $password === $usuariosPrueba[$usuario]['password']) {
        $user = $usuariosPrueba[$usuario];
        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['usuario_nombre'] = $user['nombre'];
        $_SESSION['usuario_rol'] = $user['rol_id'];
        $_SESSION['usuario_rol_id'] = $user['rol_id'];
        $_SESSION['usuario_rol_nombre'] = $user['nombre'];
        header('Location: menu_de_inicio.php');
        exit();
    }

    // Intentar con la base de datos
    try {
        $stmt = $pdo->prepare("SELECT * FROM Usuarios WHERE usuario = ? AND estado = 1");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['usuario_id'] = $user['idUsuario'];
            $_SESSION['usuario_nombre'] = $user['nombres'] . ' ' . $user['apellidos'];
            $_SESSION['usuario_rol_id'] = $user['idRol'];
            $_SESSION['usuario_rol'] = $user['idRol'];
            
            // Obtener nombre del rol
            try {
                $rol = fetchOne("SELECT nombreRol FROM Roles WHERE idRol = ?", [$user['idRol']]);
                $_SESSION['usuario_rol_nombre'] = $rol['nombreRol'] ?? 'Usuario';
            } catch (PDOException $e) {
                $_SESSION['usuario_rol_nombre'] = 'Usuario';
            }
            
            header('Location: menu_de_inicio.php');
            exit();
        }
    } catch (PDOException $e) {}

    $error = 'Usuario o contraseña incorrectos';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIS_Controlix | Login</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #1d4ed8, #0f172a);
        }
        .login-box {
            background: white;
            border-radius: 12px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .login-box .logo { text-align: center; border-bottom: none; margin-bottom: 30px; }
        .login-box .logo h1 { color: #0f172a; font-size: 28px; }
        .login-box .logo p { color: #64748b; font-size: 12px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 5px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; }
        .form-control:focus { border-color: #2563eb; outline: none; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .btn { width: 100%; padding: 12px; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-primary:hover { background: #1d4ed8; }
        .alert-system { padding: 12px 16px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .credenciales {
            margin-top: 20px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            font-size: 12px;
            color: #475569;
        }
        .credenciales strong { color: #0f172a; }
        .credenciales table { width: 100%; font-size: 12px; }
        .credenciales td { padding: 3px 5px; }
        .credenciales .badge { 
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-admin { background: #dbeafe; color: #1d4ed8; }
        .badge-gerente { background: #dcfce7; color: #166534; }
        .badge-supervisor { background: #fef3c7; color: #92400e; }
        .badge-almacen { background: #fce7f3; color: #9d174d; }
        .badge-cajero { background: #e0e7ff; color: #3730a3; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="logo">
                <h1>SIS_Controlix</h1>
                <p>Sistema de Control Empresarial</p>
            </div>

            <?php if (isset($_GET['logout'])): ?>
                <div class="alert-system alert-success">✅ Sesión cerrada correctamente</div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert-system alert-danger">❌ <?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="usuario"><i class="fas fa-user"></i> Usuario</label>
                    <input type="text" id="usuario" name="usuario" class="form-control" required placeholder="Ingresa tu usuario">
                </div>
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Contraseña</label>
                    <input type="password" id="password" name="password" class="form-control" required placeholder="Ingresa tu contraseña">
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                </button>
            </form>

           
            </div>
        </div>
    </div>
</body>
</html>