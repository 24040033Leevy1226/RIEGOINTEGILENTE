<?php
require_once("includes/auth.php");
require_once("includes/conexion.php");

$usuario_id = $_SESSION['usuario_id'];

// Obtener datos del usuario
$sqlUsuario = "SELECT nombre, email, foto_perfil FROM usuarios WHERE id = ?";
$stmtUsuario = $conn->prepare($sqlUsuario);
$stmtUsuario->bind_param("i", $usuario_id);
$stmtUsuario->execute();
$usuario = $stmtUsuario->get_result()->fetch_assoc();

// Obtener dispositivo activo
$sqlDispositivo = "SELECT id, nombre_dispositivo, ubicacion FROM dispositivos WHERE usuario_id = ? AND activo = 1 LIMIT 1";
$stmtDispositivo = $conn->prepare($sqlDispositivo);
$stmtDispositivo->bind_param("i", $usuario_id);
$stmtDispositivo->execute();
$dispositivo = $stmtDispositivo->get_result()->fetch_assoc();

if (!$dispositivo) {
    die("No se encontró un dispositivo activo.");
}

$dispositivo_id = $dispositivo["id"];

// Obtener período de los filtros
$periodo = $_GET['periodo'] ?? 'mensual';
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

// Estadísticas generales
$sqlStats = "SELECT 
                COUNT(*) as total_riegos,
                SUM(duracion_minutos) as minutos_totales,
                SUM(cantidad_agua) as agua_total,
                AVG(duracion_minutos) as duracion_promedio,
                MAX(duracion_minutos) as riego_maximo,
                MIN(duracion_minutos) as riego_minimo,
                COUNT(DISTINCT DATE(fecha_inicio)) as dias_con_riego
             FROM historial_riego
             WHERE dispositivo_id = ? 
             AND DATE(fecha_inicio) BETWEEN ? AND ?";
$stmtStats = $conn->prepare($sqlStats);
$stmtStats->bind_param("iss", $dispositivo_id, $fecha_inicio, $fecha_fin);
$stmtStats->execute();
$stats = $stmtStats->get_result()->fetch_assoc();

// Riegos por modo
$sqlModos = "SELECT 
                activado_por,
                COUNT(*) as cantidad,
                SUM(duracion_minutos) as minutos_totales,
                SUM(cantidad_agua) as agua_total
             FROM historial_riego
             WHERE dispositivo_id = ? 
             AND DATE(fecha_inicio) BETWEEN ? AND ?
             GROUP BY activado_por";
$stmtModos = $conn->prepare($sqlModos);
$stmtModos->bind_param("iss", $dispositivo_id, $fecha_inicio, $fecha_fin);
$stmtModos->execute();
$modos = $stmtModos->get_result();

// Riegos por día (para gráfica)
$sqlDiario = "SELECT 
                DATE(fecha_inicio) as fecha,
                COUNT(*) as cantidad,
                SUM(duracion_minutos) as minutos,
                SUM(cantidad_agua) as agua
             FROM historial_riego
             WHERE dispositivo_id = ? 
             AND DATE(fecha_inicio) BETWEEN ? AND ?
             GROUP BY DATE(fecha_inicio)
             ORDER BY fecha ASC";
$stmtDiario = $conn->prepare($sqlDiario);
$stmtDiario->bind_param("iss", $dispositivo_id, $fecha_inicio, $fecha_fin);
$stmtDiario->execute();
$diario = $stmtDiario->get_result();

// Consumo de agua por hora
$sqlHoras = "SELECT 
                HOUR(fecha_inicio) as hora,
                AVG(cantidad_agua) as promedio_agua,
                COUNT(*) as frecuencia
             FROM historial_riego
             WHERE dispositivo_id = ? 
             AND DATE(fecha_inicio) BETWEEN ? AND ?
             GROUP BY HOUR(fecha_inicio)
             ORDER BY hora";
$stmtHoras = $conn->prepare($sqlHoras);
$stmtHoras->bind_param("iss", $dispositivo_id, $fecha_inicio, $fecha_fin);
$stmtHoras->execute();
$horas = $stmtHoras->get_result();

// Preparar datos para gráficas
$fechas = [];
$riegos_diarios = [];
$agua_diaria = [];

while ($row = $diario->fetch_assoc()) {
    $fechas[] = date('d/m', strtotime($row['fecha']));
    $riegos_diarios[] = $row['cantidad'];
    $agua_diaria[] = round($row['agua'], 1);
}

// Resetear puntero
$diario->data_seek(0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes Premium - HydroHawk</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/rep.css">
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
            <a href="historial.php" class="menu-item">
                <i class="fas fa-history"></i>
                <span>Historial</span>
            </a>
            <a href="reportes.php" class="menu-item active">
                <i class="fas fa-file-alt"></i>
                <span>Reportes</span>
            </a>
            <a href="analisis.php" class="menu-item">
                <i class="fas fa-brain"></i>
                <span>Análisis IA</span>
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
                <h1>Reportes y Análisis</h1>
                <p><i class="fas fa-map-pin"></i> <?php echo htmlspecialchars($dispositivo["nombre_dispositivo"]); ?> | <?php echo htmlspecialchars($dispositivo["ubicacion"] ?? 'Sin ubicación'); ?></p>
            </div>
            <div class="hero-badge">
                <span class="badge-premium">
                    <i class="fas fa-chart-line"></i> Analytics Pro
                </span>
            </div>
        </div>

        <!-- Filtros de período -->
        <div class="filters-panel glass-effect">
            <form method="GET" class="filters-form" id="reportFilters">
                <div class="period-selector">
                    <button type="button" class="period-btn <?php echo $periodo == 'semanal' ? 'active' : ''; ?>" data-periodo="semanal">
                        <i class="fas fa-calendar-week"></i> Semanal
                    </button>
                    <button type="button" class="period-btn <?php echo $periodo == 'mensual' ? 'active' : ''; ?>" data-periodo="mensual">
                        <i class="fas fa-calendar-alt"></i> Mensual
                    </button>
                    <button type="button" class="period-btn <?php echo $periodo == 'trimestral' ? 'active' : ''; ?>" data-periodo="trimestral">
                        <i class="fas fa-calendar-plus"></i> Trimestral
                    </button>
                    <button type="button" class="period-btn <?php echo $periodo == 'personalizado' ? 'active' : ''; ?>" data-periodo="personalizado">
                        <i class="fas fa-sliders-h"></i> Personalizado
                    </button>
                </div>

                <div class="date-range" id="dateRange" style="<?php echo $periodo != 'personalizado' ? 'display: none;' : ''; ?>">
                    <div class="filter-group">
                        <label>Desde</label>
                        <input type="date" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>" class="filter-input">
                    </div>
                    <div class="filter-group">
                        <label>Hasta</label>
                        <input type="date" name="fecha_fin" value="<?php echo $fecha_fin; ?>" class="filter-input">
                    </div>
                </div>

                <input type="hidden" name="periodo" id="periodoInput" value="<?php echo $periodo; ?>">

                <div class="filter-actions">
                    <button type="submit" class="btn-premium primary">
                        <i class="fas fa-sync-alt"></i> Actualizar
                    </button>
                    <button type="button" class="btn-premium secondary" onclick="exportarReporte('pdf')">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                    <button type="button" class="btn-premium secondary" onclick="exportarReporte('excel')">
                        <i class="fas fa-file-excel"></i> Excel
                    </button>
                </div>
            </form>
        </div>

        <!-- KPIs principales -->
        <div class="kpi-grid">
            <div class="kpi-card glass-effect">
                <div class="kpi-icon blue">
                    <i class="fas fa-water"></i>
                </div>
                <div class="kpi-content">
                    <h3>Total de riegos</h3>
                    <div class="kpi-value"><?php echo $stats['total_riegos'] ?? 0; ?></div>
                    <div class="kpi-trend positive">
                        <i class="fas fa-arrow-up"></i> +12% vs período anterior
                    </div>
                </div>
            </div>

            <div class="kpi-card glass-effect">
                <div class="kpi-icon green">
                    <i class="fas fa-droplet"></i>
                </div>
                <div class="kpi-content">
                    <h3>Agua utilizada</h3>
                    <div class="kpi-value"><?php echo round($stats['agua_total'] ?? 0, 1); ?> L</div>
                    <div class="kpi-trend negative">
                        <i class="fas fa-arrow-down"></i> -8% vs período anterior
                    </div>
                </div>
            </div>

            <div class="kpi-card glass-effect">
                <div class="kpi-icon purple">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="kpi-content">
                    <h3>Tiempo total</h3>
                    <div class="kpi-value"><?php echo round(($stats['minutos_totales'] ?? 0) / 60, 1); ?> hrs</div>
                    <div class="kpi-trend neutral">
                        <i class="fas fa-minus"></i> Sin cambios
                    </div>
                </div>
            </div>

            <div class="kpi-card glass-effect">
                <div class="kpi-icon orange">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="kpi-content">
                    <h3>Promedio por riego</h3>
                    <div class="kpi-value"><?php echo round($stats['duracion_promedio'] ?? 0, 1); ?> min</div>
                    <div class="kpi-trend positive">
                        <i class="fas fa-arrow-up"></i> +5% eficiencia
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficas principales -->
        <div class="charts-grid">
            <!-- Gráfica de tendencia diaria -->
            <div class="chart-card glass-effect">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-line"></i> Tendencia de riegos</h3>
                    <div class="chart-legend">
                        <span><i class="fas fa-circle" style="color: var(--primary);"></i> Cantidad de riegos</span>
                        <span><i class="fas fa-circle" style="color: var(--success);"></i> Agua (L)</span>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <!-- Gráfica de distribución por modo -->
            <div class="chart-card glass-effect">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-pie"></i> Distribución por modo</h3>
                </div>
                <div class="chart-container">
                    <canvas id="modeChart"></canvas>
                </div>
            </div>

            <!-- Gráfica de consumo por hora -->
            <div class="chart-card glass-effect">
                <div class="chart-header">
                    <h3><i class="fas fa-clock"></i> Consumo por hora</h3>
                </div>
                <div class="chart-container">
                    <canvas id="hourlyChart"></canvas>
                </div>
            </div>

            <!-- Tarjetas de estadísticas adicionales -->
            <div class="stats-mini-card glass-effect">
                <h3><i class="fas fa-chart-simple"></i> Estadísticas clave</h3>
                <div class="stats-mini-grid">
                    <div class="stat-mini">
                        <span>Riego máximo</span>
                        <strong><?php echo round($stats['riego_maximo'] ?? 0, 1); ?> min</strong>
                    </div>
                    <div class="stat-mini">
                        <span>Riego mínimo</span>
                        <strong><?php echo round($stats['riego_minimo'] ?? 0, 1); ?> min</strong>
                    </div>
                    <div class="stat-mini">
                        <span>Días con riego</span>
                        <strong><?php echo $stats['dias_con_riego'] ?? 0; ?> días</strong>
                    </div>
                    <div class="stat-mini">
                        <span>Frecuencia diaria</span>
                        <strong><?php echo round(($stats['total_riegos'] ?? 0) / max(1, $stats['dias_con_riego'] ?? 1), 1); ?>/día</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de desglose por modo -->
        <div class="table-container glass-effect">
            <div class="table-header">
                <h2><i class="fas fa-chart-pie"></i> Desglose por modo de operación</h2>
            </div>
            <div class="table-responsive">
                <table class="premium-table">
                    <thead>
                        <tr>
                            <th>Modo</th>
                            <th>Cantidad</th>
                            <th>Tiempo total</th>
                            <th>Agua total</th>
                            <th>Promedio por riego</th>
                            <th>% del total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_agua = $stats['agua_total'] ?? 1;
                        while ($modo = $modos->fetch_assoc()): 
                            $porcentaje = round(($modo['agua_total'] / $total_agua) * 100, 1);
                            $clase = match($modo['activado_por']) {
                                'automatico' => 'badge-auto',
                                'manual' => 'badge-manual',
                                'programado' => 'badge-schedule',
                                default => 'badge-auto'
                            };
                        ?>
                        <tr>
                            <td>
                                <span class="badge-mode <?php echo $clase; ?>">
                                    <i class="fas fa-<?php echo $modo['activado_por'] == 'automatico' ? 'robot' : ($modo['activado_por'] == 'manual' ? 'hand' : 'clock'); ?>"></i>
                                    <?php echo ucfirst($modo['activado_por']); ?>
                                </span>
                            </td>
                            <td><strong><?php echo $modo['cantidad']; ?></strong> riegos</td>
                            <td><?php echo round($modo['minutos_totales'] / 60, 1); ?> hrs</td>
                            <td><?php echo round($modo['agua_total'], 1); ?> L</td>
                            <td><?php echo round($modo['minutos_totales'] / $modo['cantidad'], 1); ?> min</td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $porcentaje; ?>%;"></div>
                                    <span><?php echo $porcentaje; ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recomendaciones IA -->
        <div class="insights-panel glass-effect">
            <h2><i class="fas fa-brain"></i> Insights y recomendaciones</h2>
            <div class="insights-grid">
                <div class="insight-card">
                    <div class="insight-icon success">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <div class="insight-content">
                        <h4>Optimización de horarios</h4>
                        <p>El mayor consumo se registra entre las 6-9 AM. Considera ajustar el horario para optimizar la absorción.</p>
                    </div>
                </div>

                <div class="insight-card">
                    <div class="insight-icon warning">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="insight-content">
                        <h4>Alertas de consumo</h4>
                        <p>El consumo de agua ha aumentado un 15% respecto al mes anterior. Revisa posibles fugas.</p>
                    </div>
                </div>

                <div class="insight-card">
                    <div class="insight-icon info">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="insight-content">
                        <h4>Predicción</h4>
                        <p>Se espera un aumento del 20% en la necesidad de riego para la próxima semana según el clima.</p>
                    </div>
                </div>

                <div class="insight-card">
                    <div class="insight-icon primary">
                        <i class="fas fa-leaf"></i>
                    </div>
                    <div class="insight-content">
                        <h4>Ahorro estimado</h4>
                        <p>Con la configuración actual, ahorras aproximadamente 120L de agua al mes vs riego manual.</p>
                    </div>
                </div>
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

        // Period selector
        document.querySelectorAll('.period-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const periodo = this.dataset.periodo;
                document.getElementById('periodoInput').value = periodo;
                
                if (periodo === 'personalizado') {
                    document.getElementById('dateRange').style.display = 'flex';
                } else {
                    document.getElementById('dateRange').style.display = 'none';
                    
                    // Calcular fechas según período
                    const hoy = new Date();
                    let fechaInicio = new Date();
                    
                    switch(periodo) {
                        case 'semanal':
                            fechaInicio.setDate(hoy.getDate() - 7);
                            break;
                        case 'mensual':
                            fechaInicio.setMonth(hoy.getMonth() - 1);
                            break;
                        case 'trimestral':
                            fechaInicio.setMonth(hoy.getMonth() - 3);
                            break;
                    }
                    
                    document.querySelector('input[name="fecha_inicio"]').value = fechaInicio.toISOString().split('T')[0];
                    document.querySelector('input[name="fecha_fin"]').value = hoy.toISOString().split('T')[0];
                }
            });
        });

        // Inicializar gráficas
        document.addEventListener('DOMContentLoaded', function() {
            // Gráfica de tendencia
            const ctxTrend = document.getElementById('trendChart').getContext('2d');
            new Chart(ctxTrend, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($fechas); ?>,
                    datasets: [
                        {
                            label: 'Cantidad de riegos',
                            data: <?php echo json_encode($riegos_diarios); ?>,
                            borderColor: '#3E6BEC',
                            backgroundColor: 'rgba(62, 107, 236, 0.1)',
                            borderWidth: 3,
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Agua (L)',
                            data: <?php echo json_encode($agua_diaria); ?>,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 3,
                            tension: 0.4,
                            fill: true,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(62, 107, 236, 0.1)'
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });

            // Gráfica de modos
            const ctxMode = document.getElementById('modeChart').getContext('2d');
            
            <?php
            $modos->data_seek(0);
            $modos_labels = [];
            $modos_data = [];
            $modos_colors = [];
            
            while ($modo = $modos->fetch_assoc()) {
                $modos_labels[] = ucfirst($modo['activado_por']);
                $modos_data[] = $modo['cantidad'];
                $modos_colors[] = match($modo['activado_por']) {
                    'automatico' => '#3E6BEC',
                    'manual' => '#f59e0b',
                    'programado' => '#10b981',
                    default => '#64748B'
                };
            }
            ?>
            
            new Chart(ctxMode, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($modos_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($modos_data); ?>,
                        backgroundColor: <?php echo json_encode($modos_colors); ?>,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        }
                    },
                    cutout: '70%'
                }
            });

            // Gráfica de consumo por hora
            const ctxHourly = document.getElementById('hourlyChart').getContext('2d');
            
            <?php
            $horas_data = array_fill(0, 24, 0);
            $horas->data_seek(0);
            while ($hora = $horas->fetch_assoc()) {
                $horas_data[$hora['hora']] = $hora['promedio_agua'];
            }
            ?>
            
            new Chart(ctxHourly, {
                type: 'bar',
                data: {
                    labels: Array.from({length: 24}, (_, i) => i + ':00'),
                    datasets: [{
                        label: 'Consumo promedio (L)',
                        data: <?php echo json_encode($horas_data); ?>,
                        backgroundColor: 'rgba(62, 107, 236, 0.8)',
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(62, 107, 236, 0.1)'
                            }
                        }
                    }
                }
            });
        });

        // Función para exportar reportes
        function exportarReporte(formato) {
            const fechaInicio = document.querySelector('input[name="fecha_inicio"]').value;
            const fechaFin = document.querySelector('input[name="fecha_fin"]').value;
            
            let url = `api/exportar_reporte.php?formato=${formato}&fecha_inicio=${fechaInicio}&fecha_fin=${fechaFin}`;
            
            if (formato === 'pdf') {
                window.open(url, '_blank');
            } else {
                window.location.href = url;
            }
        }
    </script>
</body>
</html>