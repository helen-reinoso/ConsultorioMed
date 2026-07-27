<?php
require_once __DIR__ . '/config/session.php';

if (usuarioAutenticado()) {
    $destino = match ($_SESSION['rol']) {
        'admin' => 'admin/dashboard.php',
        'medico' => 'medico/dashboard.php',
        default => 'paciente/dashboard.php',
    };
    header('Location: views/' . $destino);
} else {
    header('Location: views/auth/login.php');
}
exit;
