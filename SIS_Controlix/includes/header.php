<?php
session_start();
require_once '../config/database.php';

$pagina_actual = basename($_SERVER['PHP_SELF']);

if (!isset($_SESSION['usuario_id']) && $pagina_actual != 'login.php') {
    header('Location: login.php');
    exit();
}

// ============================================
// DEFINIR ROLES Y PERMISOS
// ============================================

$roles = [
    1 => ['nombre' => 'Administrador', 'color' => '#dbeafe'],
    2 => ['nombre' => 'Gerente', 'color' => '#dcfce7'],
    3 => ['nombre' => 'Supervisor', 'color' => '#fef3c7'],
    4 => ['nombre' => 'Vendedor', 'color' => '#fce7f3'],
    5 => ['nombre' => 'Almacenista', 'color' => '#e0e7ff'],
    6 => ['nombre' => 'Cajero', 'color' => '#f3e8ff'],
];

// Definir módulos por rol
$permisosPorRol = [
    1 => [ // Administrador - TODO
        'dashboard' => true, 'productos' => true, 'categorias' => true, 'marcas' => true,
        'movimientos' => true, 'mermas' => true, 'alertas' => true,
        'proveedores' => true, 'compras' => true, 'clientes' => true,
        'ventas' => true, 'facturas' => true,
        'empleados' => true, 'planilla' => true,
        'reportes' => true, 'bitacora' => true, 'usuarios' => true, 'configuracion' => true
    ],
    2 => [ // Gerente - Operaciones + Inventario + RRHH
        'dashboard' => true, 'productos' => true, 'categorias' => true, 'marcas' => true,
        'movimientos' => true, 'mermas' => true, 'alertas' => true,
        'proveedores' => true, 'compras' => true, 'clientes' => true,
        'ventas' => true, 'facturas' => true,
        'empleados' => true, 'planilla' => true,
        'reportes' => false, 'bitacora' => false, 'usuarios' => false, 'configuracion' => false
    ],
    3 => [ // Supervisor - Operaciones + Inventario
        'dashboard' => true, 'productos' => true, 'categorias' => true, 'marcas' => true,
        'movimientos' => true, 'mermas' => true, 'alertas' => true,
        'proveedores' => true, 'compras' => true, 'clientes' => true,
        'ventas' => true, 'facturas' => true,
        'empleados' => false, 'planilla' => false,
        'reportes' => false, 'bitacora' => false, 'usuarios' => false, 'configuracion' => false
    ],
    5 => [ // Almacenista - Solo Inventario
        'dashboard' => true, 'productos' => true, 'categorias' => true, 'marcas' => true,
        'movimientos' => true, 'mermas' => true, 'alertas' => true,
        'proveedores' => false, 'compras' => false, 'clientes' => false,
        'ventas' => false, 'facturas' => false,
        'empleados' => false, 'planilla' => false,
        'reportes' => false, 'bitacora' => false, 'usuarios' => false, 'configuracion' => false
    ],
    6 => [ // Cajero - Solo Operaciones
        'dashboard' => true, 'productos' => false, 'categorias' => false, 'marcas' => false,
        'movimientos' => false, 'mermas' => false, 'alertas' => false,
        'proveedores' => true, 'compras' => true, 'clientes' => true,
        'ventas' => true, 'facturas' => true,
        'empleados' => false, 'planilla' => false,
        'reportes' => false, 'bitacora' => false, 'usuarios' => false, 'configuracion' => false
    ],
];

// Obtener permisos del usuario
$modulosPermitidos = [];
$nombreRol = 'Usuario';
$rolId = $_SESSION['usuario_rol_id'] ?? 0;
$rolColor = '#64748b';

if ($rolId > 0 && isset($permisosPorRol[$rolId])) {
    $modulosPermitidos = $permisosPorRol[$rolId];
    $nombreRol = $roles[$rolId]['nombre'] ?? 'Usuario';
    $rolColor = $roles[$rolId]['color'] ?? '#64748b';
} else {
    // Si no hay rol definido, dar permisos básicos
    $modulosPermitidos = ['dashboard' => true];
}

function tienePermiso($modulo) {
    global $modulosPermitidos;
    return isset($modulosPermitidos[$modulo]) && $modulosPermitidos[$modulo] === true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIS_Controlix | Sistema de Control</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<aside class="sidebar">
    <div class="logo">
        <h1>SIS_Controlix</h1>
        <p>Sistema de Control Empresarial</p>
    </div>

    <ul class="menu">
        <!-- PRINCIPAL -->
        <li class="menu-title">Principal</li>
        <li>
            <a href="menu_de_inicio.php" class="<?php echo $pagina_actual == 'menu_de_inicio.php' ? 'active' : ''; ?>">
                <span class="icon"><i class="fas fa-home"></i></span>
                <span>Inicio</span>
            </a>
        </li>

        <!-- INVENTARIO -->
        <?php if (tienePermiso('productos') || tienePermiso('categorias') || tienePermiso('marcas') || tienePermiso('movimientos') || tienePermiso('mermas') || tienePermiso('alertas')): ?>
        <li class="menu-title">Inventario</li>
        <?php endif; ?>
        
        <?php if (tienePermiso('productos')): ?>
        <li><a href="productos.php" class="<?php echo $pagina_actual == 'productos.php' ? 'active' : ''; ?>"><span class="icon"><i class="fas fa-box"></i></span><span>Productos</span></a></li>
        <?php endif; ?>
        
        <?php if (tienePermiso('categorias')): ?>
        <li><a href="categorias.php" class="<?php echo $pagina_actual == 'categorias.php' ? 'active' : ''; ?>"><span class="icon"><i class="fas fa-tags"></i></span><span>Categorías</span></a></li>
        <?php endif; ?>
        
        <?php if (tienePermiso('marcas')): ?>
        <li><a href="marcas.php" class="<?php echo $pagina_actual == 'marcas.php' ? 'active' : ''; ?>"><span class="icon"><i class="fas fa-tag"></i></span><span>Marcas</span></a></li>
        <?php endif; ?>
        
        <?php if (tienePermiso('movimientos')): ?>
        <li><a href="movimientos.php" class="<?php echo $pagina_actual == 'movimientos.php' ? 'active' : ''; ?>"><span class="icon"><i class="fas fa-chart-bar"></i></span><span>Movimientos</span></a></li>
        <?php endif; ?>
        
        <?php if (tienePermiso('mermas') || tienePermiso('alertas')): ?>
        <li><a href="mermas-alertas.php" class="<?php echo $pagina_actual == 'mermas-alertas.php' ? 'active' : ''; ?>"><span class="icon"><i class="fas fa-exclamation-triangle"></i></span><span>Mermas y Alertas</span></a></li>
        <?php endif; ?>

        <!-- OPERACIONES -->
        <?php if (tienePermiso('proveedores') || tienePermiso('compras') || tienePermiso('clientes') || tienePermiso('ventas') || tienePermiso('facturas')): ?>
        <li class="menu-title">Operaciones</li>
        <?php endif; ?>
        
        <?php if (tienePermiso('proveedores')): ?>
        <li><a href="proveedores.php" class="<?php echo $pagina_actual == 'proveedores.php' ? 'active' : ''; ?>"><span class="icon"><i class="fas fa-truck"></i></span><span>Proveedores</span></a></li>
        <?php endif; ?>
        
        <?php if (tienePermiso('compras')): ?>
        <li><a href="compras.php" class="<?php echo $pagina_actual == 'compras.php' ? 'active' : ''; ?>"><span class="icon"><i class="fas fa-shopping-cart"></i></span><span>Compras</span></a></li>
        <?php endif; ?>
        
        <?php if (tienePermiso('clientes')): ?>
        <li><a href="clientes.php" class="<?php echo $pagina_actual == 'clientes.php' ? 'active' : ''; ?>"><span class="icon"><i class="fas fa-users"></i></span><span>Clientes</span></a></li>
        <?php endif; ?>
        
        <?php if (tienePermiso('ventas')): ?>
        <li><a href="ventas.php" class="<?php echo $pagina_actual == 'ventas.php' ? 'active' : ''; ?>"><span class="icon"><i class="fas fa-coins"></i></span><span>Ventas</span></a></li>
        <?php endif; ?>
        
        <?php if (tienePermiso('facturas')): ?>
        <li><a href="facturas.php" class="<?php echo $pagina_actual == 'facturas.php' ? 'active' : ''; ?>"><span class="icon"><i class="fas fa-file-invoice"></i></span><span>Facturas</span></a></li>
        <?php endif; ?>

        <!-- RECURSOS HUMANOS -->
        <?php if (tienePermiso('empleados') || tienePermiso('planilla')): ?>
        <li class="menu-title">Recursos Humanos</li>
        <?php endif; ?>
        
        <?php if (tienePermiso('empleados')): ?>
        <li><a href="empleados.php" class="<?php echo $pagina_actual == 'empleados.php' ? 'active' : ''; ?>"><span class="icon"><i class="fas fa-user-tie"></i></span><span>Empleados</span></a></li>
        <?php endif; ?>
        
        <?php if (tienePermiso('planilla')): ?>
        <li><a href="planilla.php" class="<?php echo $pagina_actual == 'planilla.php' ? 'active' : ''; ?>"><span class="icon"><i class="fas fa-money-bill-wave"></i></span><span>Planilla</span></a></li>
        <?php endif; ?>

        <!-- ADMINISTRACIÓN -->
        <?php if (tienePermiso('reportes') || tienePermiso('bitacora') || tienePermiso('usuarios') || tienePermiso('configuracion')): ?>
        <li class="menu-title">Administración</li>
        <?php endif; ?>
        
        <?php if (tienePermiso('reportes')): ?>
        <li><a href="reportes.php" class="<?php echo $pagina_actual == 'reportes.php' ? 'active' : ''; ?>"><span class="icon"><i class="fas fa-chart-line"></i></span><span>Reportes</span></a></li>
        <?php endif; ?>
        
        <?php if (tienePermiso('bitacora')): ?>
        <li><a href="bitacora.php" class="<?php echo $pagina_actual == 'bitacora.php' ? 'active' : ''; ?>"><span class="icon"><i class="fas fa-history"></i></span><span>Bitácora</span></a></li>
        <?php endif; ?>
        
        <?php if (tienePermiso('usuarios')): ?>
        <li><a href="usuarios.php" class="<?php echo $pagina_actual == 'usuarios.php' ? 'active' : ''; ?>"><span class="icon"><i class="fas fa-user-cog"></i></span><span>Usuarios</span></a></li>
        <?php endif; ?>
        
        <?php if (tienePermiso('configuracion')): ?>
        <li><a href="configuracion.php" class="<?php echo $pagina_actual == 'configuracion.php' ? 'active' : ''; ?>"><span class="icon"><i class="fas fa-cog"></i></span><span>Configuración</span></a></li>
        <?php endif; ?>

        <!-- CERRAR SESIÓN -->
        <li class="menu-title">&nbsp;</li>
        <li>
            <a href="#" id="logout-link">
                <span class="icon"><i class="fas fa-sign-out-alt"></i></span>
                <span>Cerrar sesión</span>
            </a>
        </li>
    </ul>
</aside>

<main class="main">