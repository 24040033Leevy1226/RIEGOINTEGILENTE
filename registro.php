<?php
session_start();
if (isset($_SESSION['usuario_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>HydroHawk - Registro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/re.css">
    <style>
        .alerta-reg {
            display: none;
            align-items: center;
            gap: 0.6rem;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            margin-bottom: 1rem;
            animation: slideIn 0.25s ease;
        }
        .alerta-reg.show  { display: flex; }
        .alerta-reg.error { background:#fee2e2; border:1px solid #f87171; border-left:4px solid #ef4444; color:#991b1b; }
        .alerta-reg.ok    { background:#d1fae5; border:1px solid #6ee7b7; border-left:4px solid #10b981; color:#065f46; }
        .alerta-reg.warn  { background:#fff3cd; border:1px solid #f0c040; border-left:4px solid #f59e0b; color:#92400e; }
        @keyframes slideIn {
            from { opacity:0; transform:translateY(-8px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .input-error input { border-color: #ef4444 !important; }
    </style>
</head>
<body class="register-body">
    <div class="register-container">
        <div class="register-card">
            <div class="brand-top">HydroHawk</div>

            <div class="logo-wrap">
                <img src="assets/img/hydro_logo.png" alt="HydroHawk" class="logo-main">
            </div>

            <h1>Crear cuenta</h1>
            <p class="register-subtitle">Regístrate para acceder a tu sistema de riego inteligente</p>

            <?php if (isset($_GET["ok"]) && $_GET["ok"] == "1"): ?>
                <div class="alerta-reg ok show">
                    <span>✅</span>
                    <span>Usuario registrado correctamente. Ahora puedes <a href="index.php">iniciar sesión</a>.</span>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET["error"])): ?>
                <div class="alerta-reg error show">
                    <span>❌</span>
                    <span>
                    <?php
                        if ($_GET["error"] === "campos")   echo "Completa todos los campos obligatorios.";
                        elseif ($_GET["error"] === "correo")   echo "Ese correo ya está registrado.";
                        elseif ($_GET["error"] === "password") echo "Las contraseñas no coinciden.";
                        else echo "No se pudo completar el registro. Intenta de nuevo.";
                    ?>
                    </span>
                </div>
            <?php endif; ?>

            <!-- Alerta JS -->
            <div class="alerta-reg" id="alertaReg">
                <span id="alertaIco">⚠️</span>
                <span id="alertaTxt"></span>
            </div>

            <!-- novalidate quita las alertas feas del navegador -->
            <form action="api/registro.php" method="POST" class="register-form"
                  novalidate id="formRegistro">

                <div id="grpNombre">
                    <label>Nombre completo</label>
                    <input type="text" name="nombre" id="inNombre" placeholder="Tu nombre completo">
                </div>

                <div id="grpEmail">
                    <label>Correo electrónico</label>
                    <input type="text" name="email" id="inEmail" placeholder="usuario@correo.com" autocomplete="email">
                </div>

                <div id="grpTelefono">
                    <label>Teléfono <span style="font-size:0.78rem;opacity:.6;">(opcional)</span></label>
                    <input type="text" name="telefono" id="inTelefono" placeholder="Ej: 844 820 7043">
                </div>

                <div id="grpPass">
                    <label>Contraseña</label>
                    <input type="password" name="password" id="inPass" placeholder="Mínimo 6 caracteres">
                </div>

                <div id="grpConfirm">
                    <label>Confirmar contraseña</label>
                    <input type="password" name="confirmar_password" id="inConfirm" placeholder="Repite tu contraseña">
                </div>

                <button type="submit">Registrarme</button>
            </form>

            <div class="bottom-link">
                ¿Ya tienes cuenta? <a href="index.php">Inicia sesión</a>
            </div>
        </div>
    </div>

    <script>
        const form      = document.getElementById('formRegistro');
        const alerta    = document.getElementById('alertaReg');
        const alertaTxt = document.getElementById('alertaTxt');
        const alertaIco = document.getElementById('alertaIco');

        const inNombre  = document.getElementById('inNombre');
        const inEmail   = document.getElementById('inEmail');
        const inPass    = document.getElementById('inPass');
        const inConfirm = document.getElementById('inConfirm');

        function mostrarAlerta(msg, tipo = 'warn') {
            alerta.className = 'alerta-reg show ' + tipo;
            alertaIco.textContent = tipo === 'error' ? '❌' : tipo === 'ok' ? '✅' : '⚠️';
            alertaTxt.textContent = msg;
        }

        function limpiarAlerta() {
            alerta.classList.remove('show', 'error', 'ok', 'warn');
            [inNombre, inEmail, inPass, inConfirm].forEach(i => i.style.borderColor = '');
        }

        function marcarError(input) {
            input.style.borderColor = '#ef4444';
            input.focus();
        }

        function esEmailValido(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        form.addEventListener('submit', function (e) {
            limpiarAlerta();

            const nombre  = inNombre.value.trim();
            const email   = inEmail.value.trim();
            const pass    = inPass.value;
            const confirm = inConfirm.value;

            if (!nombre) {
                e.preventDefault();
                marcarError(inNombre);
                mostrarAlerta('Ingresa tu nombre completo.', 'error');
                return;
            }

            if (!email) {
                e.preventDefault();
                marcarError(inEmail);
                mostrarAlerta('Ingresa tu correo electrónico.', 'error');
                return;
            }

            if (!esEmailValido(email)) {
                e.preventDefault();
                marcarError(inEmail);
                mostrarAlerta('El correo no tiene un formato válido. Ej: Nombre@gmail.com', 'error');
                return;
            }

            if (!pass) {
                e.preventDefault();
                marcarError(inPass);
                mostrarAlerta('Ingresa una contraseña.', 'error');
                return;
            }

            if (pass.length < 6) {
                e.preventDefault();
                marcarError(inPass);
                mostrarAlerta('La contraseña debe tener al menos 6 caracteres.', 'error');
                return;
            }

            if (pass !== confirm) {
                e.preventDefault();
                marcarError(inConfirm);
                mostrarAlerta('Las contraseñas no coinciden.', 'error');
                return;
            }
        });

        // Limpiar error al escribir
        [inNombre, inEmail, inPass, inConfirm].forEach(i => i.addEventListener('input', limpiarAlerta));
    </script>
</body>
</html>