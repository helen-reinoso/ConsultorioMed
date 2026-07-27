<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../controllers/AuthController.php';

if (usuarioAutenticado()) {
    $destino = match ($_SESSION['rol']) {
        'admin' => '../admin/dashboard.php',
        'medico' => '../medico/dashboard.php',
        default => '../paciente/dashboard.php',
    };
    header('Location: ' . $destino);
    exit;
}

$error = null;
$mensajeExito = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $error = 'Token de seguridad inválido. Intente nuevamente.';
    } else {
        $auth = new AuthController();
        $res = $auth->iniciarSesion($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($res['exito']) {
            $destino = match ($_SESSION['rol']) {
        'admin' => '../admin/dashboard.php',
        'medico' => '../medico/dashboard.php',
        default => '../paciente/dashboard.php',
    };
    header('Location: ' . $destino);
            exit;
        }
        $error = $res['mensaje'];
    }
}

$csrf = generarTokenCSRF();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar sesión · Consultorio Médico</title>
<link rel="stylesheet" href="../../public/css/futuristic.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card panel">
        <div class="auth-logo">
            <div class="dot"></div>
            <h2>Consultorio<span style="color:var(--accent-cyan)">Med</span></h2>
            <p style="color:var(--text-muted);font-size:0.85rem;margin-top:4px;">Sistema de reservas médicas en tiempo real</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" required placeholder="nombre@correo.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required placeholder="••••••••">

            <button type="submit" class="btn btn-full" style="margin-top:20px;">Iniciar sesión</button>
        </form>

        <p style="text-align:center;margin-top:20px;font-size:0.85rem;color:var(--text-muted);">
            ¿No tiene cuenta? <a href="registro.php">Regístrese como paciente</a>
        </p>

        <div class="panel" style="margin-top:24px;background:rgba(0,229,199,0.04);border-style:dashed;">
            <p style="font-family:var(--font-mono);font-size:0.72rem;color:var(--text-muted);margin:0 0 6px;">CREDENCIALES DE PRUEBA</p>
            <p style="font-size:0.8rem;margin:2px 0;">Admin: <b>admin@consultorio.com</b></p>
            <p style="font-size:0.8rem;margin:2px 0;">Paciente: <b>ana.torres@mail.com</b></p>
            <p style="font-size:0.8rem;margin:2px 0;">Contraseña: <b>Password123!</b></p>
        </div>
    </div>
</div>
</body>
</html>
