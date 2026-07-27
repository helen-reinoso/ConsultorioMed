<?php
/**
 * session.php
 * Configuración segura de sesiones y cookies (httponly, samesite, etc).
 * Debe incluirse ANTES de cualquier salida y ANTES de session_start().
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 4, // 4 horas
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']), // true si se sirve por HTTPS
        'httponly' => true,        // JS no puede leer la cookie de sesión
        'samesite' => 'Lax',
    ]);
    session_name('CONSULTORIO_SESSION');
    session_start();
}

/**
 * Helpers de autenticación / autorización basados en sesión.
 */
function usuarioAutenticado(): bool
{
    return isset($_SESSION['id_usuario']);
}

function usuarioActual(): ?array
{
    if (!usuarioAutenticado()) {
        return null;
    }
    return [
        'id_usuario' => $_SESSION['id_usuario'],
        'nombre'     => $_SESSION['nombre'],
        'email'      => $_SESSION['email'],
        'rol'        => $_SESSION['rol'],
        'id_paciente'=> $_SESSION['id_paciente'] ?? null,
        'id_medico'  => $_SESSION['id_medico'] ?? null,
    ];
}

function requerirRol(string ...$rolesPermitidos): void
{
    if (!usuarioAutenticado() || !in_array($_SESSION['rol'], $rolesPermitidos, true)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['exito' => false, 'mensaje' => 'Acceso no autorizado para su rol.']);
        exit;
    }
}

/** Token CSRF simple para formularios */
function generarTokenCSRF(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validarTokenCSRF(?string $token): bool
{
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
