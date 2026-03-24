<?php
require_once("includes/auth.php");
require_once("includes/conexion.php");

$usuario_id = $_SESSION['usuario_id'];
$mensaje = '';
$error = '';

// Obtener datos del usuario
$sqlUsuario = "SELECT nombre, email, foto_perfil FROM usuarios WHERE id = ?";
$stmtUsuario = $conn->prepare($sqlUsuario);
$stmtUsuario->bind_param("i", $usuario_id);
$stmtUsuario->execute();
$usuario = $stmtUsuario->get_result()->fetch_assoc();

// Obtener dispositivo activo
$sqlDispositivo = "SELECT nombre_dispositivo FROM dispositivos WHERE usuario_id = ? AND activo = 1 LIMIT 1";
$stmtDispositivo = $conn->prepare($sqlDispositivo);
$stmtDispositivo->bind_param("i", $usuario_id);
$stmtDispositivo->execute();
$dispositivo = $stmtDispositivo->get_result()->fetch_assoc() ?: ['nombre_dispositivo' => 'No disponible'];

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_actual = $_POST['password_actual'];
    $password_nueva = $_POST['password_nueva'];
    $password_confirmar = $_POST['password_confirmar'];

    if (empty($password_actual) || empty($password_nueva) || empty($password_confirmar)) {
        $error = "Todos los campos son obligatorios.";
    } elseif ($password_nueva !== $password_confirmar) {
        $error = "Las contraseñas nuevas no coinciden.";
    } elseif (strlen($password_nueva) < 6) {
        $error = "La nueva contraseña debe tener al menos 6 caracteres.";
    } else {
        // Verificar contraseña actual
        $stmt = $conn->prepare("SELECT password FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (password_verify($password_actual, $user['password'])) {
            $nuevo_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $updateStmt->bind_param("si", $nuevo_hash, $usuario_id);
            if ($updateStmt->execute()) {
                $mensaje = "✅ Contraseña actualizada correctamente.";
                
                // Registrar en alertas
                $sqlAlerta = "INSERT INTO alertas (dispositivo_id, tipo, mensaje, nivel) 
                              SELECT id, 'seguridad', 'Cambio de contraseña realizado', 'info' 
                              FROM dispositivos WHERE usuario_id = ? LIMIT 1";
                $stmtAlerta = $conn->prepare($sqlAlerta);
                $stmtAlerta->bind_param("i", $usuario_id);
                $stmtAlerta->execute();
            } else {
                $error = "Error al actualizar la contraseña.";
            }
            $updateStmt->close();
        } else {
            $error = "La contraseña actual es incorrecta.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Seguridad - HydroHawk</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/co.css">
</head>
<body class="dashboard-body">
    <!-- Navbar (igual que en perfil.php) -->
    <nav class="navbar-premium"><!-- ... copiar de perfil.php ... --></nav>
    
    <!-- Sidebar (igual que en perfil.php) -->
    <aside class="sidebar-premium" id="sidebar"><!-- ... copiar de perfil.php ... --></aside>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <div class="hero-card">
            <div class="hero-content">
                <h1>Seguridad de la Cuenta</h1>
                <p><i class="fas fa-shield-alt"></i> Gestiona tu contraseña y métodos de acceso</p>
            </div>
            <div class="hero-badge">
                <span class="badge-premium"><i class="fas fa-lock"></i> Protegido</span>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="msg-ok"><i class="fas fa-check-circle"></i> <?php echo $mensaje; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="msg-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
            <!-- Cambiar contraseña -->
            <div class="form-card glass-effect">
                <h3><i class="fas fa-key"></i> Cambiar Contraseña</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Contraseña actual</label>
                        <input type="password" name="password_actual" required class="input-premium">
                    </div>
                    <div class="form-group">
                        <label>Nueva contraseña</label>
                        <input type="password" name="password_nueva" required class="input-premium" minlength="6">
                        <small>Mínimo 6 caracteres</small>
                    </div>
                    <div class="form-group">
                        <label>Confirmar nueva contraseña</label>
                        <input type="password" name="password_confirmar" required class="input-premium">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-premium primary">
                            <i class="fas fa-save"></i> Actualizar Contraseña
                        </button>
                    </div>
                </form>
            </div>

            <!-- Autenticación de dos factores -->
            <div class="form-card glass-effect">
                <h3><i class="fas fa-mobile-alt"></i> Autenticación de dos factores</h3>
                <p style="color: var(--gray); margin-bottom: 1.5rem;">
                    Aumenta la seguridad de tu cuenta requiriendo un código adicional al iniciar sesión.
                </p>
                
                <div class="toggle-switch">
                    <label class="switch">
                        <input type="checkbox" id="toggle2fa" onchange="toggle2FA(this)">
                        <span class="slider"></span>
                    </label>
                    <div class="toggle-label">
                        <strong>Activar 2FA</strong>
                        <small>Recomendado para mayor seguridad</small>
                    </div>
                </div>
                
                <div id="2faConfig" style="display: none; margin-top: 1.5rem;">
                    <p>Escanea el código QR con Google Authenticator:</p>
                    <div style="background: #f0f0f0; height: 150px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 1rem 0;">
                        <i class="fas fa-qrcode" style="font-size: 80px; color: var(--primary);"></i>
                    </div>
                    <p>Código de respaldo: <code>HYDRO-<?php echo strtoupper(substr(md5($usuario_id), 0, 8)); ?></code></p>
                </div>
            </div>

            <!-- Sesiones activas -->
            <div class="form-card glass-effect" style="grid-column: span 2;">
                <h3><i class="fas fa-desktop"></i> Sesiones activas</h3>
                <div class="table-responsive">
                    <table class="premium-table">
                        <thead>
                            <tr>
                                <th>Dispositivo</th>
                                <th>IP</th>
                                <th>Última actividad</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><i class="fas fa-chrome"></i> Chrome en Windows</td>
                                <td><?php echo $_SERVER['REMOTE_ADDR']; ?></td>
                                <td>Ahora mismo</td>
                                <td><span class="badge-auto">Esta sesión</span></td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-safari"></i> Safari en iPhone</td>
                                <td>192.168.1.105</td>
                                <td>Hace 2 horas</td>
                                <td><button class="btn-icon small" onclick="cerrarSesion('safari')"><i class="fas fa-times"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Toggle sidebar (igual que antes)
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

        // Toggle 2FA
        function toggle2FA(checkbox) {
            const configDiv = document.getElementById('2faConfig');
            if (checkbox.checked) {
                configDiv.style.display = 'block';
                alert('📱 Se ha enviado un código de verificación a tu correo para activar 2FA.');
            } else {
                configDiv.style.display = 'none';
            }
        }

        // Cerrar sesión remota
        function cerrarSesion(dispositivo) {
            alert('Sesión cerrada en ' + dispositivo);
        }
    </script>
</body>
</html>