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

// Obtener datos del usuario
$sqlUsuario = "SELECT nombre, email, foto_perfil FROM usuarios WHERE id = ?";
$stmtUsuario = $conn->prepare($sqlUsuario);
$stmtUsuario->bind_param("i", $usuario_id);
$stmtUsuario->execute();
$usuario = $stmtUsuario->get_result()->fetch_assoc();

// Filtros
$fecha_desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
$fecha_hasta = $_GET['hasta'] ?? date('Y-m-d');
$tipo = $_GET['tipo'] ?? 'todos';

// Consulta de riegos con filtros
$sqlRiegos = "SELECT fecha_inicio, fecha_fin, duracion_minutos, cantidad_agua, activado_por, observacion
              FROM historial_riego
              WHERE dispositivo_id = ? 
              AND DATE(fecha_inicio) BETWEEN ? AND ?";

if ($tipo !== 'todos') {
    $sqlRiegos .= " AND activado_por = ?";
}

$sqlRiegos .= " ORDER BY id DESC LIMIT 50";

$stmtRiegos = $conn->prepare($sqlRiegos);

if ($tipo !== 'todos') {
    $stmtRiegos->bind_param("isss", $dispositivo_id, $fecha_desde, $fecha_hasta, $tipo);
} else {
    $stmtRiegos->bind_param("iss", $dispositivo_id, $fecha_desde, $fecha_hasta);
}

$stmtRiegos->execute();
$riegos = $stmtRiegos->get_result();

// Estadísticas
$sqlStats = "SELECT 
                COUNT(*) as total_riegos,
                SUM(duracion_minutos) as minutos_totales,
                SUM(cantidad_agua) as agua_total,
                AVG(duracion_minutos) as duracion_promedio
             FROM historial_riego
             WHERE dispositivo_id = ? 
             AND DATE(fecha_inicio) BETWEEN ? AND ?";
$stmtStats = $conn->prepare($sqlStats);
$stmtStats->bind_param("iss", $dispositivo_id, $fecha_desde, $fecha_hasta);
$stmtStats->execute();
$stats = $stmtStats->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial Premium - HydroHawk</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/h.css">
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
            <a href="configuracion.php" class="menu-item">
                <i class="fas fa-sliders-h"></i>
                <span>Configuración</span>
            </a>
            <a href="historial.php" class="menu-item active">
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
                <h1>Historial del sistema</h1>
                <p><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($dispositivo["nombre_dispositivo"]); ?></p>
            </div>
            <div class="hero-badge">
                <span class="badge-premium">
                    <i class="fas fa-chart-line"></i> Análisis completo
                </span>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card glass-effect">
                <div class="stat-icon">
                    <i class="fas fa-water"></i>
                </div>
                <div class="stat-content">
                    <h3>Total de riegos</h3>
                    <p><?php echo $stats['total_riegos'] ?? 0; ?></p>
                </div>
            </div>

            <div class="stat-card glass-effect">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3>Tiempo total</h3>
                    <p><?php echo round(($stats['minutos_totales'] ?? 0) / 60, 1); ?> hrs</p>
                </div>
            </div>

            <div class="stat-card glass-effect">
                <div class="stat-icon">
                    <i class="fas fa-droplet"></i>
                </div>
                <div class="stat-content">
                    <h3>Agua utilizada</h3>
                    <p><?php echo round($stats['agua_total'] ?? 0, 1); ?> L</p>
                </div>
            </div>

            <div class="stat-card glass-effect">
                <div class="stat-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="stat-content">
                    <h3>Promedio por riego</h3>
                    <p><?php echo round($stats['duracion_promedio'] ?? 0, 1); ?> min</p>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-panel glass-effect">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label>Desde</label>
                    <input type="date" name="desde" value="<?php echo $fecha_desde; ?>" class="filter-input">
                </div>
                
                <div class="filter-group">
                    <label>Hasta</label>
                    <input type="date" name="hasta" value="<?php echo $fecha_hasta; ?>" class="filter-input">
                </div>
                
                <div class="filter-group">
                    <label>Tipo de riego</label>
                    <select name="tipo" class="filter-select">
                        <option value="todos" <?php echo $tipo === 'todos' ? 'selected' : ''; ?>>Todos</option>
                        <option value="automatico" <?php echo $tipo === 'automatico' ? 'selected' : ''; ?>>Automático</option>
                        <option value="manual" <?php echo $tipo === 'manual' ? 'selected' : ''; ?>>Manual</option>
                        <option value="programado" <?php echo $tipo === 'programado' ? 'selected' : ''; ?>>Programado</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-premium primary">
                    <i class="fas fa-filter"></i> Filtrar
                </button>
                
                <a href="?export=pdf&desde=<?php echo $fecha_desde; ?>&hasta=<?php echo $fecha_hasta; ?>" class="btn-premium secondary">
                    <i class="fas fa-file-pdf"></i> Exportar
                </a>
            </form>
        </div>

        <!-- Tabla de Historial -->
        <div class="table-container glass-effect">
            <div class="table-header">
                <h2><i class="fas fa-history"></i> Registro de riegos</h2>
                <div class="table-actions">
                    <span class="table-count"><?php echo $riegos->num_rows; ?> registros</span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="premium-table">
                    <thead>
                        <tr>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th>Duración</th>
                            <th>Agua</th>
                            <th>Modo</th>
                            <th>Observación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($riegos->num_rows > 0): ?>
                            <?php while ($r = $riegos->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="date-cell">
                                            <strong><?php echo date('d/m/Y', strtotime($r["fecha_inicio"])); ?></strong>
                                            <small><?php echo date('H:i', strtotime($r["fecha_inicio"])); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="date-cell">
                                            <strong><?php echo date('d/m/Y', strtotime($r["fecha_fin"] ?? $r["fecha_inicio"])); ?></strong>
                                            <small><?php echo date('H:i', strtotime($r["fecha_fin"] ?? $r["fecha_inicio"])); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge-time">
                                            <i class="far fa-clock"></i> <?php echo $r["duracion_minutos"] ?? 0; ?> min
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge-water">
                                            <i class="fas fa-droplet"></i> <?php echo $r["cantidad_agua"] ?? 0; ?> L
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $modo = $r["activado_por"] ?? 'automático';
                                        $badgeClass = match($modo) {
                                            'manual' => 'badge-manual',
                                            'automatico' => 'badge-auto',
                                            'programado' => 'badge-schedule',
                                            default => 'badge-auto'
                                        };
                                        $icono = match($modo) {
                                            'manual' => 'fa-hand',
                                            'automatico' => 'fa-robot',
                                            'programado' => 'fa-clock',
                                            default => 'fa-robot'
                                        };
                                        ?>
                                        <span class="badge-mode <?php echo $badgeClass; ?>">
                                            <i class="fas <?php echo $icono; ?>"></i> <?php echo ucfirst($modo); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="observation-text"><?php echo htmlspecialchars($r["observacion"] ?? '-'); ?></span>
                                    </td>
                                    <td>
                                        <button class="btn-icon small" onclick="verDetalle(<?php echo $r['id'] ?? 0; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>No hay registros de riego en este período</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Gráfica de tendencias (placeholder) -->
        <div class="chart-container glass-effect">
            <h2><i class="fas fa-chart-line"></i> Tendencia de humedad</h2>
            <div class="chart-placeholder">
                <canvas id="humidityChart"></canvas>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        // Función para ver detalle
        function verDetalle(id) {
            // Implementar modal de detalle
            alert('Ver detalle del riego #' + id);
        }

        // Gráfica de ejemplo
        const ctx = document.getElementById('humidityChart')?.getContext('2d');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
                    datasets: [{
                        label: 'Humedad del suelo %',
                        data: [65, 59, 80, 81, 56, 55, 70],
                        borderColor: '#3E6BEC',
                        backgroundColor: 'rgba(62, 107, 236, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>