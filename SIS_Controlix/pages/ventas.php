<?php
require_once '../includes/header.php';
require_once '../config/database.php';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'crear') {
        $idCliente = $_POST['idCliente'] ?? 0;
        $productos = json_decode($_POST['productos'], true);
        
        if (empty($productos)) {
            $mensaje = "Debe agregar al menos un producto";
            $tipo_mensaje = "danger";
        } else {
            // SI NO HAY CLIENTE (idCliente = 0), CREAR CLIENTE GENERAL
            if ($idCliente == 0) {
                // Verificar si ya existe el cliente "General"
                $clienteGeneral = fetchOne("SELECT idCliente FROM Clientes WHERE nombres = 'Cliente' AND apellidos = 'General'");
                if ($clienteGeneral) {
                    $idCliente = $clienteGeneral['idCliente'];
                } else {
                    // Crear cliente general
                    $sqlCliente = "INSERT INTO Clientes (nombres, apellidos) VALUES (?, ?)";
                    $idCliente = insert($sqlCliente, ['Cliente', 'General']);
                }
            }
            
            // Calcular subtotal
            $subtotal = 0;
            foreach ($productos as $p) {
                $subtotal += $p['precio'] * $p['cantidad'];
            }
            $descuento = $_POST['descuento'] ?? 0;
            $iva = ($subtotal - $descuento) * 0.15;
            $total = ($subtotal - $descuento) + $iva;
            
            // Insertar venta
            $sql = "INSERT INTO Ventas (idCliente, idUsuario, subtotal, descuento, iva, total) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $idVenta = insert($sql, [
                $idCliente,
                $_SESSION['usuario_id'] ?? 1,
                $subtotal,
                $descuento,
                $iva,
                $total
            ]);
            
            // Insertar factura
            $numeroFactura = 'FAC-' . str_pad($idVenta, 5, '0', STR_PAD_LEFT);
            $sqlFactura = "INSERT INTO Facturas (idVenta, numeroFactura, estado) VALUES (?, ?, ?)";
            $idFactura = insert($sqlFactura, [
                $idVenta,
                $numeroFactura,
                'Pagada'
            ]);
            
            // Guardar detalles de factura
            foreach ($productos as $p) {
                $subtotalProducto = $p['precio'] * $p['cantidad'];
                $sqlDetalle = "INSERT INTO DetalleFacturas (idFactura, idProducto, cantidad, precioUnitario, subtotal) 
                              VALUES (?, ?, ?, ?, ?)";
                execute($sqlDetalle, [
                    $idFactura,
                    $p['id'],
                    $p['cantidad'],
                    $p['precio'],
                    $subtotalProducto
                ]);
                
                // Actualizar stock del producto
                execute("UPDATE Productos SET stockActual = stockActual - ? WHERE idProducto = ?", [
                    $p['cantidad'],
                    $p['id']
                ]);
            }
            
            $mensaje = "Venta registrada correctamente. Factura: $numeroFactura";
            $tipo_mensaje = "success";
        }
    }
}

// Obtener ventas
$ventas = fetchAll("
    SELECT 
        V.*,
        CONCAT(C.nombres, ' ', C.apellidos) as cliente,
        U.nombres as usuario_nombre
    FROM Ventas V
    LEFT JOIN Clientes C ON V.idCliente = C.idCliente
    LEFT JOIN Usuarios U ON V.idUsuario = U.idUsuario
    ORDER BY V.fechaVenta DESC
");

// Obtener clientes para el select
$clientes = fetchAll("SELECT idCliente, CONCAT(nombres, ' ', apellidos) as nombre_completo FROM Clientes ORDER BY apellidos, nombres");
// Obtener productos para el select
$productos = fetchAll("SELECT idProducto, nombreProducto, precioVenta, stockActual FROM Productos WHERE estado = 1 AND stockActual > 0 ORDER BY nombreProducto");
?>

<header class="header">
    <div>
        <h2>Gestión de Ventas</h2>
        <p>Registrar y consultar ventas</p>
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
        <h2 class="section-title">Lista de Ventas</h2>
        <button class="btn btn-primary" onclick="mostrarModal()">
            <i class="fas fa-plus"></i> Nueva Venta
        </button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Fecha</th>
                        <th>Subtotal</th>
                        <th>Descuento</th>
                        <th>IVA</th>
                        <th>Total</th>
                        <th>Usuario</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ventas)): ?>
                        <tr><td colspan="9" class="text-center">No hay ventas registradas</td></tr>
                    <?php else: ?>
                        <?php foreach ($ventas as $v): ?>
                            <tr>
                                <td><?php echo $v['idVenta']; ?></td>
                                <td><?php echo htmlspecialchars($v['cliente'] ?? 'Cliente General'); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($v['fechaVenta'])); ?></td>
                                <td><?php echo MONEDA . ' ' . number_format($v['subtotal'], 2); ?></td>
                                <td><?php echo MONEDA . ' ' . number_format($v['descuento'] ?? 0, 2); ?></td>
                                <td><?php echo MONEDA . ' ' . number_format($v['iva'], 2); ?></td>
                                <td><strong><?php echo MONEDA . ' ' . number_format($v['total'], 2); ?></strong></td>
                                <td><?php echo htmlspecialchars($v['usuario_nombre'] ?? ''); ?></td>
                                <td>
                                    <button class="btn btn-info btn-sm" onclick="verDetalle(<?php echo $v['idVenta']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="eliminarVenta(<?php echo $v['idVenta']; ?>)">
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

    <!-- Modal Nueva Venta -->
    <div id="modalVenta" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; justify-content:center; align-items:center;">
        <div style="background:white; border-radius:10px; padding:30px; max-width:700px; width:100%; max-height:80vh; overflow-y:auto;">
            <h3 id="modalTitulo">Nueva Venta</h3>
            <form method="POST" action="" id="formVenta">
                <input type="hidden" name="action" value="crear">
                <input type="hidden" name="productos" id="productosData" value="">
                
                <div class="form-group">
                    <label>Cliente</label>
                    <select name="idCliente" class="form-control" required>
                        <option value="0">Cliente General</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?php echo $c['idCliente']; ?>">
                                <?php echo htmlspecialchars($c['nombre_completo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Agregar Productos</label>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <select id="productoSelect" class="form-control" style="flex:2; min-width:200px;">
                            <option value="">Seleccionar producto</option>
                            <?php foreach ($productos as $p): ?>
                                <option value="<?php echo $p['idProducto']; ?>" 
                                        data-precio="<?php echo $p['precioVenta']; ?>"
                                        data-stock="<?php echo $p['stockActual']; ?>"
                                        data-nombre="<?php echo htmlspecialchars($p['nombreProducto']); ?>">
                                    <?php echo htmlspecialchars($p['nombreProducto']); ?> 
                                    (C$<?php echo number_format($p['precioVenta'], 2); ?>) 
                                    - Stock: <?php echo $p['stockActual']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" id="cantidadProducto" class="form-control" placeholder="Cantidad" style="width:120px;" value="1" min="1">
                        <button type="button" class="btn btn-primary" onclick="agregarProducto()">
                            <i class="fas fa-plus"></i> Agregar
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Productos seleccionados</label>
                    <div id="productosSeleccionados" style="border:1px solid #e2e8f0; border-radius:6px; padding:10px; min-height:50px; max-height:200px; overflow-y:auto;">
                        <p class="text-center" style="color:#94a3b8; font-size:12px;">No hay productos seleccionados</p>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Descuento (C$)</label>
                    <input type="number" name="descuento" id="descuentoVenta" class="form-control" step="0.01" value="0" onchange="calcularTotales()">
                </div>
                
                <div style="display:flex; justify-content:space-between; padding:10px 0; border-top:1px solid #e2e8f0; margin-top:10px;">
                    <span>Subtotal: <strong id="subtotalDisplay">C$ 0.00</strong></span>
                    <span>Descuento: <strong id="descuentoDisplay">C$ 0.00</strong></span>
                    <span>IVA (15%): <strong id="ivaDisplay">C$ 0.00</strong></span>
                    <span>Total: <strong id="totalDisplay">C$ 0.00</strong></span>
                </div>
                
                <div style="display:flex; gap:10px; margin-top:15px;">
                    <button type="submit" class="btn btn-success" id="btnGuardar">Guardar Venta</button>
                    <button type="button" class="btn btn-danger" onclick="cerrarModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
let productosSeleccionados = [];

function mostrarModal() {
    document.getElementById('modalVenta').style.display = 'flex';
    productosSeleccionados = [];
    actualizarLista();
    calcularTotales();
    document.getElementById('productosData').value = '';
    document.getElementById('descuentoVenta').value = 0;
}

function cerrarModal() {
    document.getElementById('modalVenta').style.display = 'none';
}

function agregarProducto() {
    const select = document.getElementById('productoSelect');
    const cantidad = parseInt(document.getElementById('cantidadProducto').value) || 1;
    const option = select.options[select.selectedIndex];
    
    if (!option.value) {
        alert('Seleccione un producto');
        return;
    }
    
    const stock = parseInt(option.dataset.stock) || 0;
    if (cantidad > stock) {
        alert(`No hay suficiente stock. Disponible: ${stock}`);
        return;
    }
    
    const producto = {
        id: option.value,
        nombre: option.dataset.nombre || option.text.split(' - ')[0],
        precio: parseFloat(option.dataset.precio) || 0,
        cantidad: cantidad
    };
    
    // Verificar si ya existe
    const existente = productosSeleccionados.find(p => p.id === producto.id);
    if (existente) {
        const nuevaCantidad = existente.cantidad + cantidad;
        if (nuevaCantidad > stock) {
            alert(`No hay suficiente stock. Disponible: ${stock}`);
            return;
        }
        existente.cantidad = nuevaCantidad;
    } else {
        productosSeleccionados.push(producto);
    }
    
    actualizarLista();
    calcularTotales();
    
    // Resetear campos
    document.getElementById('productoSelect').value = '';
    document.getElementById('cantidadProducto').value = 1;
}

function actualizarLista() {
    const container = document.getElementById('productosSeleccionados');
    if (productosSeleccionados.length === 0) {
        container.innerHTML = '<p class="text-center" style="color:#94a3b8; font-size:12px;">No hay productos seleccionados</p>';
        return;
    }
    
    container.innerHTML = productosSeleccionados.map((p, index) => `
        <div style="display:flex; justify-content:space-between; align-items:center; padding:5px 0; border-bottom:1px solid #f1f5f9;">
            <span style="flex:2;">${p.nombre}</span>
            <span style="flex:1; text-align:center;">Cant: ${p.cantidad}</span>
            <span style="flex:1; text-align:center;">C$ ${p.precio.toFixed(2)}</span>
            <span style="flex:1; text-align:right; font-weight:bold;">C$ ${(p.precio * p.cantidad).toFixed(2)}</span>
            <button type="button" class="btn btn-danger btn-sm" onclick="eliminarProducto(${index})">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `).join('');
}

function eliminarProducto(index) {
    productosSeleccionados.splice(index, 1);
    actualizarLista();
    calcularTotales();
}

function calcularTotales() {
    const subtotal = productosSeleccionados.reduce((sum, p) => sum + (p.precio * p.cantidad), 0);
    const descuento = parseFloat(document.getElementById('descuentoVenta').value) || 0;
    const base = subtotal - descuento;
    const iva = base * 0.15;
    const total = base + iva;
    
    document.getElementById('subtotalDisplay').textContent = `C$ ${subtotal.toFixed(2)}`;
    document.getElementById('descuentoDisplay').textContent = `C$ ${descuento.toFixed(2)}`;
    document.getElementById('ivaDisplay').textContent = `C$ ${iva.toFixed(2)}`;
    document.getElementById('totalDisplay').textContent = `C$ ${total.toFixed(2)}`;
}

// Enviar formulario con productos
document.getElementById('formVenta').addEventListener('submit', function(e) {
    if (productosSeleccionados.length === 0) {
        e.preventDefault();
        alert('Debe agregar al menos un producto');
        return;
    }
    document.getElementById('productosData').value = JSON.stringify(productosSeleccionados);
});

function verDetalle(id) {
    window.open(`venta_detalle.php?id=${id}`, '_blank', 'width=800,height=600');
}

function eliminarVenta(id) {
    if (confirm('¿Estás seguro de eliminar esta venta?')) {
        fetch(`../api/ventas.php?action=eliminar&id=${id}`)
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error al eliminar la venta');
                }
            });
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>