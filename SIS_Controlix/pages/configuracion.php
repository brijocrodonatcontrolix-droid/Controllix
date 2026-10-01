<?php
require_once '../includes/header.php';
require_once '../config/database.php';

// Procesar formulario de configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'guardar_config') {
        // Verificar si ya existe configuración
        $existe = fetchOne("SELECT COUNT(*) as total FROM ConfiguracionSistema");
        
        if ($existe['total'] > 0) {
            $sql = "UPDATE ConfiguracionSistema SET 
                    nombreEmpresa = ?, 
                    direccion = ?, 
                    telefono = ?, 
                    correo = ?, 
                    impuesto = ?,
                    logo = ?
                    WHERE idConfiguracion = 1";
            execute($sql, [
                $_POST['nombreEmpresa'],
                $_POST['direccion'],
                $_POST['telefono'],
                $_POST['correo'],
                $_POST['impuesto'],
                $_POST['logo'] ?? ''
            ]);
        } else {
            $sql = "INSERT INTO ConfiguracionSistema (nombreEmpresa, direccion, telefono, correo, impuesto, logo) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            execute($sql, [
                $_POST['nombreEmpresa'],
                $_POST['direccion'],
                $_POST['telefono'],
                $_POST['correo'],
                $_POST['impuesto'],
                $_POST['logo'] ?? ''
            ]);
        }
        $mensaje = "Configuración guardada correctamente";
        $tipo_mensaje = "success";
    }
    
    if ($action === 'guardar_impuestos') {
        // Actualizar tabla de impuestos (IR)
        $sql = "UPDATE TablaIR SET 
                limiteInferior = ?, 
                limiteSuperior = ?, 
                impuestoBase = ?, 
                porcentaje = ?, 
                sobreExceso = ?
                WHERE idIR = ?";
        execute($sql, [
            $_POST['limiteInferior'],
            $_POST['limiteSuperior'] ?: null,
            $_POST['impuestoBase'],
            $_POST['porcentaje'],
            $_POST['sobreExceso'],
            $_POST['idIR']
        ]);
        $mensaje = "Tramo de IR actualizado correctamente";
        $tipo_mensaje = "success";
    }
}

// Obtener configuración actual
$config = fetchOne("SELECT * FROM ConfiguracionSistema WHERE idConfiguracion = 1");

// Obtener tabla de IR
$tablaIR = fetchAll("SELECT * FROM TablaIR ORDER BY limiteInferior");

// Valores por defecto si no hay configuración
if (!$config) {
    $config = [
        'nombreEmpresa' => 'Mi Empresa S.A.',
        'direccion' => 'Managua, Nicaragua',
        'telefono' => '2255-0000',
        'correo' => 'info@miempresa.com',
        'impuesto' => 15.00,
        'logo' => ''
    ];
}
?>

<header class="header">
    <div>
        <h2>Configuración del Sistema</h2>
        <p>Administrar parámetros y configuración general</p>
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

    <!-- Tabs de configuración -->
    <div style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap;">
        <button class="btn btn-primary" onclick="mostrarTab('empresa')" id="tabEmpresaBtn">
            <i class="fas fa-building"></i> Empresa
        </button>
        <button class="btn btn-secondary" onclick="mostrarTab('impuestos')" id="tabImpuestosBtn" style="background:#64748b; color:white;">
            <i class="fas fa-calculator"></i> Impuestos (IR)
        </button>
        <button class="btn btn-secondary" onclick="mostrarTab('inss')" id="tabInssBtn" style="background:#64748b; color:white;">
            <i class="fas fa-percent"></i> INSS
        </button>
        <button class="btn btn-secondary" onclick="mostrarTab('sistema')" id="tabSistemaBtn" style="background:#64748b; color:white;">
            <i class="fas fa-cog"></i> Sistema
        </button>
    </div>

    <!-- Pestaña: Empresa -->
    <div id="tabEmpresa" class="card">
        <h3>Datos de la Empresa</h3>
        <form method="POST" action="">
            <input type="hidden" name="action" value="guardar_config">
            
            <div class="form-group">
                <label>Nombre de la Empresa</label>
                <input type="text" name="nombreEmpresa" class="form-control" 
                       value="<?php echo htmlspecialchars($config['nombreEmpresa'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Dirección</label>
                <input type="text" name="direccion" class="form-control" 
                       value="<?php echo htmlspecialchars($config['direccion'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Teléfono</label>
                <input type="text" name="telefono" class="form-control" 
                       value="<?php echo htmlspecialchars($config['telefono'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Correo Electrónico</label>
                <input type="email" name="correo" class="form-control" 
                       value="<?php echo htmlspecialchars($config['correo'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Impuesto (IVA %)</label>
                <input type="number" name="impuesto" class="form-control" step="0.01" 
                       value="<?php echo htmlspecialchars($config['impuesto'] ?? 15); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Logo (URL o ruta)</label>
                <input type="text" name="logo" class="form-control" 
                       value="<?php echo htmlspecialchars($config['logo'] ?? ''); ?>" 
                       placeholder="Ej: assets/logo.png">
            </div>
            
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Guardar Configuración
            </button>
        </form>
    </div>

    <!-- Pestaña: Impuestos IR -->
    <div id="tabImpuestos" style="display:none;" class="card">
        <h3>Tabla de Impuesto sobre la Renta (IR)</h3>
        <p style="color:#64748b; font-size:12px; margin-bottom:15px;">
            Actualiza los tramos y porcentajes del Impuesto sobre la Renta
        </p>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Límite Inferior</th>
                        <th>Límite Superior</th>
                        <th>Impuesto Base</th>
                        <th>Porcentaje %</th>
                        <th>Sobre Exceso</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tablaIR as $t): ?>
                        <tr>
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="guardar_impuestos">
                                <input type="hidden" name="idIR" value="<?php echo $t['idIR']; ?>">
                                
                                <td><?php echo $t['idIR']; ?></td>
                                <td>
                                    <input type="number" name="limiteInferior" class="form-control" 
                                           value="<?php echo $t['limiteInferior']; ?>" step="0.01" style="width:120px;">
                                </td>
                                <td>
                                    <input type="number" name="limiteSuperior" class="form-control" 
                                           value="<?php echo $t['limiteSuperior'] ?? ''; ?>" step="0.01" 
                                           placeholder="NULL" style="width:120px;">
                                </td>
                                <td>
                                    <input type="number" name="impuestoBase" class="form-control" 
                                           value="<?php echo $t['impuestoBase']; ?>" step="0.01" style="width:120px;">
                                </td>
                                <td>
                                    <input type="number" name="porcentaje" class="form-control" 
                                           value="<?php echo $t['porcentaje']; ?>" step="0.01" style="width:80px;">
                                </td>
                                <td>
                                    <input type="number" name="sobreExceso" class="form-control" 
                                           value="<?php echo $t['sobreExceso']; ?>" step="0.01" style="width:120px;">
                                </td>
                                <td>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </td>
                            </form>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top:15px; padding:15px; background:#f8fafc; border-radius:6px;">
            <h4>Ejemplo de cálculo</h4>
            <p style="font-size:13px; color:#64748b;">
                <strong>Fórmula:</strong> IR = Impuesto Base + ((Renta Neta Anual - Sobre Exceso) * (Porcentaje / 100))
            </p>
            <p style="font-size:13px; color:#64748b;">
                <strong>Nota:</strong> El límite superior NULL significa que no hay límite máximo.
            </p>
        </div>
    </div>

    <!-- Pestaña: INSS -->
    <div id="tabInss" style="display:none;" class="card">
        <h3>Configuración del INSS</h3>
        <p style="color:#64748b; font-size:12px; margin-bottom:15px;">
            Configuración del Instituto Nicaragüense de Seguridad Social
        </p>
        
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
            <div style="background:#f8fafc; padding:20px; border-radius:8px;">
                <h4>INSS Laboral (Empleado)</h4>
                <div class="form-group">
                    <label>Porcentaje de INSS</label>
                    <input type="number" class="form-control" value="7.00" step="0.01" readonly>
                    <small style="color:#94a3b8;">El empleado paga el 7% de su salario bruto</small>
                </div>
                <div class="form-group">
                    <label>Techo Salarial</label>
                    <input type="number" class="form-control" value="100,000.00" step="0.01" readonly>
                    <small style="color:#94a3b8;">Monto máximo sobre el cual se calcula el INSS</small>
                </div>
            </div>
            
            <div style="background:#f8fafc; padding:20px; border-radius:8px;">
                <h4>INSS Patronal (Empresa)</h4>
                <div class="form-group">
                    <label>Porcentaje Patronal</label>
                    <input type="number" class="form-control" value="22.50" step="0.01" readonly>
                    <small style="color:#94a3b8;">La empresa paga el 22.5% adicional</small>
                </div>
                <div class="form-group">
                    <label>Techo Salarial Patronal</label>
                    <input type="number" class="form-control" value="100,000.00" step="0.01" readonly>
                    <small style="color:#94a3b8;">Monto máximo sobre el cual se calcula el INSS patronal</small>
                </div>
            </div>
        </div>
        
        <div style="margin-top:20px; padding:15px; background:#fef3c7; border-radius:6px; border-left:4px solid #f59e0b;">
            <p style="font-size:13px; color:#92400e;">
                <i class="fas fa-info-circle"></i>
                <strong>Nota:</strong> Estos valores son fijos según la legislación nicaragüense. 
                Para modificarlos, contacte al administrador del sistema.
            </p>
        </div>
    </div>

    <!-- Pestaña: Sistema -->
    <div id="tabSistema" style="display:none;" class="card">
        <h3>Información del Sistema</h3>
        
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
            <div style="background:#f8fafc; padding:15px; border-radius:8px;">
                <h4>Versión</h4>
                <p><strong>SIS_Controlix</strong> v1.0.0</p>
                <p style="font-size:12px; color:#94a3b8;">Última actualización: <?php echo date('d/m/Y'); ?></p>
            </div>
            
            <div style="background:#f8fafc; padding:15px; border-radius:8px;">
                <h4>Entorno</h4>
                <p><strong>PHP:</strong> <?php echo phpversion(); ?></p>
                <p><strong>Servidor:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido'; ?></p>
            </div>
            
            <div style="background:#f8fafc; padding:15px; border-radius:8px;">
                <h4>Base de Datos</h4>
                <p><strong>Motor:</strong> MySQL</p>
                <p><strong>Base:</strong> <?php echo DB_NAME; ?></p>
            </div>
            
            <div style="background:#f8fafc; padding:15px; border-radius:8px;">
                <h4>Estadísticas</h4>
                <?php
                $totalRegistros = fetchOne("
                    SELECT 
                        (SELECT COUNT(*) FROM Empleados) as empleados,
                        (SELECT COUNT(*) FROM Productos) as productos,
                        (SELECT COUNT(*) FROM Clientes) as clientes,
                        (SELECT COUNT(*) FROM Ventas) as ventas
                ");
                ?>
                <p><strong>Empleados:</strong> <?php echo $totalRegistros['empleados'] ?? 0; ?></p>
                <p><strong>Productos:</strong> <?php echo $totalRegistros['productos'] ?? 0; ?></p>
                <p><strong>Clientes:</strong> <?php echo $totalRegistros['clientes'] ?? 0; ?></p>
                <p><strong>Ventas:</strong> <?php echo $totalRegistros['ventas'] ?? 0; ?></p>
            </div>
        </div>
        
        <div style="margin-top:20px; padding:15px; background:#dbeafe; border-radius:6px; border-left:4px solid #2563eb;">
            <p style="font-size:13px; color:#1e40af;">
                <i class="fas fa-info-circle"></i>
                <strong>Moneda:</strong> Córdobas Nicaragüenses (C$)
            </p>
        </div>
        
        <div style="margin-top:15px; display:flex; gap:10px;">
            <button class="btn btn-danger" onclick="if(confirm('¿Estás seguro de limpiar la caché del sistema?')){alert('Caché limpiada');}">
                <i class="fas fa-broom"></i> Limpiar Caché
            </button>
            <button class="btn btn-warning" onclick="if(confirm('¿Estás seguro de generar un respaldo de la base de datos?')){alert('Respaldo generado');}">
                <i class="fas fa-database"></i> Generar Respaldo
            </button>
        </div>
    </div>
</section>

<script>
function mostrarTab(tab) {
    // Ocultar todas las pestañas
    document.getElementById('tabEmpresa').style.display = 'none';
    document.getElementById('tabImpuestos').style.display = 'none';
    document.getElementById('tabInss').style.display = 'none';
    document.getElementById('tabSistema').style.display = 'none';
    
    // Resetear estilos de botones
    document.querySelectorAll('[id^="tab"]Btn').forEach(btn => {
        btn.style.background = '#64748b';
        btn.style.color = 'white';
    });
    
    // Mostrar la pestaña seleccionada
    document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1)).style.display = 'block';
    
    // Resaltar botón
    const btn = document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1) + 'Btn');
    if (btn) {
        btn.style.background = '#2563eb';
        btn.style.color = 'white';
    }
}

// Mostrar pestaña de empresa por defecto
document.addEventListener('DOMContentLoaded', function() {
    mostrarTab('empresa');
});
</script>

<style>
.btn-secondary {
    background: #64748b;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s;
}

.btn-secondary:hover {
    background: #475569;
}

.btn-secondary.active {
    background: #2563eb;
}

.btn-warning {
    background: #f59e0b;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s;
}

.btn-warning:hover {
    background: #d97706;
}
</style>

<?php require_once '../includes/footer.php'; ?>