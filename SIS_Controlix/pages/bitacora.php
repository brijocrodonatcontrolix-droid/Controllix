<?php
require_once '../includes/header.php';
require_once '../config/database.php';

$bitacora = fetchAll("
    SELECT 
        B.*,
        U.nombres as usuario_nombre,
        U.apellidos as usuario_apellidos
    FROM Bitacora B
    LEFT JOIN Usuarios U ON B.idUsuario = U.idUsuario
    ORDER BY B.fecha DESC
    LIMIT 100
");
?>

<header class="header">
    <div>
        <h2>Bitácora del Sistema</h2>
        <p>Registro de actividades y acciones de usuarios</p>
    </div>
    <div class="header-right">
        <div class="notification"><i class="fas fa-bell"></i><span>4</span></div>
        <div class="user">
            <div class="avatar">AD</div>
            <div class="user-info">
                <strong><?php echo $_SESSION['usuario_nombre'] ?? 'Administrador'; ?></strong>
                <small>Administrador</small>
            </div>
        </div>
    </div>
</header>

<section class="content">
    <h2 class="section-title">Registro de Actividades</h2>
    
    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Tabla Afectada</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bitacora)): ?>
                        <tr><td colspan="5" class="text-center">No hay registros en la bitácora</td></tr>
                    <?php else: ?>
                        <?php foreach ($bitacora as $b): ?>
                            <tr>
                                <td><?php echo $b['idBitacora']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($b['fecha'])); ?></td>
                                <td><?php echo htmlspecialchars(($b['usuario_nombre'] ?? '') . ' ' . ($b['usuario_apellidos'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($b['accion']); ?></td>
                                <td><?php echo htmlspecialchars($b['tablaAfectada'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php require_once '../includes/footer.php'; ?>