<?php
require_once '../includes/header.php';
require_once '../config/database.php';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'crear') {
        $tipo = $_POST['tipoMovimiento'];
        $cantidad = intval($_POST['cantidad']);
        $idProducto = $_POST['idProducto'];
        $cantidadStock = ($tipo === 'Salida' || $tipo === 'Ajuste') ? -$cantidad : $cantidad;
        
        // Insertar movimiento
        $sql = "INSERT INTO MovimientosInventario (idProducto, idUsuario, tipoMovimiento, cantidad, observacion) 
                VALUES (?, ?, ?, ?, ?)";
        execute($sql, [
            $idProducto,
            $_SESSION['usuario_id'] ?? 1,
            $tipo,
            $cantidad,
            $_POST['observacion'] ?? ''
        ]);
        
        // Actualizar stock
        execute("UPDATE Productos SET stockActual = stockActual + ? WHERE idProducto = ?", [
            $cantidadStock,
            $idProducto
        ]);
        
        $mensaje = "Movimiento registrado correctamente";
        $tipo_mensaje = "success";
    }
    
    if ($action === 'editar') {
        $idMovimiento = $_POST['idMovimiento'];
        $tipo = $_POST['tipoMovimiento'];
        $cantidad = intval($_POST['cantidad']);
        $idProducto = $_POST['idProducto'];
        
        // Obtener movimiento anterior
        $movimientoAnterior = fetchOne("SELECT * FROM MovimientosInventario WHERE idMovimiento = ?", [$idMovimiento]);
        
        // Calcular diferencia de stock
        $cantidadAnterior = $movimientoAnterior['cantidad'];
        $tipoAnterior = $movimientoAnterior['tipoMovimiento'];
        $productoAnterior = $movimientoAnterior['idProducto'];
        
        // Restaurar stock anterior
        $cantidadStockAnterior = ($tipoAnterior === 'Salida' || $tipoAnterior === 'Ajuste') ? -$cantidadAnterior : $cantidadAnterior;
        execute("UPDATE Productos SET stockActual = stockActual - ? WHERE idProducto = ?", [
            $cantidadStockAnterior,
            $productoAnterior
        ]);
        
        // Aplicar nuevo movimiento
        $cantidadStockNuevo = ($tipo === 'Salida' || $tipo === 'Ajuste') ? -$cantidad : $cantidad;
        execute("UPDATE Productos SET stockActual = stockActual + ? WHERE idProducto = ?", [
            $cantidadStockNuevo,
            $idProducto
        ]);
        
        // Actualizar movimiento
        $sql = "UPDATE MovimientosInventario SET 
                idProducto = ?, tipoMovimiento = ?, cantidad = ?, observacion = ? 
                WHERE idMovimiento = ?";
        execute($sql, [
            $idProducto,
            $tipo,
            $cantidad,
            $_POST['observacion'] ?? '',
            $idMovimiento
        ]);
        
        $mensaje = "Movimiento actualizado correctamente";
        $tipo_mensaje = "success";
    }
    
    if ($action === 'eliminar') {
        $idMovimiento = $_POST['id'];
        
        // Obtener movimiento para restaurar stock
        $movimiento = fetchOne("SELECT * FROM MovimientosInventario WHERE idMovimiento = ?", [$idMovimiento]);
        
        // Restaurar stock
        $cantidadStock = ($movimiento['tipoMovimiento'] === 'Salida' || $movimiento['tipoMovimiento'] === 'Ajuste') ? -$movimiento['cantidad'] : $movimiento['cantidad'];
        execute("UPDATE Productos SET stockActual = stockActual - ? WHERE idProducto = ?", [
            $cantidadStock,
            $movimiento['idProducto']
        ]);
        
        // Eliminar movimiento
        execute("DELETE FROM MovimientosInventario WHERE idMovimiento = ?", [$idMovimiento]);
        
        $mensaje = "Movimiento eliminado correctamente";
        $tipo_mensaje = "success";
    }
}

// Obtener movimientos
$movimientos = fetchAll("
    SELECT 
        M.*,
        P.nombreProducto,
        U.nombres as usuario_nombre
    FROM MovimientosInventario M
    LEFT JOIN Productos P ON M.idProducto = P.idProducto
    LEFT JOIN Usuarios U ON M.idUsuario = U.idUsuario
    ORDER BY M.fecha DESC
    LIMIT 100
");

// Obtener productos para el select
$productos = fetchAll("SELECT idProducto, nombreProducto, stockActual FROM Productos WHERE estado = 1 ORDER BY nombreProducto");
?>

<header class="header">
    <div>
        <h2>Movimientos de Inventario</h2>
        <p>Registrar, editar y consultar movimientos</p>
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
    <?php if (isset($mensaje)): ?>
        <div class="alert-system alert-<?php echo $tipo_mensaje; ?>"><?php echo $mensaje; ?></div>
    <?php endif; ?>

    <div class="flex-between mb-3">
        <h2 class="section-title">Registro de Movimientos</h2>
        <button class="btn btn-primary" onclick="mostrarModal()">
            <i class="fas fa-plus"></i> Nuevo Movimiento
        </button>
    </div>

    <!-- Filtros -->
    <div class="card" style="margin-bottom:20px;">
        <div style="display:flex; gap:15px; flex-wrap:wrap; align-items:end;">
            <div class="form-group" style="flex:1; min-width:150px;">
                <label>Tipo de Movimiento</label>
                <select id="filtroTipo" class="form-control" onchange="aplicarFiltros()">
                    <option value="">Todos</option>
                    <option value="Entrada">Entrada</option>
                    <option value="Salida">Salida</option>
                    <option value="Ajuste">Ajuste</option>
                </select>
            </div>
            <div class="form-group" style="flex:1; min-width:150px;">
                <label>Producto</label>
                <select id="filtroProducto" class="form-control" onchange="aplicarFiltros()">
                    <option value="">Todos</option>
                    <?php foreach ($productos as $p): ?>
                        <option value="<?php echo $p['idProducto']; ?>">
                            <?php echo htmlspecialchars($p['nombreProducto']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex:1; min-width:150px;">
                <label>Fecha desde</label>
                <input type="date" id="filtroFecha" class="form-control" onchange="aplicarFiltros()">
            </div>
            <button class="btn btn-secondary" onclick="limpiarFiltros()">
                <i class="fas fa-undo"></i> Limpiar
            </button>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table id="tablaMovimientos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Producto</th>
                        <th>Tipo</th>
                        <th>Cantidad</th>
                        <th>Usuario</th>
                        <th>Observación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="movimientosBody">
                    <?php if (empty($movimientos)): ?>
                        <tr>
                            <td colspan="8" class="text-center">No hay movimientos registrados</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($movimientos as $m): ?>
                            <tr data-tipo="<?php echo $m['tipoMovimiento']; ?>" 
                                data-producto="<?php echo $m['idProducto']; ?>"
                                data-fecha="<?php echo date('Y-m-d', strtotime($m['fecha'])); ?>">
                                <td><?php echo $m['idMovimiento']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($m['fecha'])); ?></td>
                                <td><?php echo htmlspecialchars($m['nombreProducto'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge <?php 
                                        echo $m['tipoMovimiento'] === 'Entrada' ? 'paid' : 
                                            ($m['tipoMovimiento'] === 'Salida' ? 'pending' : 'badge-warning'); 
                                    ?>">
                                        <?php echo $m['tipoMovimiento']; ?>
                                    </span>
                                </td>
                                <td><?php echo $m['cantidad']; ?></td>
                                <td><?php echo htmlspecialchars($m['usuario_nombre'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($m['observacion'] ?? ''); ?></td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="editarMovimiento(<?php echo $m['idMovimiento']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="eliminarMovimiento(<?php echo $m['idMovimiento']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Nuevo/Editar Movimiento -->
    <div id="modalMovimiento" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; justify-content:center; align-items:center;">
        <div style="background:white; border-radius:10px; padding:30px; max-width:500px; width:100%;">
            <h3 id="modalTitulo">Nuevo Movimiento</h3>
            <form method="POST" action="" id="formMovimiento">
                <input type="hidden" name="action" id="formAction" value="crear">
                <input type="hidden" name="idMovimiento" id="idMovimiento" value="">
                
                <div class="form-group">
                    <label>Producto</label>
                    <select name="idProducto" id="idProductoMovimiento" class="form-control" required onchange="mostrarStock()">
                        <option value="">Seleccione un producto</option>
                        <?php foreach ($productos as $p): ?>
                            <option value="<?php echo $p['idProducto']; ?>" data-stock="<?php echo $p['stockActual']; ?>">
                                <?php echo htmlspecialchars($p['nombreProducto']); ?> (Stock: <?php echo $p['stockActual']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Tipo de Movimiento</label>
                    <select name="tipoMovimiento" id="tipoMovimiento" class="form-control" required onchange="validarStock()">
                        <option value="">Seleccione un tipo</option>
                        <option value="Entrada">Entrada (Aumenta stock)</option>
                        <option value="Salida">Salida (Disminuye stock)</option>
                        <option value="Ajuste">Ajuste (Corrección)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Cantidad</label>
                    <input type="number" name="cantidad" id="cantidadMovimiento" class="form-control" required min="1" onchange="validarStock()">
                    <small id="stockInfo" style="color:#64748b;">Stock actual: 0</small>
                </div>
                
                <div class="form-group">
                    <label>Observación</label>
                    <textarea name="observacion" id="observacionMovimiento" class="form-control" rows="2" placeholder="Motivo del movimiento"></textarea>
                </div>
                
                <div id="alertaStock" style="display:none; padding:10px; background:#fee2e2; border-radius:6px; margin-bottom:15px; color:#991b1b; font-size:13px;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span id="mensajeAlerta">La cantidad excede el stock disponible</span>
                </div>
                
                <div style="display:flex; gap:10px; margin-top:15px;">
                    <button type="submit" class="btn btn-success">Guardar</button>
                    <button type="button" class="btn btn-danger" onclick="cerrarModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
// Variables globales
let productosSeleccionados = [];

function mostrarModal() {
    document.getElementById('modalTitulo').textContent = 'Nuevo Movimiento';
    document.getElementById('formAction').value = 'crear';
    document.getElementById('idMovimiento').value = '';
    document.getElementById('idProductoMovimiento').value = '';
    document.getElementById('tipoMovimiento').value = '';
    document.getElementById('cantidadMovimiento').value = '';
    document.getElementById('observacionMovimiento').value = '';
    document.getElementById('alertaStock').style.display = 'none';
    document.getElementById('stockInfo').textContent = 'Stock actual: 0';
    document.getElementById('modalMovimiento').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modalMovimiento').style.display = 'none';
}

function mostrarStock() {
    const select = document.getElementById('idProductoMovimiento');
    const option = select.options[select.selectedIndex];
    const stock = option.dataset.stock || 0;
    document.getElementById('stockInfo').textContent = `Stock actual: ${stock}`;
    validarStock();
}

function validarStock() {
    const select = document.getElementById('idProductoMovimiento');
    const option = select.options[select.selectedIndex];
    const stock = parseInt(option.dataset.stock || 0);
    const tipo = document.getElementById('tipoMovimiento').value;
    const cantidad = parseInt(document.getElementById('cantidadMovimiento').value) || 0;
    const alerta = document.getElementById('alertaStock');
    const mensaje = document.getElementById('mensajeAlerta');
    
    if ((tipo === 'Salida' || tipo === 'Ajuste') && cantidad > stock) {
        alerta.style.display = 'block';
        mensaje.textContent = `La cantidad (${cantidad}) excede el stock disponible (${stock})`;
    } else {
        alerta.style.display = 'none';
    }
}

function editarMovimiento(id) {
    // Obtener datos del movimiento
    fetch(`../api/movimientos.php?action=obtener&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const m = data.data;
                document.getElementById('modalTitulo').textContent = 'Editar Movimiento';
                document.getElementById('formAction').value = 'editar';
                document.getElementById('idMovimiento').value = m.idMovimiento;
                document.getElementById('idProductoMovimiento').value = m.idProducto;
                document.getElementById('tipoMovimiento').value = m.tipoMovimiento;
                document.getElementById('cantidadMovimiento').value = m.cantidad;
                document.getElementById('observacionMovimiento').value = m.observacion || '';
                
                // Actualizar stock info
                const select = document.getElementById('idProductoMovimiento');
                const option = select.options[select.selectedIndex];
                if (option) {
                    document.getElementById('stockInfo').textContent = `Stock actual: ${option.dataset.stock || 0}`;
                }
                
                document.getElementById('alertaStock').style.display = 'none';
                document.getElementById('modalMovimiento').style.display = 'flex';
            }
        });
}

function eliminarMovimiento(id) {
    if (confirm('¿Estás seguro de eliminar este movimiento? Esto restaurará el stock.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="eliminar">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function aplicarFiltros() {
    const tipo = document.getElementById('filtroTipo').value;
    const producto = document.getElementById('filtroProducto').value;
    const fecha = document.getElementById('filtroFecha').value;
    
    const rows = document.querySelectorAll('#movimientosBody tr');
    
    rows.forEach(row => {
        let mostrar = true;
        
        if (tipo && row.dataset.tipo !== tipo) {
            mostrar = false;
        }
        
        if (producto && row.dataset.producto !== producto) {
            mostrar = false;
        }
        
        if (fecha && row.dataset.fecha !== fecha) {
            mostrar = false;
        }
        
        row.style.display = mostrar ? '' : 'none';
    });
}

function limpiarFiltros() {
    document.getElementById('filtroTipo').value = '';
    document.getElementById('filtroProducto').value = '';
    document.getElementById('filtroFecha').value = '';
    aplicarFiltros();
}

// Validar antes de enviar
document.getElementById('formMovimiento').addEventListener('submit', function(e) {
    const tipo = document.getElementById('tipoMovimiento').value;
    const cantidad = parseInt(document.getElementById('cantidadMovimiento').value) || 0;
    const producto = document.getElementById('idProductoMovimiento').value;
    
    if (!producto) {
        e.preventDefault();
        alert('Seleccione un producto');
        return;
    }
    
    if (!tipo) {
        e.preventDefault();
        alert('Seleccione un tipo de movimiento');
        return;
    }
    
    if (cantidad <= 0) {
        e.preventDefault();
        alert('La cantidad debe ser mayor a 0');
        return;
    }
    
    // Si es salida, verificar stock
    if (tipo === 'Salida' || tipo === 'Ajuste') {
        const select = document.getElementById('idProductoMovimiento');
        const option = select.options[select.selectedIndex];
        const stock = parseInt(option.dataset.stock || 0);
        
        if (cantidad > stock) {
            e.preventDefault();
            alert(`No hay suficiente stock. Disponible: ${stock}`);
            return;
        }
    }
});
</script>

<style>
.badge-warning {
    background: #fef3c7;
    color: #92400e;
}
</style>

<?php require_once '../includes/footer.php'; ?>