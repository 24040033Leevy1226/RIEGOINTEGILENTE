<?php
require_once("includes/auth.php");
require_once("includes/conexion.php");

$usuario_id = $_SESSION['usuario_id'];

$sqlUsuario = "SELECT nombre, email, foto_perfil FROM usuarios WHERE id = ?";
$stmtUsuario = $conn->prepare($sqlUsuario);
$stmtUsuario->bind_param("i", $usuario_id);
$stmtUsuario->execute();
$usuario = $stmtUsuario->get_result()->fetch_assoc();

$sqlDispositivo = "SELECT d.id, d.nombre_dispositivo, d.ubicacion
                   FROM dispositivos d
                   WHERE d.usuario_id = ? AND d.activo = 1
                   LIMIT 1";
$stmtDispositivo = $conn->prepare($sqlDispositivo);
$stmtDispositivo->bind_param("i", $usuario_id);
$stmtDispositivo->execute();
$dispositivo = $stmtDispositivo->get_result()->fetch_assoc();

if (!$dispositivo) {
    die("No se encontró un dispositivo activo. <a href='configuracion.php'>Configurar ahora</a>");
}

$dispositivo_id = (int)$dispositivo['id'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>HydroHawk Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        const DISPOSITIVO_ID = <?php echo $dispositivo_id; ?>;
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dash.css">

    <style>
        #riegoTimer {
            display: none;
            background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(62,107,236,0.08));
            border: 1.5px solid rgba(16,185,129,0.35);
            border-radius: 16px;
            padding: 1rem 1.5rem;
            margin-top: 1.25rem;
            align-items: center;
            gap: 1.25rem;
            flex-wrap: wrap;
        }
        #riegoTimer.activo { display: flex; }
        .timer-circle {
            width: 64px; height: 64px; border-radius: 50%;
            background: rgba(16,185,129,0.15); border: 3px solid #10b981;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .timer-circle i { font-size: 1.6rem; color: #10b981; animation: spin 2s linear infinite; }
        @keyframes spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }
        .timer-info { flex: 1; }
        .timer-info strong { display: block; font-size: 1rem; color: var(--primary-dark, #1e3a8a); }
        .timer-info span { font-size: 0.85rem; color: var(--gray, #6b7280); }
        #timerCountdown { font-size: 2rem; font-weight: 800; color: #10b981; min-width: 80px; text-align: center; }
        .progress-riego { width: 100%; height: 6px; background: rgba(16,185,129,0.15); border-radius: 10px; margin-top: 0.6rem; overflow: hidden; }
        .progress-riego-fill { height: 100%; background: #10b981; border-radius: 10px; transition: width 1s linear; }
        .metros-input-wrap { display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem; }
        .metros-input-wrap input { width: 90px; text-align: center; }
        .tiempo-auto-badge {
            display: inline-flex; align-items: center; gap: 0.4rem;
            background: rgba(62,107,236,0.1); color: var(--primary, #3e6bec);
            border-radius: 20px; padding: 0.3rem 0.75rem;
            font-size: 0.8rem; font-weight: 600; white-space: nowrap;
        }
    </style>
</head>
<body class="dashboard-body">

    <nav class="navbar-premium">
        <div class="nav-left">
            <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
            <img src="assets/img/hydro_logo.png" alt="HydroHawk" class="nav-logo">
            <span class="nav-brand">HydroHawk</span>
        </div>
        <div class="nav-right">
            <div class="nav-notifications" onclick="window.location.href='soporte.php'">
                <i class="fas fa-bell"></i>
                <span class="notification-badge" id="notificacionBadge">0</span>
            </div>
            <div class="nav-user" id="userMenuTrigger">
                <div class="user-avatar">
                    <?php if (!empty($usuario['foto_perfil'])): ?>
                        <img src="<?php echo htmlspecialchars($usuario['foto_perfil']); ?>" alt="Perfil">
                    <?php else: ?>
                        <div class="avatar-placeholder"><?php echo strtoupper(substr($usuario['nombre'], 0, 1)); ?></div>
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
                <a href="perfil.php"        class="dropdown-item"><i class="fas fa-user"></i> Mi Perfil</a>
                <a href="configuracion.php" class="dropdown-item"><i class="fas fa-cog"></i> Ajustes de cuenta</a>
                <a href="seguridad.php"     class="dropdown-item"><i class="fas fa-shield-alt"></i> Seguridad</a>
                <div class="dropdown-divider"></div>
                <a href="logout.php" class="dropdown-item logout"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
            </div>
        </div>
    </nav>

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
            <a href="dashboard.php"     class="menu-item active"><i class="fas fa-chart-pie"></i><span>Dashboard</span></a>
            <a href="configuracion.php" class="menu-item"><i class="fas fa-sliders-h"></i><span>Configuración</span></a>
            <a href="historial.php"     class="menu-item"><i class="fas fa-history"></i><span>Historial</span></a>
            <a href="reportes.php"      class="menu-item"><i class="fas fa-file-alt"></i><span>Reportes</span></a>
            <a href="analisis.php"      class="menu-item"><i class="fas fa-brain"></i><span>Análisis IA</span></a>
            <a href="soporte.php"       class="menu-item"><i class="fas fa-headset"></i><span>Soporte</span></a>
        </nav>
        <div class="sidebar-footer">
            <div class="system-status">
                <i class="fas fa-circle"></i>
                <span>Sistema en línea</span>
            </div>
        </div>
    </aside>

    <main class="main-content" id="mainContent">

        <div class="hero-card">
            <div class="hero-content">
                <h1><?php echo htmlspecialchars($dispositivo['nombre_dispositivo']); ?></h1>
                <p><i class="fas fa-map-pin"></i> <?php echo htmlspecialchars($dispositivo['ubicacion'] ?: 'Ubicación no definida'); ?></p>
            </div>
            <div class="hero-badge">
                <span class="badge-premium"><i class="fas fa-shield-alt"></i> Premium</span>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card glass-effect">
                <div class="stat-icon"><i class="fas fa-tint"></i></div>
                <div class="stat-content"><h3>Humedad del suelo</h3><p id="humedadSuelo">-- %</p></div>
                <div class="stat-trend positive" id="humedadTrend"><i class="fas fa-minus"></i></div>
            </div>
            <div class="stat-card glass-effect">
                <div class="stat-icon"><i class="fas fa-water"></i></div>
                <div class="stat-content"><h3>Estado de bomba</h3><p id="estadoBomba">--</p></div>
                <div class="stat-trend" id="bombaIndicator"><i class="fas fa-circle"></i></div>
            </div>
            <div class="stat-card glass-effect">
                <div class="stat-icon"><i class="fas fa-robot"></i></div>
                <div class="stat-content"><h3>Modo</h3><p id="modoSistema">--</p></div>
            </div>
            <div class="stat-card glass-effect">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-content"><h3>Última actualización</h3><p id="ultimaActualizacion">--</p></div>
            </div>
        </div>

        <!-- Control Panel -->
        <div class="control-panel glass-effect">
            <div class="panel-header">
                <h2><i class="fas fa-gamepad"></i> Control de riego</h2>
                <div class="panel-actions">
                    <button class="btn-icon" onclick="cargarEstado()" title="Actualizar">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
            <div class="buttons-wrap">
                <button id="btnEncender"   onclick="enviarComando('encender')"   class="btn-premium primary"><i class="fas fa-play"></i> Encender bomba</button>
                <button id="btnApagar"     onclick="enviarComando('apagar')"     class="btn-premium danger"><i class="fas fa-stop"></i> Apagar bomba</button>
                <button id="btnAutomatico" onclick="enviarComando('automatico')" class="btn-premium success"><i class="fas fa-brain"></i> Modo automático</button>
                <button id="btnManual"     onclick="enviarComando('manual')"     class="btn-premium warning"><i class="fas fa-hand"></i> Modo manual</button>
            </div>
            <div id="mensajeAccion" class="message-box"></div>
        </div>

        <!-- Config Summary -->
        <div class="config-summary glass-effect">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
                <h2 style="margin:0;"><i class="fas fa-sliders-h"></i> Configuración actual</h2>
            </div>

            <div class="config-grid">
                <div class="config-item">
                    <span>Humedad mínima</span>
                    <strong id="configHumedadMin">-- %</strong>
                    <div class="progress-bar"><div class="progress-fill" id="progressMin" style="width:30%"></div></div>
                </div>
                <div class="config-item">
                    <span>Humedad máxima</span>
                    <strong id="configHumedadMax">-- %</strong>
                    <div class="progress-bar"><div class="progress-fill" id="progressMax" style="width:70%"></div></div>
                </div>
                <div class="config-item">
                    <span>Duración riego</span>
                    <strong id="configDuracion">-- min</strong>
                </div>
                <div class="config-item">
                    <span>Horario</span>
                    <strong id="configHorario">--</strong>
                </div>
                <div class="config-item">
                    <span>Notificaciones</span>
                    <strong id="configNotificaciones">--</strong>
                </div>
                <div class="config-item">
                    <span>Tiempo calculado</span>
                    <strong id="tiempoCalculado">--</strong>
                </div>
            </div>

            <p id="configResumen" class="config-resumen" style="margin-top:1rem; font-size:0.9rem; color:var(--gray);"></p>

            <div style="margin-top:1.5rem; padding-top:1.5rem; border-top:1px solid rgba(62,107,236,0.1);">
                <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem;">

                    <div>
                        <label style="font-size:0.82rem; font-weight:600; color:var(--primary-dark,#1e3a8a); display:block; margin-bottom:0.5rem;">
                            <i class="fas fa-ruler-combined"></i> Metros cuadrados del terreno
                        </label>
                        <div class="metros-input-wrap">
                            <input type="number" id="metrosTerreno" class="input-premium"
                                   min="1" step="0.5" placeholder="Ej: 45"
                                   oninput="recalcularTiempoConfig()">
                            <span style="font-weight:600; color:var(--gray,#6b7280);">m²</span>
                            <span id="tiempoAutoBadge" class="tiempo-auto-badge" style="display:none;">
                                <i class="fas fa-clock"></i>
                                <span id="tiempoAutoTexto">-- min</span>
                            </span>
                        </div>
                        <small style="color:var(--gray,#6b7280); font-size:0.78rem; display:block; margin-top:0.3rem;">
                            El tiempo se calcula automáticamente: 2 L/m² ÷ caudal de la bomba
                        </small>
                    </div>

                    <div style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end;">
                        <button onclick="cambiarManualYEncender()"
                                class="btn-premium primary"
                                style="padding:0.6rem 1.2rem; font-size:0.88rem;">
                            <i class="fas fa-play"></i> Encender bomba
                        </button>
                        <button onclick="cambiarManualYApagar()"
                                class="btn-premium danger"
                                style="padding:0.6rem 1.2rem; font-size:0.88rem;">
                            <i class="fas fa-stop"></i> Apagar bomba
                        </button>
                        <button onclick="enviarComando('automatico')"
                                class="btn-premium success"
                                style="padding:0.6rem 1.2rem; font-size:0.88rem;">
                            <i class="fas fa-brain"></i> Modo automático
                        </button>
                    </div>
                </div>

                <!-- Temporizador -->
                <div id="riegoTimer">
                    <div class="timer-circle"><i class="fas fa-tint"></i></div>
                    <div class="timer-info">
                        <strong>Riego en progreso</strong>
                        <span id="timerLabel">Apagado automático en:</span>
                        <div class="progress-riego">
                            <div class="progress-riego-fill" id="timerProgressBar" style="width:100%"></div>
                        </div>
                    </div>
                    <div id="timerCountdown">--:--</div>
                </div>
            </div>
        </div>

        <!-- Quick Config -->
        <div class="quick-config-panel glass-effect">
            <h2><i class="fas fa-bolt"></i> Ajuste rápido de riego</h2>
            <form id="formConfigRapida" class="quick-config-form">
                <div class="form-group">
                    <label>Iniciar riego por debajo de</label>
                    <div class="input-group">
                        <input type="number" id="humedad_minima" name="humedad_minima" step="0.01" min="0" max="100" required>
                        <span class="input-suffix">%</span>
                    </div>
                </div>
                <div class="form-group">
                    <label>Detener riego al llegar a</label>
                    <div class="input-group">
                        <input type="number" id="humedad_maxima" name="humedad_maxima" step="0.01" min="0" max="100" required>
                        <span class="input-suffix">%</span>
                    </div>
                </div>
                <div class="form-group">
                    <label>Tiempo máximo de riego</label>
                    <div class="input-group">
                        <input type="number" id="duracion_riego" name="duracion_riego" min="1" required>
                        <span class="input-suffix">min</span>
                    </div>
                </div>
                <button type="submit" class="btn-premium primary">
                    <i class="fas fa-save"></i> Guardar ajuste
                </button>
            </form>
        </div>

        <!-- ══════════════════════════════════════
             Calculadora de riego por m²
             - El usuario solo ingresa los m²
             - Caudal fijo : 1 L/min
             - Consumo fijo: 4 L/m² (riego moderado)
        ══════════════════════════════════════ -->
        <div class="glass-effect" style="border-radius:20px; padding:2rem; margin-bottom:1.5rem; border:1px solid rgba(62,107,236,0.15);">
            <h2 style="display:flex; align-items:center; gap:0.5rem; color:var(--primary-dark); margin-bottom:0.5rem;">
                <i class="fas fa-ruler-combined"></i> Calculadora de riego por m²
            </h2>
            <p style="font-size:0.83rem; color:var(--gray); margin-bottom:1.5rem;">
                <i class="fas fa-info-circle"></i>
                Cálculo basado en <strong>4 L/m²</strong> (riego moderado) · Caudal de bomba: <strong>1 L/min</strong>.
            </p>

            <!-- Solo un campo visible: metros cuadrados -->
            <div style="max-width:280px;">
                <label style="font-size:0.82rem; font-weight:600; color:var(--primary-dark); display:block; margin-bottom:0.4rem;">
                    <i class="fas fa-vector-square"></i> Área a regar
                </label>
                <div style="display:flex; align-items:center; gap:0.75rem;">
                    <input type="number" id="calcMetros" min="1" step="0.5"
                           placeholder="Ej: 20"
                           class="input-premium"
                           style="flex:1;"
                           oninput="calcularTiempoRiego()">
                    <span style="font-size:1rem; font-weight:700; color:var(--gray); white-space:nowrap;">m²</span>
                </div>
            </div>

            <!-- Resultado -->
            <div id="calcResultado" style="
                display: none;
                margin-top: 1.75rem;
                background: linear-gradient(135deg, rgba(62,107,236,0.07), rgba(16,185,129,0.07));
                border: 1px solid rgba(62,107,236,0.18);
                border-radius: 16px;
                padding: 1.25rem 1.5rem;
                flex-wrap: wrap;
                gap: 1.5rem;
                align-items: center;
                justify-content: space-around;
            ">
                <div style="text-align:center;">
                    <div style="font-size:0.75rem; color:var(--gray); margin-bottom:0.3rem; text-transform:uppercase; letter-spacing:0.06em;">
                        <i class="fas fa-water"></i> Agua necesaria
                    </div>
                    <div id="calcTotalLitros" style="font-size:1.7rem; font-weight:800; color:var(--primary);">--</div>
                    <div style="font-size:0.78rem; color:var(--gray);">litros</div>
                </div>
                <div style="text-align:center;">
                    <div style="font-size:0.75rem; color:var(--gray); margin-bottom:0.3rem; text-transform:uppercase; letter-spacing:0.06em;">
                        <i class="fas fa-clock"></i> Tiempo estimado
                    </div>
                    <div id="calcTiempoMin" style="font-size:1.7rem; font-weight:800; color:var(--success);">--</div>
                    <div style="font-size:0.78rem; color:var(--gray);">minutos</div>
                </div>
                <div style="text-align:center;">
                    <div style="font-size:0.75rem; color:var(--gray); margin-bottom:0.3rem; text-transform:uppercase; letter-spacing:0.06em;">
                        <i class="fas fa-hourglass-half"></i> Equivalente
                    </div>
                    <div id="calcTiempoHoras" style="font-size:1.7rem; font-weight:800; color:var(--warning);">--</div>
                    <div style="font-size:0.78rem; color:var(--gray);">horas y minutos</div>
                </div>
            </div>

            <!-- Aviso -->
            <p id="calcAviso" style="display:none; margin-top:1rem; font-size:0.83rem; color:var(--gray);">
                <i class="fas fa-exclamation-circle"></i> Ingresa un valor mayor a 0 para calcular.
            </p>
        </div>
        <!-- ══════════════════════════════════════ -->

        <!-- Alertas -->
        <div class="alert-panel glass-effect">
            <h2><i class="fas fa-exclamation-triangle"></i> Alertas del sistema</h2>
            <div id="alertasBox" class="alerts-container">
                <div class="alert-item info">
                    <i class="fas fa-info-circle"></i>
                    <span>Cargando alertas...</span>
                </div>
            </div>
        </div>

    </main>

    <script src="assets/js/app.js?v=<?php echo time(); ?>"></script>

    <script>
        /* ─── Sidebar ─── */
        document.getElementById('menuToggle').addEventListener('click', function () {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('mainContent').classList.toggle('expanded');
        });

        /* ─── User dropdown ─── */
        const userMenuTrigger = document.getElementById('userMenuTrigger');
        const userDropdown    = document.getElementById('userDropdown');
        userMenuTrigger.addEventListener('click', e => { e.stopPropagation(); userDropdown.classList.toggle('show'); });
        document.addEventListener('click', () => userDropdown.classList.remove('show'));
        userDropdown.addEventListener('click', e => e.stopPropagation());

        /* ─── Notificaciones cada 30s ─── */
        function actualizarNotificaciones() {
            fetch('api/obtener_estado.php?dispositivo_id=' + DISPOSITIVO_ID)
                .then(res => res.json())
                .then(data => {
                    if (data.ok && data.alertas) {
                        const noLeidas = data.alertas.filter(a => !a.leida).length;
                        document.getElementById('notificacionBadge').textContent = noLeidas;
                    }
                });
        }
        setInterval(actualizarNotificaciones, 30000);

        /* ════════════════════════════════════════
           TEMPORIZADOR Y BOTONES INTELIGENTES
        ════════════════════════════════════════ */
        let timerInterval = null;
        let timerTotal    = 0;
        let timerRestante = 0;

        const CAUDAL_DEFAULT = 2;
        const LITROS_POR_M2  = 2;

        function recalcularTiempoConfig() {
            const metros = parseFloat(document.getElementById('metrosTerreno').value);
            const badge  = document.getElementById('tiempoAutoBadge');
            const texto  = document.getElementById('tiempoAutoTexto');
            if (!metros || metros <= 0) { badge.style.display = 'none'; return; }
            const minutos = Math.ceil((metros * LITROS_POR_M2) / CAUDAL_DEFAULT);
            const h = Math.floor(minutos / 60), m = minutos % 60;
            texto.textContent   = h > 0 ? `${h}h ${m}min` : `${minutos} min`;
            badge.style.display = 'inline-flex';
        }

        function obtenerMinutosRiego() {
            const metros = parseFloat(document.getElementById('metrosTerreno').value);
            if (metros && metros > 0) return Math.ceil((metros * LITROS_POR_M2) / CAUDAL_DEFAULT);
            const dur = parseFloat(document.getElementById('configDuracion').textContent);
            return (!isNaN(dur) && dur > 0) ? dur : 10;
        }

        function iniciarTimer(minutos) {
            if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
            timerTotal    = minutos * 60;
            timerRestante = timerTotal;

            const timer    = document.getElementById('riegoTimer');
            const label    = document.getElementById('timerLabel');
            const countdown= document.getElementById('timerCountdown');
            const progress = document.getElementById('timerProgressBar');

            timer.classList.add('activo');
            label.textContent = `Apagado automático en: (${minutos} min total)`;

            function actualizar() {
                const m = Math.floor(timerRestante / 60);
                const s = timerRestante % 60;
                countdown.textContent = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
                progress.style.width  = ((timerRestante / timerTotal) * 100) + '%';
            }
            actualizar();

            timerInterval = setInterval(() => {
                timerRestante--;
                actualizar();
                if (timerRestante <= 0) {
                    clearInterval(timerInterval); timerInterval = null;
                    enviarComando('apagar');
                    timer.classList.remove('activo');
                    mostrarMensajeLocal('ok', `Riego completado. Bomba apagada después de ${minutos} min.`);
                }
            }, 1000);
        }

        async function cambiarManualYEncender() {
            mostrarMensajeLocal('info', 'Cambiando a modo manual...');
            await enviarComando('manual');
            setTimeout(() => {
                const minutos = obtenerMinutosRiego();
                const metros  = parseFloat(document.getElementById('metrosTerreno').value);
                const msg = metros && metros > 0
                    ? `Encendiendo bomba por ${minutos} min (${metros} m²)...`
                    : `Encendiendo bomba por ${minutos} min...`;
                mostrarMensajeLocal('info', msg);
                enviarComando('encender');
                iniciarTimer(minutos);
            }, 700);
        }

        async function cambiarManualYApagar() {
            mostrarMensajeLocal('info', 'Apagando bomba...');
            await enviarComando('manual');
            setTimeout(() => {
                if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
                document.getElementById('riegoTimer').classList.remove('activo');
                enviarComando('apagar');
            }, 700);
        }

        function mostrarMensajeLocal(tipo, texto) {
            if (typeof mostrarMensaje === 'function') {
                mostrarMensaje(tipo, texto);
                return;
            }
            const box = document.getElementById('mensajeAccion');
            if (!box) return;
            const clase = tipo === 'ok' ? 'msg-ok' : tipo === 'error' ? 'msg-error' : 'msg-info';
            const icono = tipo === 'ok' ? 'fa-check-circle' : tipo === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
            box.innerHTML = `<div class="${clase}"><i class="fas ${icono}"></i> ${texto}</div>`;
            setTimeout(() => { box.innerHTML = ''; }, 6000);
        }

        /* ─── Calculadora de riego por m²
               Consumo fijo : 4 L/m² (riego moderado)
               Caudal fijo  : 1 L/min
        ─── */
        const CALC_LITROS_POR_M2 = 4;
        const CALC_CAUDAL_LMIN   = 1;

        function calcularTiempoRiego() {
            const metros    = parseFloat(document.getElementById('calcMetros').value);
            const resultado = document.getElementById('calcResultado');
            const aviso     = document.getElementById('calcAviso');
            const campo     = document.getElementById('calcMetros');

            if (!metros || metros <= 0) {
                resultado.style.display = 'none';
                aviso.style.display     = campo.value !== '' ? 'block' : 'none';
                return;
            }

            const totalLitros = metros * CALC_LITROS_POR_M2;
            const tiempoMin   = totalLitros / CALC_CAUDAL_LMIN;
            const horas       = Math.floor(tiempoMin / 60);
            const minutos     = Math.round(tiempoMin % 60);
            const tiempoTexto = horas > 0 ? `${horas}h ${minutos}min` : `${minutos}min`;

            document.getElementById('calcTotalLitros').textContent = totalLitros.toFixed(0);
            document.getElementById('calcTiempoMin').textContent   = tiempoMin.toFixed(1);
            document.getElementById('calcTiempoHoras').textContent = tiempoTexto;

            aviso.style.display     = 'none';
            resultado.style.display = 'flex';
        }
    </script>

</body>
</html>