<?php
require_once("includes/auth.php");
require_once("includes/conexion.php");

// ── PHPMailer (instalar con: composer require phpmailer/phpmailer) ──
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once("vendor/autoload.php");

// ════════════════════════════════════════
// CONFIGURACIÓN SMTP — edita solo estas líneas
// ════════════════════════════════════════
define('SMTP_USER', 'hydrohawkhawk@gmail.com');   // tu correo Gmail
define('SMTP_PASS', 'kvha jyhq duja zxod'); // contraseña de aplicación (16 caracteres)
define('SMTP_FROM', 'hydrohawkhawk@gmail.com');   // remitente
define('SMTP_NAME', 'HydroHawk Soporte');          // nombre que aparece como remitente
define('SOPORTE_TO', 'hydrohawkhawk@gmail.com');  // a quién llegan los tickets
// ════════════════════════════════════════

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

// ── Procesar envío de ticket ──
$mensaje_enviado = false;
$error_mensaje   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_ticket'])) {
    $asunto    = trim($_POST['asunto']    ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $mensaje   = trim($_POST['mensaje']   ?? '');
    $prioridad = trim($_POST['prioridad'] ?? 'normal');

    if (empty($asunto) || empty($mensaje)) {
        $error_mensaje = 'Por favor completa todos los campos obligatorios.';
    } else {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(SMTP_FROM, SMTP_NAME);
            $mail->addAddress(SOPORTE_TO);
            $mail->addReplyTo($usuario['email'], $usuario['nombre']);

            $mail->Subject = '[HydroHawk Ticket] ' . $asunto;
            $mail->Body    =
"Nuevo ticket de soporte
========================
Nombre     : {$usuario['nombre']}
Email      : {$usuario['email']}
Dispositivo: {$dispositivo['nombre_dispositivo']}
Categoría  : {$categoria}
Prioridad  : {$prioridad}

Mensaje:
{$mensaje}";

            if (!empty($_FILES['adjunto']['tmp_name'])) {
                $mail->addAttachment($_FILES['adjunto']['tmp_name'], $_FILES['adjunto']['name']);
            }

            $mail->send();
            $mensaje_enviado = true;

        } catch (Exception $e) {
            $error_mensaje = 'Error al enviar el ticket: ' . $mail->ErrorInfo;
        }
    }
}

// ── Procesar modal email ──
$modal_enviado = false;
$modal_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_modal_email'])) {
    $asunto    = trim($_POST['modal_asunto']    ?? '');
    $categoria = trim($_POST['modal_categoria'] ?? '');
    $mensaje   = trim($_POST['modal_mensaje']   ?? '');

    if (empty($asunto) || empty($mensaje)) {
        $modal_error = 'Por favor completa el asunto y el mensaje.';
    } else {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(SMTP_FROM, SMTP_NAME);
            $mail->addAddress(SOPORTE_TO);
            $mail->addReplyTo($usuario['email'], $usuario['nombre']);

            $catTxt = $categoria ? "Categoría  : {$categoria}\n" : '';

            $mail->Subject = '[HydroHawk Soporte] ' . $asunto;
            $mail->Body    =
"Estimado equipo de soporte HydroHawk,

Mi nombre es {$usuario['nombre']} ({$usuario['email']}).
{$catTxt}Dispositivo: {$dispositivo['nombre_dispositivo']}

{$mensaje}

Quedo en espera de su respuesta.

Saludos cordiales,
{$usuario['nombre']}";

            $mail->send();
            $modal_enviado = true;

        } catch (Exception $e) {
            $modal_error = 'Error al enviar: ' . $mail->ErrorInfo;
        }
    }
}

// Preguntas frecuentes
$faqs = [
    ['pregunta' => '¿Cómo configuro los límites de humedad?',       'respuesta' => 'Ve a Configuración > Límites de humedad. Puedes ajustar los valores mínimo y máximo según las necesidades de tus plantas.',                                                             'categoria' => 'configuracion'],
    ['pregunta' => '¿Qué hago si la bomba no enciende?',            'respuesta' => 'Verifica que el dispositivo esté conectado a la corriente y que la válvula de agua esté abierta. Si el problema persiste, contacta a soporte técnico.',                                  'categoria' => 'problemas'],
    ['pregunta' => '¿Cómo interpreto las gráficas de humedad?',     'respuesta' => 'Las gráficas muestran la tendencia de humedad en el tiempo. La línea azul indica la humedad actual y las líneas punteadas muestran los límites configurados.',                          'categoria' => 'reportes'],
    ['pregunta' => '¿Puedo controlar el sistema desde mi teléfono?','respuesta' => 'Sí, HydroHawk es completamente responsive. Puedes acceder desde cualquier dispositivo con conexión a internet.',                                                                        'categoria' => 'general'],
    ['pregunta' => '¿Cómo restablezco mi contraseña?',              'respuesta' => 'En la pantalla de login, haz clic en "¿Olvidaste tu contraseña?" y sigue las instrucciones para restablecerla.',                                                                         'categoria' => 'cuenta'],
    ['pregunta' => '¿Qué significan los colores en el dashboard?',  'respuesta' => 'Verde: óptimo, Amarillo: precaución, Rojo: alerta. Los rangos se configuran en la sección de Configuración.',                                                                             'categoria' => 'general'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Soporte Técnico - HydroHawk</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/sup.css">

    <style>
        #modalEmail { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:9999; align-items:center; justify-content:center; padding:1rem; }
        #modalEmail.open { display:flex; }
        .modal-email-box { background:#fff; border-radius:20px; padding:2.5rem; width:100%; max-width:560px; max-height:90vh; overflow-y:auto; box-shadow:0 24px 64px rgba(0,0,0,0.25); animation:modalIn .22s ease; }
        @keyframes modalIn { from{opacity:0;transform:translateY(18px) scale(.98)} to{opacity:1;transform:translateY(0) scale(1)} }
        .modal-email-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; }
        .modal-email-title  { display:flex; align-items:center; gap:.75rem; }
        .modal-email-icon   { width:44px; height:44px; border-radius:12px; background:rgba(16,185,129,.12); color:#10b981; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
        .modal-email-title h3 { margin:0; font-size:1.05rem; color:var(--primary-dark,#1e3a8a); }
        .modal-email-title small { display:block; color:var(--gray,#6b7280); font-size:.8rem; margin-top:2px; }
        .modal-close-btn { background:none; border:none; font-size:1.5rem; line-height:1; cursor:pointer; color:var(--gray,#6b7280); padding:.2rem .5rem; border-radius:8px; transition:background .15s; }
        .modal-close-btn:hover { background:#f3f4f6; }
        .modal-email-from { background:#f8fafc; border-left:3px solid #10b981; border-radius:10px; padding:.75rem 1rem; margin-bottom:1.5rem; font-size:.85rem; color:#374151; }
        .modal-email-fields { display:flex; flex-direction:column; gap:1rem; }
        .modal-email-fields label { font-size:.82rem; font-weight:600; color:var(--primary-dark,#1e3a8a); display:block; margin-bottom:.4rem; }
        .modal-email-fields label i { font-size:.78rem; margin-right:4px; }
        .modal-email-fields .input-premium,
        .modal-email-fields .select-premium { width:100%; box-sizing:border-box; }
        .modal-email-fields textarea.input-premium { resize:vertical; min-height:130px; }
        .modal-email-actions { display:flex; gap:.75rem; justify-content:flex-end; margin-top:1.75rem; }
        .btn-loading { opacity:.7; pointer-events:none; }
    </style>
</head>
<body class="dashboard-body">

    <!-- ══ MODAL — Soporte por email ══ -->
    <div id="modalEmail" role="dialog" aria-modal="true">
        <div class="modal-email-box">
            <div class="modal-email-header">
                <div class="modal-email-title">
                    <div class="modal-email-icon"><i class="fas fa-envelope"></i></div>
                    <div>
                        <h3>Nuevo mensaje de soporte</h3>
                        <small>Para: <?php echo SOPORTE_TO; ?></small>
                    </div>
                </div>
                <button class="modal-close-btn" onclick="cerrarModalEmail()" title="Cerrar">&times;</button>
            </div>

            <div class="modal-email-from">
                <strong>De:</strong>
                <?php echo htmlspecialchars($usuario['nombre']); ?>
                &lt;<?php echo htmlspecialchars($usuario['email']); ?>&gt;
            </div>

            <?php if ($modal_enviado): ?>
                <div class="msg-ok" style="margin-bottom:1.5rem;">
                    <i class="fas fa-check-circle"></i> Mensaje enviado correctamente. Te responderemos pronto.
                </div>
            <?php endif; ?>
            <?php if ($modal_error): ?>
                <div class="msg-error" style="margin-bottom:1.5rem;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($modal_error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="formModalEmail">
                <input type="hidden" name="form_modal_email" value="1">
                <div class="modal-email-fields">
                    <div>
                        <label for="modal_asunto"><i class="fas fa-tag"></i> Asunto <span style="color:#e53e3e;">*</span></label>
                        <input type="text" id="modal_asunto" name="modal_asunto" class="input-premium"
                               placeholder="Ej: Problema con sensor de humedad"
                               value="<?php echo htmlspecialchars($_POST['modal_asunto'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="modal_categoria"><i class="fas fa-folder"></i> Categoría</label>
                        <select id="modal_categoria" name="modal_categoria" class="select-premium">
                            <option value="">Selecciona una categoría</option>
                            <?php
                            $cats = ['Problema técnico','Ayuda con configuración','Facturación','Problemas de cuenta','Otro'];
                            foreach ($cats as $c):
                                $sel = (($_POST['modal_categoria'] ?? '') === $c) ? 'selected' : '';
                            ?>
                            <option value="<?php echo $c; ?>" <?php echo $sel; ?>><?php echo $c; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="modal_mensaje"><i class="fas fa-align-left"></i> Mensaje <span style="color:#e53e3e;">*</span></label>
                        <textarea id="modal_mensaje" name="modal_mensaje" class="input-premium"
                                  placeholder="Describe tu problema con el mayor detalle posible..."><?php echo htmlspecialchars($_POST['modal_mensaje'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-email-actions">
                    <button type="button" class="btn-premium secondary" onclick="cerrarModalEmail()">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn-premium primary" id="btnEnviarModal">
                        <i class="fas fa-paper-plane"></i> Enviar mensaje
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- ══════════════════════════════ -->

    <!-- Navbar -->
    <nav class="navbar-premium">
        <div class="nav-left">
            <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
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

    <!-- Sidebar -->
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
            <a href="dashboard.php"     class="menu-item"><i class="fas fa-chart-pie"></i><span>Dashboard</span></a>
            <a href="configuracion.php" class="menu-item"><i class="fas fa-sliders-h"></i><span>Configuración</span></a>
            <a href="historial.php"     class="menu-item"><i class="fas fa-history"></i><span>Historial</span></a>
            <a href="reportes.php"      class="menu-item"><i class="fas fa-file-alt"></i><span>Reportes</span></a>
            <a href="analisis.php"      class="menu-item"><i class="fas fa-brain"></i><span>Análisis IA</span></a>
            <a href="soporte.php"       class="menu-item active"><i class="fas fa-headset"></i><span>Soporte</span></a>
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
                <h1>Centro de Soporte</h1>
                <p><i class="fas fa-headset"></i> Estamos aquí para ayudarte 24/7</p>
            </div>
            <div class="hero-badge">
                <span class="badge-premium"><i class="fas fa-clock"></i> Tiempo promedio: 15min</span>
            </div>
        </div>

        <!-- Contact Cards -->
        <div class="soporte-contacto-grid">
            <div class="contacto-card">
                <div class="contacto-icon" style="background:rgba(62,107,236,.1);color:var(--primary);">
                    <i class="fas fa-phone-alt"></i>
                </div>
                <h3>Atención telefónica</h3>
                <p>Lun-Vie 9:00 - 18:00</p>
                <div class="contacto-valor">+52 8448207043</div>
                <button class="btn-premium primary" style="margin-top:1rem;width:100%;"
                        onclick="window.location.href='tel:+528448207043'">
                    <i class="fas fa-phone"></i> Llamar ahora
                </button>
            </div>

            <div class="contacto-card">
                <div class="contacto-icon" style="background:rgba(16,185,129,.1);color:var(--success);">
                    <i class="fas fa-envelope"></i>
                </div>
                <h3>Soporte por email</h3>
                <p>Respuesta en 24h</p>
                <div class="contacto-valor">hydrohawkhawk@gmail.com</div>
                <button class="btn-premium success" style="margin-top:1rem;width:100%;"
                        onclick="abrirModalEmail()">
                    <i class="fas fa-envelope"></i> Enviar email
                </button>
            </div>

            <div class="contacto-card">
                <div class="contacto-icon" style="background:rgba(245,158,11,.1);color:var(--warning);">
                    <i class="fas fa-comment-dots"></i>
                </div>
                <h3>Chat en vivo</h3>
                <p>Disponible ahora</p>
                <div class="contacto-valor" style="color:var(--success);">
                    <i class="fas fa-circle" style="font-size:.6rem;"></i> En línea
                </div>
                <button class="btn-premium warning" style="margin-top:1rem;width:100%;" onclick="abrirChat()">
                    <i class="fas fa-comment"></i> Iniciar chat
                </button>
            </div>

            <div class="contacto-card">
                <div class="contacto-icon" style="background:rgba(139,92,246,.1);color:var(--purple);">
                    <i class="fab fa-whatsapp"></i>
                </div>
                <h3>WhatsApp</h3>
                <p>Respuesta inmediata</p>
                <div class="contacto-valor">+52 8448207043</div>
                <button class="btn-premium" style="margin-top:1rem;width:100%;background:#25D366;color:white;"
                        onclick="window.location.href='https://wa.me/528448207043'">
                    <i class="fab fa-whatsapp"></i> Enviar WhatsApp
                </button>
            </div>
        </div>

        <!-- Ticket -->
        <div class="ticket-container glass-effect">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;">
                <h2 style="display:flex;align-items:center;gap:.5rem;color:var(--primary-dark);">
                    <i class="fas fa-ticket-alt"></i> Crear nuevo ticket
                </h2>
                <span class="badge-premium" style="background:var(--purple);">
                    <i class="fas fa-clock"></i> Tiempo estimado: 2h
                </span>
            </div>

            <?php if ($mensaje_enviado): ?>
                <div class="msg-ok" style="margin-bottom:1.5rem;">
                    <i class="fas fa-check-circle"></i> Ticket enviado correctamente. Te contactaremos pronto.
                </div>
            <?php endif; ?>
            <?php if ($error_mensaje): ?>
                <div class="msg-error" style="margin-bottom:1.5rem;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_mensaje); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="ticket-form" enctype="multipart/form-data">
                <input type="hidden" name="form_ticket" value="1">
                <div class="form-grid" style="grid-template-columns:repeat(2,1fr);">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Asunto *</label>
                        <input type="text" name="asunto" placeholder="Ej: Problema con la bomba" required class="input-premium">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-folder"></i> Categoría *</label>
                        <select name="categoria" required class="select-premium">
                            <option value="">Selecciona una categoría</option>
                            <option value="Problema técnico">Problema técnico</option>
                            <option value="Ayuda con configuración">Ayuda con configuración</option>
                            <option value="Facturación">Facturación</option>
                            <option value="Problemas de cuenta">Problemas de cuenta</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:span 2;">
                        <label><i class="fas fa-exclamation-triangle"></i> Prioridad</label>
                        <div style="display:flex;gap:1rem;flex-wrap:wrap;">
                            <label class="prioridad-option"><input type="radio" name="prioridad" value="baja" checked><span class="prioridad-label baja"><i class="fas fa-chevron-down"></i> Baja</span></label>
                            <label class="prioridad-option"><input type="radio" name="prioridad" value="normal"><span class="prioridad-label normal"><i class="fas fa-minus"></i> Normal</span></label>
                            <label class="prioridad-option"><input type="radio" name="prioridad" value="alta"><span class="prioridad-label alta"><i class="fas fa-chevron-up"></i> Alta</span></label>
                            <label class="prioridad-option"><input type="radio" name="prioridad" value="urgente"><span class="prioridad-label urgente"><i class="fas fa-exclamation"></i> Urgente</span></label>
                        </div>
                    </div>
                    <div class="form-group" style="grid-column:span 2;">
                        <label><i class="fas fa-align-left"></i> Mensaje *</label>
                        <textarea name="mensaje" rows="5" placeholder="Describe tu problema en detalle..."
                                  required class="input-premium" style="resize:vertical;min-height:120px;"></textarea>
                    </div>
                    <div class="form-group" style="grid-column:span 2;">
                        <label><i class="fas fa-paperclip"></i> Adjuntar archivo (opcional)</label>
                        <input type="file" name="adjunto" class="input-premium" style="padding:.8rem;">
                        <small style="color:var(--gray);">Máx. 10MB — Formatos: jpg, png, pdf</small>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="reset"  class="btn-premium secondary"><i class="fas fa-undo"></i> Limpiar</button>
                    <button type="submit" class="btn-premium primary"><i class="fas fa-paper-plane"></i> Enviar ticket</button>
                </div>
            </form>
        </div>

        <!-- FAQ -->
        <div class="faq-container glass-effect">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;">
                <h2 style="display:flex;align-items:center;gap:.5rem;color:var(--primary-dark);">
                    <i class="fas fa-question-circle"></i> Preguntas frecuentes
                </h2>
                <div class="faq-search">
                    <i class="fas fa-search"></i>
                    <input type="text" id="buscarFaq" placeholder="Buscar pregunta..." class="input-premium" style="padding-left:2.5rem;">
                </div>
            </div>
            <div class="faq-categorias">
                <button class="faq-categoria-btn active" data-categoria="todos">Todos</button>
                <button class="faq-categoria-btn" data-categoria="configuracion">Configuración</button>
                <button class="faq-categoria-btn" data-categoria="problemas">Problemas</button>
                <button class="faq-categoria-btn" data-categoria="reportes">Reportes</button>
                <button class="faq-categoria-btn" data-categoria="general">General</button>
                <button class="faq-categoria-btn" data-categoria="cuenta">Cuenta</button>
            </div>
            <div class="faq-grid" id="faqContainer">
                <?php foreach ($faqs as $index => $faq): ?>
                <div class="faq-item" data-categoria="<?php echo $faq['categoria']; ?>">
                    <div class="faq-pregunta" onclick="toggleFaq(<?php echo $index; ?>)">
                        <span><?php echo $faq['pregunta']; ?></span>
                        <i class="fas fa-chevron-down" id="faq-icon-<?php echo $index; ?>"></i>
                    </div>
                    <div class="faq-respuesta" id="faq-<?php echo $index; ?>">
                        <p><?php echo $faq['respuesta']; ?></p>
                        <div style="margin-top:1rem;">
                            <span class="badge-auto"><?php echo ucfirst($faq['categoria']); ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recursos -->
        <div class="recursos-grid">
            <div class="recurso-card">
                <i class="fas fa-book-open"></i>
                <h3>Manual de usuario</h3>
                <p>Guía completa de HydroHawk</p>
                <button class="btn-premium secondary" style="width:100%;" onclick="window.open('manual.pdf','_blank')">
                    <i class="fas fa-download"></i> Descargar PDF
                </button>
            </div>
            <div class="recurso-card">
                <i class="fas fa-video"></i>
                <h3>Video tutoriales</h3>
                <p>Aprende visualmente</p>
                <button class="btn-premium secondary" style="width:100%;" onclick="window.open('https://youtube.com/hydrohawk','_blank')">
                    <i class="fab fa-youtube"></i> Ver videos
                </button>
            </div>
            <div class="recurso-card">
                <i class="fas fa-bug"></i>
                <h3>Reportar error</h3>
                <p>Ayúdanos a mejorar</p>
                <button class="btn-premium secondary" style="width:100%;" onclick="window.location.href='reportar-error.php'">
                    <i class="fas fa-exclamation-triangle"></i> Reportar
                </button>
            </div>
            <div class="recurso-card">
                <i class="fas fa-sync-alt"></i>
                <h3>Estado del sistema</h3>
                <p>Verifica el funcionamiento</p>
                <div style="display:flex;align-items:center;gap:.5rem;justify-content:center;margin:.5rem 0;">
                    <i class="fas fa-circle" style="color:var(--success);font-size:.6rem;"></i>
                    <span>Todos los sistemas operativos</span>
                </div>
            </div>
        </div>

    </main>

    <script>
        /* Sidebar */
        document.getElementById('menuToggle').addEventListener('click', function () {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('mainContent').classList.toggle('expanded');
        });

        /* Dropdown usuario */
        const userMenuTrigger = document.getElementById('userMenuTrigger');
        const userDropdown    = document.getElementById('userDropdown');
        userMenuTrigger.addEventListener('click', e => { e.stopPropagation(); userDropdown.classList.toggle('show'); });
        document.addEventListener('click', () => userDropdown.classList.remove('show'));
        userDropdown.addEventListener('click', e => e.stopPropagation());

        /* Chat */
        function abrirChat() { alert('🧑‍💻 Conectando con un agente...\nPor favor espera un momento.'); }

        /* FAQ */
        function toggleFaq(index) {
            const r = document.getElementById(`faq-${index}`);
            const i = document.getElementById(`faq-icon-${index}`);
            const open = r.style.display === 'block';
            r.style.display   = open ? 'none'         : 'block';
            i.style.transform = open ? 'rotate(0deg)' : 'rotate(180deg)';
        }
        document.querySelectorAll('.faq-categoria-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.faq-categoria-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const cat = this.dataset.categoria;
                document.querySelectorAll('.faq-item').forEach(f => {
                    f.style.display = (cat === 'todos' || f.dataset.categoria === cat) ? 'block' : 'none';
                });
            });
        });
        document.getElementById('buscarFaq').addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('.faq-item').forEach(f => {
                f.style.display = f.querySelector('.faq-pregunta span').textContent.toLowerCase().includes(q) ? 'block' : 'none';
            });
        });

        /* Modal Email */
        const modalEmail = document.getElementById('modalEmail');

        function abrirModalEmail() {
            modalEmail.classList.add('open');
            document.body.style.overflow = 'hidden';
            document.getElementById('modal_asunto').focus();
        }
        function cerrarModalEmail() {
            modalEmail.classList.remove('open');
            document.body.style.overflow = '';
        }
        modalEmail.addEventListener('click', e => { if (e.target === modalEmail) cerrarModalEmail(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && modalEmail.classList.contains('open')) cerrarModalEmail(); });

        /* Mostrar modal si el servidor respondió (éxito o error) */
        <?php if ($modal_enviado || $modal_error): ?>
        document.addEventListener('DOMContentLoaded', () => abrirModalEmail());
        <?php endif; ?>

        /* Spinner al enviar */
        document.getElementById('formModalEmail').addEventListener('submit', function () {
            const btn = document.getElementById('btnEnviarModal');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
            btn.classList.add('btn-loading');
        });
    </script>

</body>
</html>

