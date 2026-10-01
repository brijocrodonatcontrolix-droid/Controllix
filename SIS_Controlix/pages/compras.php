<?php
require_once '../includes/header.php';
require_once '../config/database.php';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'crear') {
        $idProveedor = $_POST['idProveedor'] ?? 0;
        $numeroFactura = $_POST['numeroFactura'] ?? '';
        $productos = json_decode($_POST['productos'], true);
        
        if (empty($productos)) {
            $mensaje = "Debe agregar al menos un producto";
            $tipo_mensaje = "danger";
        } else {
            // Calcular subtotal
            $subtotal = 0;
            foreach ($productos as $p) {
                $subtotal += $p['precio'] * $p['cantidad'];
            }
            $iva = $subtotal * 0.15;
            $total = $subtotal + $iva;
            
            // Insertar compra
            $sql = "INSERT INTO Compras (idProveedor, idUsuario, numeroFactura, subtotal, iva, total) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $idCompra = insert($sql, [
                $idProveedor,
                $_SESSION['usuario_id'] ?? 1,
                $numeroFactura,
                $subtotal,
                $iva,
                $total
            ]);
            
            // Guardar detalles
            foreach ($productos as $p) {
                $sqlDetalle = "INSERT INTO DetalleCompras (idCompra, idProducto, cantidad, precioCompra, subtotal) 
                              VALUES (?, ?, ?, ?, ?)";
                execute($sqlDetalle, [
                    $idCompra,
                    $p['id'],
                    $p['cantidad'],
                    $p['precio'],
                    $p['precio'] * $p['cantidad']
                ]);
                
                // Actualizar stock del producto y precio de compra
                execute("UPDATE Productos SET 
                        stockActual = stockActual + ?,
                        precioCompra = ?
                        WHERE idProducto = ?", [
                    $p['cantidad'],
                    $p['precio'],
                    $p['id']
                ]);
            }
            
            $mensaje = "Compra registrada correctamente";
            $tipo_mensaje = "success";
        }
    }
}

// Obtener compras
$compras = fetchAll("
    SELECT 
        C.*,
        P.nombreEmpresa,
        U.nombres as usuario_nombre
    FROM Compras C
    LEFT JOIN Proveedores P ON C.idProveedor = P.idProveedor
    LEFT JOIN Usuarios U ON C.idUsuario = U.idUsuario
    ORDER BY C.fechaCompra DESC
");

// Obtener proveedores para el select
$proveedores = fetchAll("SELECT idProveedor, nombreEmpresa FROM Proveedores WHERE estado = 1 ORDER BY nombreEmpresa");
$productos = fetchAll("SELECT idProducto, nombreProducto, precioCompra, stockActual FROM Productos WHERE estado = 1 ORDER BY nombreProducto");
?>

<header class="header">
    <div>
        <h2>Gestión de Compras</h2>
        <p>Registrar y consultar compras</p>
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
        <h2 class="section-title">Lista de Compras</h2>
        <button class="btn btn-primary" onclick="mostrarModal()">
            <i class="fas fa-plus"></i> Nueva Compra
        </button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Proveedor</th>
                        <th>Factura</th>
                        <th>Fecha</th>
                        <th>Subtotal</th>
                        <th>IVA</th>
                        <th>Total</th>
                        <th>Usuario</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($compras)): ?>
                        <tr><td colspan="9" class="text-center">No hay compras registradas</td></tr>
                    <?php else: ?>
                        <?php foreach ($compras as $c): ?>
                            <tr>
                                <td><?php echo $c['idCompra']; ?></td>
                                <td><?php echo htmlspecialchars($c['nombreEmpresa'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($c['numeroFactura'] ?? ''); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($c['fechaCompra'])); ?></td>
                                <td><?php echo MONEDA . ' ' . number_format($c['subtotal'], 2); ?></td>
                                <td><?php echo MONEDA . ' ' . number_format($c['iva'], 2); ?></td>
                                <td><strong><?php echo MONEDA . ' ' . number_format($c['total'], 2); ?></strong></td>
                                <td><?php echo htmlspecialchars($c['usuario_nombre'] ?? ''); ?></td>
                                <td>
                                    <button class="btn btn-info btn-sm" onclick="verDetalle(<?php echo $c['idCompra']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="eliminarCompra(<?php echo $c['idCompra']; ?>)">
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

    <!-- Modal Nueva Compra -->
    <div id="modalCompra" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; justify-content:center; align-items:center;">
        <div style="background:white; border-radius:10px; padding:30px; max-width:700px; width:100%; max-height:80vh; overflow-y:auto;">
            <h3 id="modalTitulo">Nueva Compra</h3>
            <form method="POST" action="" id="formCompra">
                <input type="hidden" name="action" value="crear">
                <input type="hidden" name="productos" id="productosData" value="">
                
                <div class="form-group">
                    <label>Proveedor</label>
                    <select name="idProveedor" class="form-control" required>
                        <option value="">Seleccione un proveedor</option>
                        <?php foreach ($proveedores as $p): ?>
                            <option value="<?php echo $p['idProveedor']; ?>">
                                <?php echo htmlspecialchars($p['nombreEmpresa']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Número de Factura</label>
                    <input type="text" name="numeroFactura" class="form-control" placeholder="Factura del proveedor">
                </div>
                
                <div class="form-group">
                    <label>Agregar Productos</label>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <select id="productoSelect" class="form-control" style="flex:2; min-width:200px;">
                            <option value="">Seleccionar producto</option>
                            <?php foreach ($productos as $p): ?>
                                <option value="<?php echo $p['idProducto']; ?>" 
                                        data-precio="<?php echo $p['precioCompra'] ?? 0; ?>"
                                        data-nombre="<?php echo htmlspecialchars($p['nombreProducto']); ?>">
                                    <?php echo htmlspecialchars($p['nombreProducto']); ?> 
                                    (C$<?php echo number_format($p['precioCompra'] ?? 0, 2); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" id="cantidadProducto" class="form-control" placeholder="Cantidad" style="width:120px;" value="1" min="1">
                        <input type="number" id="precioProducto" class="form-control" placeholder="Precio C$" style="width:150px;" step="0.01">
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
                
                <div style="display:flex; justify-content:space-between; padding:10px 0; border-top:1px solid #e2e8f0; margin-top:10px;">
                    <span>Subtotal: <strong id="subtotalDisplay">C$ 0.00</strong></span>
                    <span>IVA (15%): <strong id="ivaDisplay">C$ 0.00</strong></span>
                    <span>Total: <strong id="totalDisplay">C$ 0.00</strong></span>
                </div>
                
                <div style="display:flex; gap:10px; margin-top:15px;">
                    <button type="submit" class="btn btn-success" id="btnGuardar">Guardar Compra</button>
                    <button type="button" class="btn btn-danger" onclick="cerrarModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
let productosSeleccionados = [];

function mostrarModal() {
    document.getElementById('modalCompra').style.display = 'flex';
    productosSeleccionados = [];
    actualizarLista();
    calcularTotales();
    document.getElementById('productosData').value = '';
}

function cerrarModal() {
    document.getElementById('modalCompra').style.display = 'none';
}

function agregarProducto() {
    const select = document.getElementById('productoSelect');
    const cantidad = parseInt(document.getElementById('cantidadProducto').value) || 1;
    let precio = parseFloat(document.getElementById('precioProducto').value);
    const option = select.options[select.selectedIndex];
    
    if (!option.value) {
        alert('Seleccione un producto');
        return;
    }
    
    // Si no se especificó precio, usar el de la base de datos
    if (!precio || precio <= 0) {
        precio = parseFloat(option.dataset.precio) || 0;
    }
    
    if (precio <= 0) {
        alert('El precio debe ser mayor a 0');
        return;
    }
    
    const producto = {
        id: option.value,
        nombre: option.dataset.nombre || option.text.split(' - ')[0],
        precio: precio,
        cantidad: cantidad
    };
    
    // Verificar si ya existe
    const existente = productosSeleccionados.find(p => p.id === producto.id);
    if (existente) {
        existente.cantidad += cantidad;
        existente.precio = precio;
    } else {
        productosSeleccionados.push(producto);
    }
    
    actualizarLista();
    calcularTotales();
    
    // Resetear campos
    document.getElementById('productoSelect').value = '';
    document.getElementById('cantidadProducto').value = 1;
    document.getElementById('precioProducto').value = '';
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
    const iva = subtotal * 0.15;
    const total = subtotal + iva;
    
    document.getElementById('subtotalDisplay').textContent = `C$ ${subtotal.toFixed(2)}`;
    document.getElementById('ivaDisplay').textContent = `C$ ${iva.toFixed(2)}`;
    document.getElementById('totalDisplay').textContent = `C$ ${total.toFixed(2)}`;
}

// Actualizar precio automático al seleccionar producto
document.getElementById('productoSelect').addEventListener('change', function() {
    const option = this.options[this.selectedIndex];
    if (option.value) {
        const precio = parseFloat(option.dataset.precio) || 0;
        document.getElementById('precioProducto').value = precio;
    }
});

// Enviar formulario con productos
document.getElementById('formCompra').addEventListener('submit', function(e) {
    if (productosSeleccionados.length === 0) {
        e.preventDefault();
        alert('Debe agregar al menos un producto');
        return;
    }
    document.getElementById('productosData').value = JSON.stringify(productosSeleccionados);
});

function verDetalle(id) {
    window.open(`compra_detalle.php?id=${id}`, '_blank', 'width=800,height=600');
}

function eliminarCompra(id) {
    if (confirm('¿Estás seguro de eliminar esta compra?')) {
        fetch(`../api/compras.php?action=eliminar&id=${id}`)
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error al eliminar la compra');
                }
            });
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>