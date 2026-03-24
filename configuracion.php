<?php
require_once("includes/auth.php");
require_once("includes/conexion.php");

$usuario_id = $_SESSION['usuario_id'];

$sqlDispositivo = "SELECT id, nombre_dispositivo
                   FROM dispositivos
                   WHERE usuario_id = ? AND activo = 1
                   LIMIT 1";

$stmtDispositivo = $conn->prepare($sqlDispositivo);
$stmtDispositivo->bind_param("i", $usuario_id);
$stmtDispositivo->execute();
$dispositivo = $stmtDispositivo->get_result()->fetch_assoc();

if (!$dispositivo) {
    die("No se encontró un dispositivo activo.");
}

$dispositivo_id = $dispositivo["id"];

$sqlConfig = "SELECT * FROM configuracion_riego WHERE dispositivo_id = ? LIMIT 1";
$stmtConfig = $conn->prepare($sqlConfig);
$stmtConfig->bind_param("i", $dispositivo_id);
$stmtConfig->execute();
$config = $stmtConfig->get_result()->fetch_assoc();

if (!$config) {
    $sqlInsert = "INSERT INTO configuracion_riego
                  (dispositivo_id, humedad_minima, humedad_maxima, hora_inicio, hora_fin, duracion_riego, notificaciones)
                  VALUES (?, 30, 70, '06:00:00', '09:00:00', 10, 1)";
    $stmtInsert = $conn->prepare($sqlInsert);
    $stmtInsert->bind_param("i", $dispositivo_id);
    $stmtInsert->execute();

    $stmtConfig->execute();
    $config = $stmtConfig->get_result()->fetch_assoc();
}

// Obtener datos del usuario para el navbar
$sqlUsuario = "SELECT nombre, email, foto_perfil FROM usuarios WHERE id = ?";
$stmtUsuario = $conn->prepare($sqlUsuario);
$stmtUsuario->bind_param("i", $usuario_id);
$stmtUsuario->execute();
$usuario = $stmtUsuario->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración Premium - HydroHawk</title>
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
                <span class="notification-badge">3</span>
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

            <!-- Dropdown Menu -->
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
                    <i class="fas fa-cog"></i> Ajustes de cuenta
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

    <!-- Sidebar Colapsable -->
    <aside class="sidebar-premium collapsed" id="sidebar">
        <div class="sidebar-header">
            <div class="device-info">
                <i class="fas fa-microchip"></i>
                <div class="device-details">
                    <span>Dispositivo activo</span>
                    <strong><?php echo htmlspecialchars($dispositivo["nombre_dispositivo"]); ?></strong>
                </div>
            </div>
        </div>

        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item">
                <i class="fas fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>
            <a href="configuracion.php" class="menu-item active">
                <i class="fas fa-sliders-h"></i>
                <span>Configuración</span>
            </a>
            <a href="historial.php" class="menu-item">
                <i class="fas fa-history"></i>
                <span>Historial</span>
            </a>
            <a href="reportes.php" class="menu-item">
                <i class="fas fa-file-alt"></i>
                <span>Reportes</span>
            </a>
            <a href="soporte.php" class="menu-item">
                <i class="fas fa-headset"></i>
                <span>Soporte</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="system-status">
                <i class="fas fa-circle" style="color: #10b981;"></i>
                <span>Sistema en línea</span>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Hero Section -->
        <div class="hero-card">
            <div class="hero-content">
                <h1>Configuración de riego</h1>
                <p><i class="fas fa-map-pin"></i> <?php echo htmlspecialchars($dispositivo["nombre_dispositivo"]); ?></p>
            </div>
            <div class="hero-badge">
                <span class="badge-premium">
                    <i class="fas fa-shield-alt"></i> Configuración Premium
                </span>
            </div>
        </div>

        <!-- Config Form -->
        <div class="config-container">
            <div class="config-tabs">
                <button class="tab-btn active" data-tab="basico">
                    <i class="fas fa-sliders-h"></i> Básico
                </button>
                <button class="tab-btn" data-tab="avanzado">
                    <i class="fas fa-microchip"></i> Avanzado
                </button>
                <button class="tab-btn" data-tab="notificaciones">
                    <i class="fas fa-bell"></i> Notificaciones
                </button>
            </div>

            <div id="mensajeConfig" class="message-box"></div>

            <form id="formConfig" class="config-form-premium">
                <input type="hidden" name="dispositivo_id" value="<?php echo (int)$dispositivo_id; ?>">

                <!-- Pestaña Básico -->
                <div class="tab-pane active" id="tab-basico">
                    <div class="form-grid">
                        <div class="form-card glass-effect">
                            <h3><i class="fas fa-tint"></i> Límites de humedad</h3>
                            <div class="form-group">
                                <label>Humedad mínima (%)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" name="humedad_minima" value="<?php echo htmlspecialchars($config["humedad_minima"]); ?>" required>
                                    <span class="input-suffix">%</span>
                                </div>
                                <small>El riego se activará por debajo de este valor</small>
                            </div>

                            <div class="form-group">
                                <label>Humedad máxima (%)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" name="humedad_maxima" value="<?php echo htmlspecialchars($config["humedad_maxima"]); ?>" required>
                                    <span class="input-suffix">%</span>
                                </div>
                                <small>El riego se detendrá al alcanzar este valor</small>
                            </div>
                        </div>

                        <div class="form-card glass-effect">
                            <h3><i class="fas fa-clock"></i> Horario de riego</h3>
                            <div class="form-group">
                                <label>Hora de inicio</label>
                                <div class="input-group">
                                    <i class="fas fa-sun input-icon"></i>
                                    <input type="time" name="hora_inicio" value="<?php echo htmlspecialchars($config["hora_inicio"]); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Hora de fin</label>
                                <div class="input-group">
                                    <i class="fas fa-moon input-icon"></i>
                                    <input type="time" name="hora_fin" value="<?php echo htmlspecialchars($config["hora_fin"]); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Duración de riego</label>
                                <div class="input-group">
                                    <input type="number" name="duracion_riego" value="<?php echo htmlspecialchars($config["duracion_riego"]); ?>" required>
                                    <span class="input-suffix">minutos</span>
                                </div>
                                <small>Tiempo máximo por ciclo de riego</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pestaña Avanzado -->
                <div class="tab-pane" id="tab-avanzado">
                    <div class="form-grid">
                        <div class="form-card glass-effect">
                            <h3><i class="fas fa-robot"></i> Control inteligente</h3>
                            
                            <div class="toggle-switch">
                                <label class="switch">
                                    <input type="checkbox" name="control_inteligente" <?php echo ($config['control_inteligente'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                                <div class="toggle-label">
                                    <strong>Control predictivo</strong>
                                    <small>Ajusta automáticamente según el clima</small>
                                </div>
                            </div>

                            <div class="toggle-switch">
                                <label class="switch">
                                    <input type="checkbox" name="ahorro_agua" <?php echo ($config['ahorro_agua'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                                <div class="toggle-label">
                                    <strong>Modo ahorro de agua</strong>
                                    <small>Optimiza el consumo de agua</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-card glass-effect">
                            <h3><i class="fas fa-chart-line"></i> Sensibilidad</h3>
                            
                            <div class="form-group">
                                <label>Frecuencia de medición</label>
                                <select name="frecuencia_medicion" class="select-premium">
                                    <option value="5">Cada 5 minutos</option>
                                    <option value="10" selected>Cada 10 minutos</option>
                                    <option value="15">Cada 15 minutos</option>
                                    <option value="30">Cada 30 minutos</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Margen de histéresis</label>
                                <div class="input-group">
                                    <input type="number" name="historesis" value="2" step="0.1" min="0.5" max="5">
                                    <span class="input-suffix">%</span>
                                </div>
                                <small>Evita cambios bruscos en la activación</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pestaña Notificaciones -->
                <div class="tab-pane" id="tab-notificaciones">
                    <div class="form-grid">
                        <div class="form-card glass-effect">
                            <h3><i class="fas fa-bell"></i> Alertas</h3>
                            
                            <div class="toggle-switch">
                                <label class="switch">
                                    <input type="checkbox" name="notificaciones" value="1" <?php echo ((int)$config["notificaciones"] === 1) ? "checked" : ""; ?>>
                                    <span class="slider"></span>
                                </label>
                                <div class="toggle-label">
                                    <strong>Notificaciones push</strong>
                                    <small>Recibe alertas en tiempo real</small>
                                </div>
                            </div>

                            <div class="toggle-switch">
                                <label class="switch">
                                    <input type="checkbox" name="email_alerts" checked>
                                    <span class="slider"></span>
                                </label>
                                <div class="toggle-label">
                                    <strong>Alertas por email</strong>
                                    <small>Recibe resúmenes diarios</small>
                                </div>
                            </div>

                            <div class="toggle-switch">
                                <label class="switch">
                                    <input type="checkbox" name="sms_alerts">
                                    <span class="slider"></span>
                                </label>
                                <div class="toggle-label">
                                    <strong>Alertas SMS</strong>
                                    <small>Solo para emergencias</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-card glass-effect">
                            <h3><i class="fas fa-envelope"></i> Preferencias</h3>
                            
                            <div class="form-group">
                                <label>Email de notificación</label>
                                <input type="email" name="email_notificacion" value="<?php echo htmlspecialchars($usuario['email']); ?>" class="input-premium">
                            </div>

                            <div class="form-group">
                                <label>Teléfono para SMS</label>
                                <input type="tel" name="telefono_notificacion" placeholder="+52 555 555 5555" class="input-premium">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="reset" class="btn-premium secondary">
                        <i class="fas fa-undo"></i> Restablecer
                    </button>
                    <button type="submit" class="btn-premium primary">
                        <i class="fas fa-save"></i> Guardar configuración
                    </button>
                </div>
            </form>
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

        // Tabs functionality
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
                
                this.classList.add('active');
                document.getElementById('tab-' + this.dataset.tab).classList.add('active');
            });
        });

        // Form submission
        document.getElementById("formConfig").addEventListener("submit", async function(e){
            e.preventDefault();

            const formData = new FormData(this);

            const res = await fetch("api/guardar_configuracion.php", {
                method: "POST",
                body: formData
            });

            const data = await res.json();
            const box = document.getElementById("mensajeConfig");

            if (data.ok) {
                box.innerHTML = '<div class="msg-ok"><i class="fas fa-check-circle"></i> ' + data.mensaje + '</div>';
            } else {
                box.innerHTML = '<div class="msg-error"><i class="fas fa-exclamation-circle"></i> ' + data.mensaje + '</div>';
            }

            setTimeout(() => box.innerHTML = '', 5000);
        });
    </script>
</body>
</html>