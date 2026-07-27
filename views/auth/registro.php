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

$errores = [];
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $errores['general'] = 'Token de seguridad inválido. Intente nuevamente.';
    } else {
        $auth = new AuthController();
        $res = $auth->registrarPaciente($_POST);
        if ($res['exito']) {
            $exito = true;
        } else {
            $errores = $res['errores'];
        }
    }
}

$csrf = generarTokenCSRF();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Crear cuenta · Consultorio Médico</title>
<link rel="stylesheet" href="../../public/css/futuristic.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card panel" style="max-width:460px;">
        <div class="auth-logo">
            <div class="dot"></div>
            <h2>Crear cuenta de paciente</h2>
        </div>

        <?php if ($exito): ?>
            <div class="alert alert-success">Registro exitoso. <a href="login.php">Inicie sesión aquí</a>.</div>
        <?php else: ?>
            <?php if (!empty($errores['general'])): ?>
                <div class="alert alert-error"><?= htmlspecialchars($errores['general']) ?></div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <label for="nombre">Nombre completo</label>
                <input type="text" id="nombre" name="nombre" required value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
                <?php if (!empty($errores['nombre'])): ?><small style="color:var(--danger)"><?= htmlspecialchars($errores['nombre']) ?></small><?php endif; ?>

                <label for="email">Correo electrónico</label>
                <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                <?php if (!empty($errores['email'])): ?><small style="color:var(--danger)"><?= htmlspecialchars($errores['email']) ?></small><?php endif; ?>

                <label for="telefono">Teléfono</label>
                <input type="text" id="telefono" name="telefono" placeholder="6000-0000" value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">

                <label for="fecha_nacimiento">Fecha de nacimiento</label>
                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?= htmlspecialchars($_POST['fecha_nacimiento'] ?? '') ?>">

                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required placeholder="Mínimo 8 caracteres, 1 mayúscula, 1 número">
                <?php if (!empty($errores['password'])): ?><small style="color:var(--danger)"><?= htmlspecialchars($errores['password']) ?></small><?php endif; ?>

                <button type="submit" class="btn btn-full" style="margin-top:20px;">Registrarme</button>
            </form>
        <?php endif; ?>

        <p style="text-align:center;margin-top:20px;font-size:0.85rem;color:var(--text-muted);">
            ¿Ya tiene cuenta? <a href="login.php">Inicie sesión</a>
        </p>
    </div>
</div>
</body>
</html>
