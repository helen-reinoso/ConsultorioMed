<?php
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Paciente.php';
require_once __DIR__ . '/../models/Medico.php';

class AuthController
{
    private Usuario $usuarioModel;
    private Paciente $pacienteModel;
    private Medico $medicoModel;

    public function __construct()
    {
        $this->usuarioModel = new Usuario();
        $this->pacienteModel = new Paciente();
        $this->medicoModel = new Medico();
    }

    public function registrarPaciente(array $datos): array
    {
        $nombre   = trim($datos['nombre'] ?? '');
        $email    = trim(strtolower($datos['email'] ?? ''));
        $password = (string) ($datos['password'] ?? '');
        $telefono = trim($datos['telefono'] ?? '');
        $direccion = trim($datos['direccion'] ?? '') ?: null;
        $fechaNacimiento = $datos['fecha_nacimiento'] ?? null;

        $errores = $this->validarRegistro($nombre, $email, $password);
        if (!empty($errores)) {
            return ['exito' => false, 'errores' => $errores];
        }

        if ($this->usuarioModel->existeEmail($email)) {
            return ['exito' => false, 'errores' => ['email' => 'Este correo ya está registrado.']];
        }

        $idUsuario = $this->usuarioModel->crear($nombre, $email, $password, 'paciente', $telefono);
        $this->pacienteModel->crear($idUsuario, $nombre, $fechaNacimiento ?: null, $telefono, $direccion);

        return ['exito' => true, 'mensaje' => 'Paciente registrado correctamente.'];
    }

    public function iniciarSesion(string $email, string $password): array
    {
        $email = trim(strtolower($email));
        $usuario = $this->usuarioModel->buscarPorEmail($email);

        if (!$usuario || !$this->usuarioModel->verificarPassword($password, $usuario['password_hash'])) {
            return ['exito' => false, 'mensaje' => 'Correo o contraseña incorrectos.'];
        }

        if ((int) $usuario['activo'] === 0) {
            return ['exito' => false, 'mensaje' => 'Esta cuenta se encuentra desactivada.'];
        }

        $_SESSION['id_usuario'] = (int) $usuario['id_usuario'];
        $_SESSION['nombre']     = $usuario['nombre'];
        $_SESSION['email']      = $usuario['email'];
        $_SESSION['rol']        = $usuario['rol'];

        if ($usuario['rol'] === 'paciente') {
            $paciente = $this->pacienteModel->buscarPorUsuario((int) $usuario['id_usuario']);
            $_SESSION['id_paciente'] = $paciente['id_paciente'] ?? null;
        } elseif ($usuario['rol'] === 'medico') {
            $medico = $this->medicoModel->buscarPorUsuario((int) $usuario['id_usuario']);
            $_SESSION['id_medico'] = $medico['id_medico'] ?? null;
        }

        session_regenerate_id(true); // mitiga fijación de sesión

        return [
            'exito'   => true,
            'mensaje' => 'Sesión iniciada correctamente.',
            'usuario' => [
                'nombre' => $usuario['nombre'],
                'rol'    => $usuario['rol'],
            ],
        ];
    }

    public function cerrarSesion(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    private function validarRegistro(string $nombre, string $email, string $password): array
    {
        $errores = [];

        if (strlen($nombre) < 3) {
            $errores['nombre'] = 'El nombre debe tener al menos 3 caracteres.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores['email'] = 'El correo electrónico no es válido.';
        }
        if (strlen($password) < 8) {
            $errores['password'] = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errores['password'] = 'La contraseña debe incluir al menos una mayúscula y un número.';
        }

        return $errores;
    }
}
