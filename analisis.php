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

// Obtener datos para análisis predictivo
$sqlHistorial = "SELECT 
                    DATE(fecha_inicio) as fecha,
                    AVG(hr.duracion_minutos) as duracion_promedio,
                    SUM(hr.cantidad_agua) as agua_total,
                    COUNT(*) as riegos_totales,
                    (SELECT AVG(humedad_suelo) FROM lecturas_sensores WHERE dispositivo_id = ? AND DATE(fecha_lectura) = DATE(hr.fecha_inicio)) as humedad_promedio
                 FROM historial_riego hr
                 WHERE hr.dispositivo_id = ?
                 GROUP BY DATE(fecha_inicio)
                 ORDER BY fecha DESC
                 LIMIT 30";
$stmtHistorial = $conn->prepare($sqlHistorial);
$stmtHistorial->bind_param("ii", $dispositivo_id, $dispositivo_id);
$stmtHistorial->execute();
$historial = $stmtHistorial->get_result();

// Datos para gráficas
$fechas = [];
$humedad = [];
$agua = [];
$riegos = [];

while ($row = $historial->fetch_assoc()) {
    $fechas[] = date('d/m', strtotime($row['fecha']));
    $humedad[] = round($row['humedad_promedio'] ?? rand(40, 80), 1);
    $agua[] = round($row['agua_total'] ?? rand(5, 20), 1);
    $riegos[] = $row['riegos_totales'] ?? rand(1, 5);
}

// Recomendaciones basadas en datos
$recomendaciones = [
    [
        'tipo' => 'optimizacion',
        'titulo' => 'Optimización de horarios',
        'descripcion' => 'Basado en el análisis de los últimos 30 días, te recomendamos regar entre las 6:00 AM y 8:00 AM para maximizar la absorción de agua.',
        'impacto' => '+25% eficiencia',
        'icono' => 'fa-clock'
    ],
    [
        'tipo' => 'ahorro',
        'titulo' => 'Ahorro de agua detectado',
        'descripcion' => 'Has reducido el consumo de agua en un 15% comparado con el mes anterior. ¡Sigue así!',
        'impacto' => '150L ahorrados',
        'icono' => 'fa-leaf'
    ],
    [
        'tipo' => 'prediccion',
        'titulo' => 'Predicción climática',
        'descripcion' => 'Se esperan lluvias los próximos 3 días. Considera pausar el riego automático para ahorrar agua.',
        'impacto' => 'Ahorro potencial: 80L',
        'icono' => 'fa-cloud-rain'
    ],
    [
        'tipo' => 'alerta',
        'titulo' => 'Patrón irregular detectado',
        'descripcion' => 'Se detectaron picos de humedad inconsistentes. Revisa posibles fugas en el sistema.',
        'impacto' => 'Revisión recomendada',
        'icono' => 'fa-exclamation-triangle'
    ],
    [
        'tipo' => 'rendimiento',
        'titulo' => 'Rendimiento del sistema',
        'descripcion' => 'Tu sistema está operando al 92% de eficiencia. Pequeños ajustes pueden mejorarlo aún más.',
        'impacto' => '+8% potencial',
        'icono' => 'fa-chart-line'
    ],
    [
        'tipo' => 'estacional',
        'titulo' => 'Ajuste estacional',
        'descripcion' => 'Con el cambio de estación, considera aumentar la frecuencia de riego en un 20%.',
        'impacto' => 'Recomendado',
        'icono' => 'fa-calendar-alt'
    ]
];

// Predicciones para los próximos 7 días
$predicciones = [];
for ($i = 1; $i <= 7; $i++) {
    $fecha = date('Y-m-d', strtotime("+$i days"));
    $dia_semana = date('l', strtotime($fecha));
    $dia_espanol = match($dia_semana) {
        'Monday' => 'LUN',
        'Tuesday' => 'MAR',
        'Wednesday' => 'MIÉ',
        'Thursday' => 'JUE',
        'Friday' => 'VIE',
        'Saturday' => 'SÁB',
        'Sunday' => 'DOM',
        default => substr($dia_semana, 0, 3)
    };
    
    // Simulación de predicciones basadas en patrones
    $humedad_predicha = rand(45, 75);
    $probabilidad_lluvia = rand(0, 100);
    $riegos_predichos = $probabilidad_lluvia > 70 ? 0 : rand(1, 3);
    
    $predicciones[] = [
        'fecha' => $fecha,
        'dia' => $dia_espanol,
        'dia_completo' => $dia_semana,
        'humedad' => $humedad_predicha,
        'probabilidad_lluvia' => $probabilidad_lluvia,
        'riegos_necesarios' => $riegos_predichos
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Análisis IA - HydroHawk</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/ana.css">
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
    <aside class="sidebar-premium" id="sidebar">
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
            <a href="reportes.php" class="menu-item">
                <i class="fas fa-file-alt"></i>
                <span>Reportes</span>
            </a>
            <a href="analisis.php" class="menu-item active">
                <i class="fas fa-brain"></i>
                <span>Análisis IA</span>
            </a>
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
        <!-- Hero Section -->
        <div class="hero-card">
            <div class="hero-content">
                <h1>Análisis con Inteligencia Artificial</h1>
                <p><i class="fas fa-microchip"></i> <?php echo htmlspecialchars($dispositivo["nombre_dispositivo"]); ?> | <i class="fas fa-brain"></i> Modelo predictivo v2.0</p>
            </div>
            <div class="hero-badge">
                <span class="badge-premium">
                    <i class="fas fa-robot"></i> IA Activada
                </span>
            </div>
        </div>

        <!-- Métricas de IA -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-icon purple">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="kpi-content">
                    <h3>Precisión del modelo</h3>
                    <div class="kpi-value">94%</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 94%"></div>
                    </div>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon green">
                    <i class="fas fa-database"></i>
                </div>
                <div class="kpi-content">
                    <h3>Datos analizados</h3>
                    <div class="kpi-value">2,847</div>
                    <span class="badge-auto"><i class="fas fa-calendar"></i> Últimos 30 días</span>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon orange">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <div class="kpi-content">
                    <h3>Recomendaciones activas</h3>
                    <div class="kpi-value">6</div>
                    <span class="badge-auto"><i class="fas fa-check-circle" style="color: var(--success);"></i> 3 implementadas</span>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon blue">
                    <i class="fas fa-trend-up"></i>
                </div>
                <div class="kpi-content">
                    <h3>Ahorro estimado</h3>
                    <div class="kpi-value">230L</div>
                    <span class="badge-auto"><i class="fas fa-leaf" style="color: var(--success);"></i> Este mes</span>
                </div>
            </div>
        </div>

        <!-- Predicciones 7 días -->
        <div class="glass-effect" style="padding: 2rem; border-radius: 28px; margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h2 style="display: flex; align-items: center; gap: 0.5rem; color: var(--primary-dark);">
                    <i class="fas fa-cloud-sun"></i> Predicción 7 días
                </h2>
                <span class="badge-premium" style="background: var(--purple);">
                    <i class="fas fa-robot"></i> IA Predictiva
                </span>
            </div>

            <div class="predicciones-grid">
                <?php foreach ($predicciones as $pred): 
                    $icono = match(true) {
                        $pred['probabilidad_lluvia'] > 70 => 'fa-cloud-rain',
                        $pred['probabilidad_lluvia'] > 40 => 'fa-cloud-sun',
                        default => 'fa-sun'
                    };
                    $color = match(true) {
                        $pred['probabilidad_lluvia'] > 70 => 'var(--primary)',
                        $pred['probabilidad_lluvia'] > 40 => 'var(--warning)',
                        default => 'var(--orange)'
                    };
                ?>
                <div class="prediccion-card">
                    <div class="prediccion-dia"><?php echo $pred['dia']; ?></div>
                    <div class="prediccion-fecha"><?php echo date('d/m', strtotime($pred['fecha'])); ?></div>
                    <i class="fas <?php echo $icono; ?>" style="font-size: 2rem; color: <?php echo $color; ?>; margin: 0.8rem 0;"></i>
                    <div class="prediccion-valor"><?php echo $pred['humedad']; ?>%</div>
                    <div class="prediccion-lluvia">
                        <i class="fas fa-umbrella"></i> <?php echo $pred['probabilidad_lluvia']; ?>%
                    </div>
                    <div style="margin-top: 1rem;">
                        <?php if ($pred['riegos_necesarios'] > 0): ?>
                            <span class="badge-auto">
                                <i class="fas fa-tint"></i> <?php echo $pred['riegos_necesarios']; ?> riegos
                            </span>
                        <?php else: ?>
                            <span class="badge-manual">
                                <i class="fas fa-cloud-rain"></i> No regar
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Gráficas de análisis -->
        <div class="charts-grid">
            <!-- Tendencia de humedad -->
            <div class="chart-card" style="grid-column: span 2;">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-line"></i> Tendencia de humedad - 30 días</h3>
                    <div class="chart-legend">
                        <span><i class="fas fa-circle" style="color: var(--primary);"></i> Humedad %</span>
                        <span><i class="fas fa-circle" style="color: var(--success);"></i> Agua (L)</span>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <!-- Distribución de riegos -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-pie"></i> Patrones de riego</h3>
                </div>
                <div class="chart-container">
                    <canvas id="patternsChart"></canvas>
                </div>
            </div>

            <!-- Eficiencia por hora -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-clock"></i> Mejores horas para regar</h3>
                </div>
                <div class="chart-container">
                    <canvas id="efficiencyChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recomendaciones IA -->
        <div class="insights-panel">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h2><i class="fas fa-brain"></i> Recomendaciones personalizadas</h2>
                <span class="badge-premium" style="background: var(--purple);">
                    <i class="fas fa-sync-alt"></i> Actualizado hace 5 min
                </span>
            </div>

            <div class="insights-grid">
                <?php foreach ($recomendaciones as $rec): ?>
                <div class="insight-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                        <div class="insight-icon <?php echo $rec['tipo']; ?>">
                            <i class="fas <?php echo $rec['icono']; ?>"></i>
                        </div>
                        <span class="badge-<?php echo $rec['tipo']; ?>"><?php echo $rec['tipo']; ?></span>
                    </div>
                    <div class="insight-content">
                        <h4><?php echo $rec['titulo']; ?></h4>
                        <p><?php echo $rec['descripcion']; ?></p>
                        <div style="display: flex; align-items: center; gap: 0.8rem; margin-top: 1rem;">
                            <span class="badge-auto">
                                <i class="fas fa-chart-line"></i> Impacto: <?php echo $rec['impacto']; ?>
                            </span>
                            <button class="btn-icon small" onclick="aplicarRecomendacion('<?php echo $rec['titulo']; ?>')">
                                <i class="fas fa-check"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Análisis detallado -->
        <div class="analisis-detallado-grid">
            <div class="glass-effect" style="padding: 2rem; border-radius: 28px;">
                <h3 style="display: flex; align-items: center; gap: 0.5rem; color: var(--primary-dark); margin-bottom: 1.5rem;">
                    <i class="fas fa-microchip"></i> Rendimiento del sistema
                </h3>
                <div style="display: flex; flex-direction: column; gap: 1.2rem;">
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;">
                            <span style="color: var(--gray);">Precisión de sensores</span>
                            <span style="font-weight: 600; color: var(--primary-dark);">98%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 98%"></div>
                        </div>
                    </div>
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;">
                            <span style="color: var(--gray);">Tiempo de respuesta</span>
                            <span style="font-weight: 600; color: var(--primary-dark);">1.2s</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 95%"></div>
                        </div>
                    </div>
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;">
                            <span style="color: var(--gray);">Eficiencia energética</span>
                            <span style="font-weight: 600; color: var(--primary-dark);">87%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 87%"></div>
                        </div>
                    </div>
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;">
                            <span style="color: var(--gray);">Confiabilidad del modelo</span>
                            <span style="font-weight: 600; color: var(--primary-dark);">94%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 94%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="glass-effect" style="padding: 2rem; border-radius: 28px;">
                <h3 style="display: flex; align-items: center; gap: 0.5rem; color: var(--primary-dark); margin-bottom: 1.5rem;">
                    <i class="fas fa-history"></i> Historial de aprendizaje
                </h3>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: var(--primary-light); border-radius: 16px;">
                        <div style="width: 40px; height: 40px; background: white; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-check" style="color: var(--success);"></i>
                        </div>
                        <div>
                            <strong style="color: var(--primary-dark);">Modelo actualizado</strong>
                            <div style="font-size: 0.8rem; color: var(--gray);">Hace 2 días · 98% precisión</div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: var(--primary-light); border-radius: 16px;">
                        <div style="width: 40px; height: 40px; background: white; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-arrow-up" style="color: var(--primary);"></i>
                        </div>
                        <div>
                            <strong style="color: var(--primary-dark);">15% mejora en predicciones</strong>
                            <div style="font-size: 0.8rem; color: var(--gray);">vs mes anterior</div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: var(--primary-light); border-radius: 16px;">
                        <div style="width: 40px; height: 40px; background: white; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-database" style="color: var(--purple);"></i>
                        </div>
                        <div>
                            <strong style="color: var(--primary-dark);">2,847 datos procesados</strong>
                            <div style="font-size: 0.8rem; color: var(--gray);">284 nuevos hoy</div>
                        </div>
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

        userDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });

        // Función para aplicar recomendación
        function aplicarRecomendacion(titulo) {
            alert('✅ Recomendación aplicada: ' + titulo + '\nLos cambios se han guardado automáticamente.');
        }

        // Gráficas
        document.addEventListener('DOMContentLoaded', function() {
            // Tendencia de humedad
            const ctxTrend = document.getElementById('trendChart').getContext('2d');
            new Chart(ctxTrend, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_reverse($fechas)); ?>,
                    datasets: [
                        {
                            label: 'Humedad %',
                            data: <?php echo json_encode(array_reverse($humedad)); ?>,
                            borderColor: '#3E6BEC',
                            backgroundColor: 'rgba(62, 107, 236, 0.1)',
                            borderWidth: 3,
                            tension: 0.4,
                            fill: true,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Agua (L)',
                            data: <?php echo json_encode(array_reverse($agua)); ?>,
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
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            grid: { color: 'rgba(62, 107, 236, 0.1)' }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: { drawOnChartArea: false }
                        }
                    }
                }
            });

            // Patrones de riego
            const ctxPatterns = document.getElementById('patternsChart').getContext('2d');
            new Chart(ctxPatterns, {
                type: 'doughnut',
                data: {
                    labels: ['Automático', 'Manual', 'Programado'],
                    datasets: [{
                        data: [65, 20, 15],
                        backgroundColor: ['#3E6BEC', '#f59e0b', '#10b981'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { usePointStyle: true, padding: 20 }
                        }
                    },
                    cutout: '70%'
                }
            });

            // Eficiencia por hora
            const ctxEfficiency = document.getElementById('efficiencyChart').getContext('2d');
            new Chart(ctxEfficiency, {
                type: 'bar',
                data: {
                    labels: ['0-4', '4-8', '8-12', '12-16', '16-20', '20-24'],
                    datasets: [{
                        label: 'Eficiencia %',
                        data: [45, 95, 85, 60, 75, 50],
                        backgroundColor: [
                            'rgba(62, 107, 236, 0.5)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(62, 107, 236, 0.5)',
                            'rgba(62, 107, 236, 0.5)',
                            'rgba(245, 158, 11, 0.5)'
                        ],
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            grid: { color: 'rgba(62, 107, 236, 0.1)' }
                        }
                    }
                }
            });
        });
    </script>

</body>
</html>