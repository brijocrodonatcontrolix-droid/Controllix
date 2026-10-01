<?php
require_once '../includes/header.php';
require_once '../config/database.php';

// ============================================
// PROCESAR FORMULARIO CON PREVENCIÓN DE DUPLICADOS
// ============================================

// Verificar si ya se procesó este formulario (usando token)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Verificar token CSRF para prevenir duplicados
    $token = $_POST['token'] ?? '';
    $token_session = $_SESSION['form_token'] ?? '';
    
    // Si no hay token o no coincide, es un intento duplicado
    if (empty($token) || $token !== $token_session) {
        // No hacer nada, es un reenvío
    } else {
        // Limpiar token para que no se pueda reutilizar
        unset($_SESSION['form_token']);
        
        // Procesar según acción
        if ($action === 'crear_merma') {
            $idProducto = intval($_POST['idProducto'] ?? 0);
            $cantidad = intval($_POST['cantidad'] ?? 0);
            
            // VALIDAR: Producto existe
            if ($idProducto <= 0) {
                $mensaje = "Error: Producto no válido";
                $tipo_mensaje = "danger";
            }
            // VALIDAR: Cantidad mayor a 0
            elseif ($cantidad <= 0) {
                $mensaje = "Error: La cantidad debe ser mayor a 0";
                $tipo_mensaje = "danger";
            }
            else {
                // Obtener stock actual
                $producto = fetchOne("SELECT stockActual, nombreProducto FROM Productos WHERE idProducto = ? AND estado = 1", [$idProducto]);
                
                if (!$producto) {
                    $mensaje = "Error: Producto no encontrado";
                    $tipo_mensaje = "danger";
                }
                elseif ($producto['stockActual'] < $cantidad) {
                    $mensaje = "Error: No hay suficiente stock. Stock actual: {$producto['stockActual']}";
                    $tipo_mensaje = "danger";
                }
                else {
                    // Registrar merma
                    execute("INSERT INTO Mermas (idProducto, cantidad, motivo, idUsuario) VALUES (?, ?, ?, ?)", [
                        $idProducto, 
                        $cantidad, 
                        $_POST['motivo'] ?? '', 
                        $_SESSION['usuario_id'] ?? 1
                    ]);
                    
                    // Actualizar stock
                    execute("UPDATE Productos SET stockActual = stockActual - ? WHERE idProducto = ?", [
                        $cantidad, 
                        $idProducto
                    ]);
                    
                    $mensaje = "Merma registrada correctamente. Producto: {$producto['nombreProducto']}, Cantidad: $cantidad";
                    $tipo_mensaje = "success";
                }
            }
            
            // Redirigir con mensaje
            if (isset($mensaje)) {
                header("Location: mermas-alertas.php?mensaje=" . urlencode($mensaje) . "&tipo=" . $tipo_mensaje);
                exit();
            }
        }
        
        if ($action === 'marcar_leida') {
            $idAlerta = intval($_POST['idAlerta'] ?? 0);
            if ($idAlerta > 0) {
                execute("UPDATE Alertas SET leida = 1 WHERE idAlerta = ?", [$idAlerta]);
                $mensaje = "Alerta marcada como leída";
                $tipo_mensaje = "success";
            }
            header("Location: mermas-alertas.php?mensaje=" . urlencode($mensaje) . "&tipo=" . $tipo_mensaje);
            exit();
        }
        
        if ($action === 'eliminar_alerta') {
            $idAlerta = intval($_POST['idAlerta'] ?? 0);
            if ($idAlerta > 0) {
                execute("DELETE FROM Alertas WHERE idAlerta = ?", [$idAlerta]);
                $mensaje = "Alerta eliminada correctamente";
                $tipo_mensaje = "success";
            }
            header("Location: mermas-alertas.php?mensaje=" . urlencode($mensaje) . "&tipo=" . $tipo_mensaje);
            exit();
        }
        
        if ($action === 'eliminar_todas') {
            execute("DELETE FROM Alertas WHERE leida = 1");
            $mensaje = "Todas las alertas leídas han sido eliminadas";
            $tipo_mensaje = "success";
            header("Location: mermas-alertas.php?mensaje=" . urlencode($mensaje) . "&tipo=" . $tipo_mensaje);
            exit();
        }
    }
}

// ============================================
// GENERAR TOKEN PARA EL FORMULARIO
// ============================================

$_SESSION['form_token'] = bin2hex(random_bytes(32));

// ============================================
// MOSTRAR MENSAJES DESPUÉS DE REDIRECCIÓN
// ============================================

$mensaje = '';
$tipo_mensaje = '';
if (isset($_GET['mensaje'])) {
    $mensaje = urldecode($_GET['mensaje']);
    $tipo_mensaje = $_GET['tipo'] ?? 'success';
}

// ============================================
// OBTENER DATOS
// ============================================

// Obtener mermas
$mermas = fetchAll("
    SELECT 
        M.*,
        P.nombreProducto,
        U.nombres as usuario_nombre
    FROM Mermas M
    LEFT JOIN Productos P ON M.idProducto = P.idProducto
    LEFT JOIN Usuarios U ON M.idUsuario = U.idUsuario
    ORDER BY M.fecha DESC
    LIMIT 50
");

// Obtener alertas
$alertas = fetchAll("
    SELECT 
        A.*,
        P.nombreProducto
    FROM Alertas A
    LEFT JOIN Productos P ON A.idProducto = P.idProducto
    ORDER BY A.leida ASC, A.fecha DESC
");

// Obtener productos para selects (SOLO CON STOCK > 0)
$productos = fetchAll("
    SELECT idProducto, nombreProducto, stockActual, stockMinimo 
    FROM Productos 
    WHERE estado = 1 AND stockActual > 0
    ORDER BY nombreProducto
");

// Si no hay productos con stock, mostrar todos los activos
if (empty($productos)) {
    $productos = fetchAll("
        SELECT idProducto, nombreProducto, stockActual, stockMinimo 
        FROM Productos 
        WHERE estado = 1
        ORDER BY nombreProducto
    ");
}

// Generar alertas automáticas (stock bajo)
foreach ($productos as $p) {
    if ($p['stockActual'] <= $p['stockMinimo']) {
        $existe = fetchOne("SELECT COUNT(*) as total FROM Alertas WHERE idProducto = ? AND leida = 0", [$p['idProducto']]);
        if ($existe['total'] == 0) {
            execute("INSERT INTO Alertas (idProducto, tipoAlerta, mensaje) VALUES (?, 'Stock Bajo', ?)", [
                $p['idProducto'],
                "El producto '{$p['nombreProducto']}' tiene stock bajo ({$p['stockActual']} unidades)"
            ]);
        }
    }
}

// Contar alertas
$alertasNoLeidas = fetchOne("SELECT COUNT(*) as total FROM Alertas WHERE leida = 0")['total'] ?? 0;
$alertasLeidas = fetchOne("SELECT COUNT(*) as total FROM Alertas WHERE leida = 1")['total'] ?? 0;
?>

<header class="header">
    <div>
        <h2>Mermas y Alertas</h2>
        <p>Registrar mermas y gestionar alertas del sistema</p>
    </div>
    <div class="header-right">
        <div class="notification">
            <i class="fas fa-bell"></i>
            <span><?php echo $alertasNoLeidas; ?></span>
        </div>
        <div class="user">
            <div class="avatar"><?php echo strtoupper(substr($_SESSION['usuario_nombre'] ?? 'AD', 0, 2)); ?></div>
            <div class="user-info">
                <strong><?php echo $_SESSION['usuario_nombre'] ?? 'Administrador'; ?></strong>
                <small>Administrador</small>
            </div>
        </div>
    </div>
</header>

<section class="content">
    <?php if ($mensaje): ?>
        <div class="alert-system alert-<?php echo $tipo_mensaje; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>
    
    <!-- Resumen -->
    <div class="stats" style="margin-bottom:25px;">
        <div class="stat">
            <div>
                <h4>Alertas sin leer</h4>
                <strong style="color:#ef4444;"><?php echo $alertasNoLeidas; ?></strong>
            </div>
            <div class="stat-icon"><i class="fas fa-bell" style="color:#ef4444;"></i></div>
        </div>
        <div class="stat">
            <div>
                <h4>Alertas leídas</h4>
                <strong style="color:#22c55e;"><?php echo $alertasLeidas; ?></strong>
            </div>
            <div class="stat-icon"><i class="fas fa-check-circle" style="color:#22c55e;"></i></div>
        </div>
        <div class="stat">
            <div>
                <h4>Total de Mermas</h4>
                <strong><?php echo count($mermas); ?></strong>
            </div>
            <div class="stat-icon"><i class="fas fa-trash" style="color:#f59e0b;"></i></div>
        </div>
        <div class="stat">
            <div>
                <h4>Productos con stock bajo</h4>
                <strong style="color:#f59e0b;">
                    <?php 
                    $stockBajo = 0;
                    foreach ($productos as $p) {
                        if ($p['stockActual'] <= $p['stockMinimo']) $stockBajo++;
                    }
                    echo $stockBajo;
                    ?>
                </strong>
            </div>
            <div class="stat-icon"><i class="fas fa-exclamation-triangle" style="color:#f59e0b;"></i></div>
        </div>
    </div>

    <!-- Alertas -->
    <div class="flex-between mb-3">
        <h2 class="section-title">Alertas del Sistema</h2>
        <div style="display:flex; gap:10px;">
            <?php if ($alertasLeidas > 0): ?>
                <form method="POST" action="" onsubmit="return confirm('¿Eliminar todas las alertas leídas?')">
                    <input type="hidden" name="action" value="eliminar_todas">
                    <input type="hidden" name="token" value="<?php echo $_SESSION['form_token']; ?>">
                    <button type="submit" class="btn btn-warning btn-sm">
                        <i class="fas fa-broom"></i> Limpiar leídas
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <?php if (empty($alertas)): ?>
            <p class="text-center" style="color:#94a3b8; padding:20px 0;">
                <i class="fas fa-check-circle" style="color:#22c55e; font-size:24px; display:block; margin-bottom:10px;"></i>
                No hay alertas pendientes
            </p>
        <?php else: ?>
            <?php foreach ($alertas as $a): ?>
                <div class="alert" style="border-bottom:1px solid #e2e8f0; padding:15px 0; 
                    <?php echo $a['leida'] ? 'opacity:0.6;' : 'background:#fef3c7; padding:15px; border-radius:6px; margin-bottom:10px;'; ?>">
                    
                    <div class="alert-icon">
                        <i class="fas fa-<?php echo $a['leida'] ? 'check-circle' : 'exclamation-triangle'; ?>" 
                           style="color:<?php echo $a['leida'] ? '#22c55e' : '#f59e0b'; ?>; font-size:24px;">
                        </i>
                    </div>
                    
                    <div style="flex:1;">
                        <strong><?php echo htmlspecialchars($a['tipoAlerta'] ?? 'Alerta'); ?></strong>
                        <?php if (!$a['leida']): ?>
                            <span class="badge pending" style="margin-left:10px;">NUEVA</span>
                        <?php else: ?>
                            <span class="badge paid" style="margin-left:10px;">LEÍDA</span>
                        <?php endif; ?>
                        
                        <p style="font-size:13px; margin:5px 0;">
                            <?php echo htmlspecialchars($a['mensaje']); ?>
                            <?php if ($a['nombreProducto']): ?>
                                <br><small style="color:#64748b;">Producto: <?php echo htmlspecialchars($a['nombreProducto']); ?></small>
                            <?php endif; ?>
                        </p>
                        <small style="color:#94a3b8;"><?php echo date('d/m/Y H:i', strtotime($a['fecha'])); ?></small>
                    </div>
                    
                    <div style="display:flex; gap:5px; align-items:center;">
                        <?php if (!$a['leida']): ?>
                            <form method="POST" action="" style="margin:0;">
                                <input type="hidden" name="action" value="marcar_leida">
                                <input type="hidden" name="idAlerta" value="<?php echo $a['idAlerta']; ?>">
                                <input type="hidden" name="token" value="<?php echo $_SESSION['form_token']; ?>">
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="fas fa-check"></i> Marcar leída
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <form method="POST" action="" style="margin:0;" onsubmit="return confirm('¿Eliminar esta alerta?')">
                            <input type="hidden" name="action" value="eliminar_alerta">
                            <input type="hidden" name="idAlerta" value="<?php echo $a['idAlerta']; ?>">
                            <input type="hidden" name="token" value="<?php echo $_SESSION['form_token']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Mermas -->
    <div class="flex-between mb-3" style="margin-top:25px;">
        <h2 class="section-title">Registro de Mermas</h2>
        <button class="btn btn-primary" onclick="mostrarModalMerma()">
            <i class="fas fa-plus"></i> Nueva Merma
        </button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Motivo</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mermas)): ?>
                        <tr><td colspan="6" class="text-center">No hay mermas registradas</td></tr>
                    <?php else: ?>
                        <?php foreach ($mermas as $m): ?>
                            <tr>
                                <td><?php echo $m['idMerma']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($m['fecha'])); ?></td>
                                <td><?php echo htmlspecialchars($m['nombreProducto'] ?? 'N/A'); ?></td>
                                <td><span style="color:#dc2626;">-<?php echo $m['cantidad']; ?></span></td>
                                <td><?php echo htmlspecialchars($m['motivo'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($m['usuario_nombre'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Productos con stock bajo -->
    <h2 class="section-title">Productos con Stock Bajo</h2>
    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Producto</th>
                        <th>Stock Actual</th>
                        <th>Stock Mínimo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $stockBajoEncontrados = false;
                    foreach ($productos as $p):
                        if ($p['stockActual'] <= $p['stockMinimo']):
                            $stockBajoEncontrados = true;
                    ?>
                        <tr>
                            <td><?php echo $p['idProducto']; ?></td>
                            <td><?php echo htmlspecialchars($p['nombreProducto']); ?></td>
                            <td><strong style="color:#dc2626;"><?php echo $p['stockActual']; ?></strong></td>
                            <td><?php echo $p['stockMinimo']; ?></td>
                            <td>
                                <span class="badge pending">
                                    <i class="fas fa-exclamation-triangle"></i> Stock Bajo
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-primary btn-sm" onclick="window.location.href='movimientos.php'">
                                    <i class="fas fa-arrow-right"></i> Registrar Entrada
                                </button>
                            </td>
                        </tr>
                    <?php 
                        endif;
                    endforeach;
                    if (!$stockBajoEncontrados):
                    ?>
                        <tr>
                            <td colspan="6" class="text-center">
                                <i class="fas fa-check-circle" style="color:#22c55e;"></i>
                                Todos los productos tienen stock suficiente
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Nueva Merma -->
    <div id="modalMerma" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; justify-content:center; align-items:center;">
        <div style="background:white; border-radius:10px; padding:30px; max-width:500px; width:100%;">
            <h3>Registrar Merma</h3>
            <form method="POST" action="" id="formMerma">
                <input type="hidden" name="action" value="crear_merma">
                <input type="hidden" name="token" value="<?php echo $_SESSION['form_token']; ?>">
                
                <div class="form-group">
                    <label>Producto</label>
                    <select name="idProducto" id="selectProducto" class="form-control" required onchange="mostrarStockMerma()">
                        <option value="">Seleccione un producto</option>
                        <?php foreach ($productos as $p): ?>
                            <option value="<?php echo $p['idProducto']; ?>" data-stock="<?php echo $p['stockActual']; ?>">
                                <?php echo htmlspecialchars($p['nombreProducto']); ?> (Stock: <?php echo $p['stockActual']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Cantidad a dar de baja</label>
                    <input type="number" name="cantidad" id="cantidadMerma" class="form-control" required min="1" onchange="validarStockMerma()">
                    <small id="stockInfoMerma" style="color:#64748b;">Stock actual: 0</small>
                </div>
                
                <div class="form-group">
                    <label>Motivo</label>
                    <select name="motivo" class="form-control" required>
                        <option value="">Seleccione un motivo</option>
                        <option value="Daño">Daño</option>
                        <option value="Caducidad">Caducidad</option>
                        <option value="Robo">Robo</option>
                        <option value="Pérdida">Pérdida</option>
                        <option value="Devolución">Devolución</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Observación adicional</label>
                    <textarea name="observacion" class="form-control" rows="2" placeholder="Detalles adicionales..."></textarea>
                </div>
                
                <div id="alertaStockMerma" style="display:none; padding:10px; background:#fee2e2; border-radius:6px; margin-bottom:15px; color:#991b1b; font-size:13px;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span id="mensajeAlertaStock">La cantidad excede el stock disponible</span>
                </div>
                
                <div style="display:flex; gap:10px; margin-top:15px;">
                    <button type="submit" class="btn btn-danger" id="btnRegistrarMerma">Registrar Merma</button>
                    <button type="button" class="btn btn-secondary" onclick="cerrarModalMerma()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
function mostrarModalMerma() {
    document.getElementById('modalMerma').style.display = 'flex';
    document.getElementById('alertaStockMerma').style.display = 'none';
    document.getElementById('cantidadMerma').value = '';
    document.getElementById('selectProducto').value = '';
    document.getElementById('stockInfoMerma').textContent = 'Stock actual: 0';
}

function cerrarModalMerma() {
    document.getElementById('modalMerma').style.display = 'none';
}

function mostrarStockMerma() {
    const select = document.getElementById('selectProducto');
    const option = select.options[select.selectedIndex];
    const stock = option.dataset.stock || 0;
    document.getElementById('stockInfoMerma').textContent = `Stock actual: ${stock}`;
    validarStockMerma();
}

function validarStockMerma() {
    const select = document.getElementById('selectProducto');
    const option = select.options[select.selectedIndex];
    const stock = parseInt(option.dataset.stock || 0);
    const cantidad = parseInt(document.getElementById('cantidadMerma').value) || 0;
    const alerta = document.getElementById('alertaStockMerma');
    const mensaje = document.getElementById('mensajeAlertaStock');
    
    if (cantidad > 0 && cantidad > stock) {
        alerta.style.display = 'block';
        mensaje.textContent = `La cantidad (${cantidad}) excede el stock disponible (${stock})`;
    } else {
        alerta.style.display = 'none';
    }
}

// Validar antes de enviar
document.getElementById('formMerma').addEventListener('submit', function(e) {
    const select = document.getElementById('selectProducto');
    const cantidad = parseInt(document.getElementById('cantidadMerma').value) || 0;
    const option = select.options[select.selectedIndex];
    const stock = parseInt(option.dataset.stock || 0);
    
    if (!select.value) {
        e.preventDefault();
        alert('Seleccione un producto');
        return false;
    }
    
    if (cantidad <= 0) {
        e.preventDefault();
        alert('La cantidad debe ser mayor a 0');
        return false;
    }
    
    if (cantidad > stock) {
        e.preventDefault();
        alert(`No hay suficiente stock. Disponible: ${stock}`);
        return false;
    }
    
    return true;
});
</script>

<style>
.badge-warning {
    background: #fef3c7;
    color: #92400e;
}
</style>

<?php require_once '../includes/footer.php'; ?>