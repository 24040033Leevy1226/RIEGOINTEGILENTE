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
    <title>HydroHawk - Iniciar sesión</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/ind.css">
    <style>
        .alerta-login {
            display: none;
            align-items: center;
            gap: 0.6rem;
            background: #fff3cd;
            border: 1px solid #f0c040;
            border-left: 4px solid #f59e0b;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            color: #92400e;
            margin-bottom: 1rem;
            animation: slideIn 0.25s ease;
        }
        .alerta-login.error {
            background: #fee2e2;
            border-color: #f87171;
            border-left-color: #ef4444;
            color: #991b1b;
        }
        .alerta-login.ok {
            background: #d1fae5;
            border-color: #6ee7b7;
            border-left-color: #10b981;
            color: #065f46;
        }
        .alerta-login.show { display: flex; }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .input-error { border-color: #ef4444 !important; }
    </style>
</head>
<body class="login-body">

<div class="login-container">
    <div class="login-card">

        <div class="brand-top">HydroHawk</div>

        <div class="logo-wrap">
            <img src="assets/img/hydro_logo.png" alt="HydroHawk" class="logo-main">
        </div>

        <h1>HydroHawk<br>Riego Inteligente</h1>
        <p class="subtitle">"Volamos alto, Regamos inteligente"</p>

        <?php if (isset($_GET["error"])): ?>
            <div class="alerta-login error show">
                <span>⚠️</span>
                <span>
                <?php
                    if ($_GET["error"] === "credenciales") echo "Correo o contraseña incorrectos.";
                    elseif ($_GET["error"] === "campos")    echo "Por favor completa todos los campos.";
                    else echo "Error al iniciar sesión. Intenta de nuevo.";
                ?>
                </span>
            </div>
        <?php endif; ?>

        <!-- Alerta JS (validación bonita) -->
        <div class="alerta-login" id="alertaLogin">
            <span id="alertaIcono">⚠️</span>
            <span id="alertaTexto"></span>
        </div>

        <!-- novalidate desactiva las alertas feas del navegador -->
        <form action="api/login.php" method="POST" class="login-form"
              novalidate id="formLogin">

            <input type="text" name="email" id="inputEmail"
                   placeholder="Correo electrónico" autocomplete="email">

            <input type="password" name="password" id="inputPassword"
                   placeholder="Contraseña">

            <button type="submit">Iniciar sesión</button>
        </form>

        <div class="login-footer">
            ¿No tienes cuenta? <a href="registro.php">Crear cuenta</a>
        </div>

    </div>
</div>

<script>
    const form     = document.getElementById('formLogin');
    const alerta   = document.getElementById('alertaLogin');
    const alertaTxt = document.getElementById('alertaTexto');
    const alertaIco = document.getElementById('alertaIcono');
    const inputEmail = document.getElementById('inputEmail');
    const inputPass  = document.getElementById('inputPassword');

    function mostrarAlerta(msg, tipo = 'warn') {
        alerta.className = 'alerta-login show' + (tipo === 'error' ? ' error' : tipo === 'ok' ? ' ok' : '');
        alertaIco.textContent = tipo === 'error' ? '❌' : tipo === 'ok' ? '✅' : '⚠️';
        alertaTxt.textContent = msg;
    }

    function limpiarAlerta() {
        alerta.classList.remove('show', 'error', 'ok');
        inputEmail.classList.remove('input-error');
        inputPass.classList.remove('input-error');
    }

    function esEmailValido(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    form.addEventListener('submit', function (e) {
        limpiarAlerta();

        const email = inputEmail.value.trim();
        const pass  = inputPass.value.trim();

        if (!email && !pass) {
            e.preventDefault();
            inputEmail.classList.add('input-error');
            inputPass.classList.add('input-error');
            mostrarAlerta('Por favor ingresa tu correo y contraseña.', 'error');
            return;
        }

        if (!email) {
            e.preventDefault();
            inputEmail.classList.add('input-error');
            mostrarAlerta('Ingresa tu correo electrónico.', 'error');
            return;
        }

        if (!esEmailValido(email)) {
            e.preventDefault();
            inputEmail.classList.add('input-error');
            mostrarAlerta('El correo no tiene un formato válido. Ej: HydroHawk@gmail.com', 'error');
            return;
        }

        if (!pass) {
            e.preventDefault();
            inputPass.classList.add('input-error');
            mostrarAlerta('Ingresa tu contraseña.', 'error');
            return;
        }
    });

    // Limpiar error al escribir
    inputEmail.addEventListener('input', limpiarAlerta);
    inputPass.addEventListener('input',  limpiarAlerta);
</script>

</body>
</html>