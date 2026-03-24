<?php
require_once("includes/auth.php");
require_once("includes/conexion.php");

$usuario_id = $_SESSION['usuario_id'];
$mensaje = '';
$error = '';

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'actualizar_perfil') {
        $nombre = trim($_POST['nombre']);
        $email = trim($_POST['email']);
        $telefono = trim($_POST['telefono']);

        if (empty($nombre) || empty($email)) {
            $error = "El nombre y el correo son obligatorios.";
        } else {
            // Verificar si el email ya existe
            $checkStmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $checkStmt->bind_param("si", $email, $usuario_id);
            $checkStmt->execute();
            $checkStmt->store_result();
            
            if ($checkStmt->num_rows > 0) {
                $error = "El correo electrónico ya está en uso.";
            } else {
                $updateStmt = $conn->prepare("UPDATE usuarios SET nombre = ?, email = ?, telefono = ? WHERE id = ?");
                $updateStmt->bind_param("sssi", $nombre, $email, $telefono, $usuario_id);
                if ($updateStmt->execute()) {
                    $_SESSION['nombre'] = $nombre;
                    $_SESSION['email'] = $email;
                    $mensaje = "Perfil actualizado correctamente.";
                } else {
                    $error = "Error al actualizar el perfil.";
                }
                $updateStmt->close();
            }
            $checkStmt->close();
        }
    }

    // Subir foto
    if ($_POST['accion'] === 'subir_foto' && isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/perfiles/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $extension = strtolower(pathinfo($_FILES["foto_perfil"]["name"], PATHINFO_EXTENSION));
        $filename = "user_" . $usuario_id . "_" . time() . "." . $extension;
        $target_file = $target_dir . $filename;
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($extension, $allowed_types)) {
            if (move_uploaded_file($_FILES["foto_perfil"]["tmp_name"], $target_file)) {
                // Eliminar foto anterior si existe
                $oldStmt = $conn->prepare("SELECT foto_perfil FROM usuarios WHERE id = ?");
                $oldStmt->bind_param("i", $usuario_id);
                $oldStmt->execute();
                $oldResult = $oldStmt->get_result()->fetch_assoc();
                if ($oldResult && !empty($oldResult['foto_perfil']) && file_exists($oldResult['foto_perfil'])) {
                    unlink($oldResult['foto_perfil']);
                }
                
                $updateStmt = $conn->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?");
                $updateStmt->bind_param("si", $target_file, $usuario_id);
                if ($updateStmt->execute()) {
                    $mensaje = "Foto de perfil actualizada.";
                } else {
                    $error = "Error al guardar la foto.";
                }
                $updateStmt->close();
            } else {
                $error = "Error al subir el archivo.";
            }
        } else {
            $error = "Tipo de archivo no permitido. Solo JPG, PNG, GIF.";
        }
    }

    // Eliminar foto
    if ($_POST['accion'] === 'eliminar_foto') {
        $oldStmt = $conn->prepare("SELECT foto_perfil FROM usuarios WHERE id = ?");
        $oldStmt->bind_param("i", $usuario_id);
        $oldStmt->execute();
        $oldResult = $oldStmt->get_result()->fetch_assoc();
        if ($oldResult && !empty($oldResult['foto_perfil']) && file_exists($oldResult['foto_perfil'])) {
            unlink($oldResult['foto_perfil']);
        }
        
        $updateStmt = $conn->prepare("UPDATE usuarios SET foto_perfil = NULL WHERE id = ?");
        $updateStmt->bind_param("i", $usuario_id);
        if ($updateStmt->execute()) {
            $mensaje = "Foto de perfil eliminada.";
        } else {
            $error = "Error al eliminar la foto.";
        }
        $updateStmt->close();
    }
}

// Obtener datos actualizados
$sqlUsuario = "SELECT nombre, email, telefono, foto_perfil, email_verificado, telefono_verificado FROM usuarios WHERE id = ?";
$stmtUsuario = $conn->prepare($sqlUsuario);
$stmtUsuario->bind_param("i", $usuario_id);
$stmtUsuario->execute();
$usuario = $stmtUsuario->get_result()->fetch_assoc();

// Obtener dispositivo activo
$sqlDispositivo = "SELECT id, nombre_dispositivo FROM dispositivos WHERE usuario_id = ? AND activo = 1 LIMIT 1";
$stmtDispositivo = $conn->prepare($sqlDispositivo);
$stmtDispositivo->bind_param("i", $usuario_id);
$stmtDispositivo->execute();
$dispositivo = $stmtDispositivo->get_result()->fetch_assoc() ?: ['nombre_dispositivo' => 'No disponible'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Perfil - HydroHawk</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/co.css">
</head>
<body class="dashboard-body">
    <!-- Navbar Superior -->
    <nav class="navbar-premium">
        <div class="nav-left">
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>
            <img src="assets/img/hydro_logo.png" alt="HydroHawk" class="nav-logo">
            <span class="nav-brand">HydroHawk</span>
        </div>
        <div class="nav-right">
            <div class="nav-notifications">
                <i class="fas fa-bell"></i>
                <span class="notification-badge">0</span>
            </div>
            <div class="nav-user" id="userMenuTrigger">
                <div class="user-avatar">
                    <?php if (!empty($usuario['foto_perfil'])): ?>
                        <img src="<?php echo htmlspecialchars($usuario['foto_perfil']); ?>" alt="Perfil">
                    <?php else: ?>
                        <div class="avatar-placeholder">
                            <?php echo strtoupper(substr($usuario['nombre'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <span class="user-name"><?php echo htmlspecialchars($usuario['nombre']); ?></span>
                    <span class="user-role">Administrador</span>
                </div>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="user-dropdown" id="userDropdown">
                <div class="dropdown-header">
                    <div class="dropdown-user">
                        <strong><?php echo htmlspecialchars($usuario['nombre']); ?></strong>
                        <span><?php echo htmlspecialchars($usuario['email']); ?></span>
                    </div>
                </div>
                <div class="dropdown-divider"></div>
                <a href="perfil.php" class="dropdown-item">
                    <i class="fas fa-user"></i> Mi Perfil
                </a>
                <a href="configuracion.php" class="dropdown-item">
                    <i class="fas fa-cog"></i> Configuración
                </a>
                <a href="seguridad.php" class="dropdown-item">
                    <i class="fas fa-shield-alt"></i> Seguridad
                </a>
                <div class="dropdown-divider"></div>
                <a href="logout.php" class="dropdown-item logout">
                    <i class="fas fa-sign-out-alt"></i> Cerrar sesión
                </a>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside class="sidebar-premium" id="sidebar">
        <div class="sidebar-header">
            <div class="device-info">
                <i class="fas fa-microchip"></i>
                <div class="device-details">
                    <span>Dispositivo activo</span>
                    <strong><?php echo htmlspecialchars($dispositivo['nombre_dispositivo']); ?></strong>
                </div>
            </div>
        </div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item"><i class="fas fa-chart-pie"></i><span>Dashboard</span></a>
            <a href="configuracion.php" class="menu-item"><i class="fas fa-sliders-h"></i><span>Configuración</span></a>
            <a href="historial.php" class="menu-item"><i class="fas fa-history"></i><span>Historial</span></a>
            <a href="reportes.php" class="menu-item"><i class="fas fa-file-alt"></i><span>Reportes</span></a>
            <a href="analisis.php" class="menu-item"><i class="fas fa-brain"></i><span>Análisis IA</span></a>
            <a href="soporte.php" class="menu-item"><i class="fas fa-headset"></i><span>Soporte</span></a>
        </nav>
        <div class="sidebar-footer">
            <div class="system-status">
                <i class="fas fa-circle"></i>
                <span>Sistema en línea</span>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <div class="hero-card">
            <div class="hero-content">
                <h1>Mi Perfil</h1>
                <p><i class="fas fa-user-circle"></i> Administra tu información personal</p>
            </div>
            <div class="hero-badge">
                <span class="badge-premium"><i class="fas fa-shield-alt"></i> Cuenta Premium</span>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="msg-ok"><i class="fas fa-check-circle"></i> <?php echo $mensaje; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="msg-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="form-grid" style="grid-template-columns: 1fr 2fr;">
            <!-- Columna de Foto -->
            <div class="form-card glass-effect" style="text-align: center;">
                <h3><i class="fas fa-camera"></i> Foto de Perfil</h3>
                <div style="display: flex; flex-direction: column; align-items: center; gap: 1rem;">
                    <div class="user-avatar" style="width: 150px; height: 150px; border-radius: 50%; overflow: hidden;">
                        <?php if (!empty($usuario['foto_perfil'])): ?>
                            <img src="<?php echo htmlspecialchars($usuario['foto_perfil']); ?>" alt="Perfil" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div class="avatar-placeholder" style="font-size: 4rem; line-height: 150px; background: linear-gradient(135deg, var(--primary-dark), var(--primary));">
                                <?php echo strtoupper(substr($usuario['nombre'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" style="width: 100%;">
                        <input type="hidden" name="accion" value="subir_foto">
                        <input type="file" name="foto_perfil" accept="image/*" class="input-premium" style="margin-bottom: 0.5rem;">
                        <button type="submit" class="btn-premium primary" style="width: 100%;">
                            <i class="fas fa-upload"></i> Subir Nueva Foto
                        </button>
                    </form>
                    
                    <?php if (!empty($usuario['foto_perfil'])): ?>
                    <form method="POST">
                        <input type="hidden" name="accion" value="eliminar_foto">
                        <button type="submit" class="btn-premium secondary" style="width: 100%;">
                            <i class="fas fa-trash-alt"></i> Eliminar Foto
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <small style="color: var(--gray);">Formatos: JPG, PNG, GIF. Máx 2MB</small>
                </div>
            </div>

            <!-- Columna de Datos Personales -->
            <div class="form-card glass-effect">
                <h3><i class="fas fa-id-card"></i> Datos Personales</h3>
                <form method="POST">
                    <input type="hidden" name="accion" value="actualizar_perfil">
                    
                    <div class="form-group">
                        <label>Nombre completo *</label>
                        <input type="text" name="nombre" value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required class="input-premium">
                    </div>
                    
                    <div class="form-group">
                        <label>Correo electrónico *</label>
                        <div class="input-group">
                            <input type="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required class="input-premium">
                            <span class="input-suffix">
                                <?php if ($usuario['email_verificado']): ?>
                                    <i class="fas fa-check-circle" style="color: var(--success);" title="Verificado"></i>
                                <?php else: ?>
                                    <i class="fas fa-times-circle" style="color: var(--danger);" title="No verificado"></i>
                                <?php endif; ?>
                            </span>
                        </div>
                        <small><a href="#" onclick="enviarVerificacion('email'); return false;">Enviar verificación</a></small>
                    </div>
                    
                    <div class="form-group">
                        <label>Teléfono</label>
                        <div class="input-group">
                            <input type="tel" name="telefono" value="<?php echo htmlspecialchars($usuario['telefono']); ?>" class="input-premium">
                            <span class="input-suffix">
                                <?php if ($usuario['telefono_verificado']): ?>
                                    <i class="fas fa-check-circle" style="color: var(--success);" title="Verificado"></i>
                                <?php else: ?>
                                    <i class="fas fa-times-circle" style="color: var(--danger);" title="No verificado"></i>
                                <?php endif; ?>
                            </span>
                        </div>
                        <small><a href="#" onclick="enviarVerificacion('telefono'); return false;">Enviar verificación</a></small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-premium primary">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Historial de actividad -->
        <div class="form-card glass-effect" style="margin-top: 2rem;">
            <h3><i class="fas fa-history"></i> Actividad reciente</h3>
            <div class="table-responsive">
                <table class="premium-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Acción</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo date('d/m/Y H:i'); ?></td>
                            <td>Actualización de perfil</td>
                            <td><?php echo $_SERVER['REMOTE_ADDR']; ?></td>
                        </tr>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime('-1 day')); ?></td>
                            <td>Inicio de sesión</td>
                            <td><?php echo $_SERVER['REMOTE_ADDR']; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // Toggle sidebar
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('mainContent').classList.toggle('expanded');
        });

        // User dropdown
        const userMenuTrigger = document.getElementById('userMenuTrigger');
        const userDropdown = document.getElementById('userDropdown');

        userMenuTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
        });

        document.addEventListener('click', function() {
            userDropdown.classList.remove('show');
        });

        userDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });

        // Simular envío de verificación
        function enviarVerificacion(tipo) {
            alert('✅ Se ha enviado un código de verificación a tu ' + (tipo === 'email' ? 'correo electrónico' : 'teléfono'));
        }
    </script>
</body>
</html>