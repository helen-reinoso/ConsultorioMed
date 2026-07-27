<?php
require_once __DIR__ . '/../models/Medico.php';
require_once __DIR__ . '/../models/Especialidad.php';
require_once __DIR__ . '/../models/Usuario.php';

class MedicoController
{
    private Medico $medicoModel;
    private Especialidad $especialidadModel;
    private Usuario $usuarioModel;

    public function __construct()
    {
        $this->medicoModel = new Medico();
        $this->especialidadModel = new Especialidad();
        $this->usuarioModel = new Usuario();
    }

    public function listar(): array
    {
        return $this->medicoModel->listarTodos();
    }

    public function obtener(int $id): ?array
    {
        return $this->medicoModel->buscarPorId($id);
    }

    public function crear(array $datos): array
    {
        $errores = $this->validar($datos);
        if (!empty($errores)) {
            return ['exito' => false, 'errores' => $errores, 'codigo' => 422];
        }

        $idUsuario = null;
        if (!empty($datos['password'])) {
            $errAcceso = $this->validarAcceso($datos);
            if ($errAcceso) {
                return ['exito' => false, 'errores' => $errAcceso, 'codigo' => 422];
            }
            $idUsuario = $this->usuarioModel->crear(
                trim($datos['nombre']),
                trim($datos['email']),
                $datos['password'],
                'medico',
                trim($datos['telefono'] ?? '') ?: null
            );
        }

        $id = $this->medicoModel->crear(
            trim($datos['nombre']),
            (int) $datos['id_especialidad'],
            trim($datos['email'] ?? '') ?: null,
            trim($datos['telefono'] ?? '') ?: null,
            $idUsuario
        );

        return ['exito' => true, 'id_medico' => $id, 'codigo' => 201];
    }

    /** Otorga acceso (usuario + contraseña) a un médico ya existente que no tenía login */
    public function crearAcceso(int $idMedico, array $datos): array
    {
        $medico = $this->medicoModel->buscarPorId($idMedico);
        if (!$medico) {
            return ['exito' => false, 'mensaje' => 'Médico no encontrado.', 'codigo' => 404];
        }
        if (!empty($medico['id_usuario'])) {
            return ['exito' => false, 'mensaje' => 'Este médico ya tiene acceso creado.', 'codigo' => 409];
        }

        $errAcceso = $this->validarAcceso($datos);
        if ($errAcceso) {
            return ['exito' => false, 'errores' => $errAcceso, 'codigo' => 422];
        }

        $idUsuario = $this->usuarioModel->crear(
            $medico['nombre'],
            trim($datos['email']),
            $datos['password'],
            'medico',
            $medico['telefono']
        );
        $this->medicoModel->vincularUsuario($idMedico, $idUsuario);

        return ['exito' => true, 'codigo' => 201];
    }

    private function validarAcceso(array $datos): ?array
    {
        if (empty($datos['email']) || !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            return ['email' => 'Debe indicar un correo válido para crear el acceso.'];
        }
        if ($this->usuarioModel->existeEmail(trim($datos['email']))) {
            return ['email' => 'Ese correo ya está en uso por otra cuenta.'];
        }
        if (empty($datos['password']) || strlen($datos['password']) < 8) {
            return ['password' => 'La contraseña debe tener al menos 8 caracteres.'];
        }
        return null;
    }

    public function actualizar(int $id, array $datos): array
    {
        $errores = $this->validar($datos);
        if (!empty($errores)) {
            return ['exito' => false, 'errores' => $errores, 'codigo' => 422];
        }

        $ok = $this->medicoModel->actualizar(
            $id,
            trim($datos['nombre']),
            (int) $datos['id_especialidad'],
            trim($datos['email'] ?? '') ?: null,
            trim($datos['telefono'] ?? '') ?: null
        );

        return ['exito' => $ok, 'codigo' => $ok ? 200 : 400];
    }

    public function eliminar(int $id): array
    {
        $ok = $this->medicoModel->eliminar($id);
        return ['exito' => $ok, 'codigo' => $ok ? 200 : 400];
    }

    public function agregarHorario(int $idMedico, array $datos): array
    {
        $err = $this->validarHorario($datos);
        if (!empty($err)) {
            return ['exito' => false, 'mensaje' => $err, 'codigo' => 422];
        }
        $id = $this->medicoModel->agregarHorario(
            $idMedico,
            (int) $datos['dia_semana'],
            $datos['hora_inicio'],
            $datos['hora_fin']
        );
        return ['exito' => true, 'id_horario' => $id, 'codigo' => 201];
    }

    public function actualizarHorario(int $idHorario, array $datos): array
    {
        $err = $this->validarHorario($datos);
        if (!empty($err)) {
            return ['exito' => false, 'mensaje' => $err, 'codigo' => 422];
        }
        $ok = $this->medicoModel->actualizarHorario(
            $idHorario,
            (int) $datos['dia_semana'],
            $datos['hora_inicio'],
            $datos['hora_fin']
        );
        return ['exito' => $ok, 'codigo' => $ok ? 200 : 400];
    }

    public function eliminarHorario(int $idHorario): array
    {
        $ok = $this->medicoModel->eliminarHorario($idHorario);
        return ['exito' => $ok, 'codigo' => $ok ? 200 : 400];
    }

    private function validarHorario(array $datos): ?string
    {
        if (empty($datos['dia_semana']) || (int) $datos['dia_semana'] < 1 || (int) $datos['dia_semana'] > 7) {
            return 'Debe indicar un día de la semana válido (1=Lunes...7=Domingo).';
        }
        if (empty($datos['hora_inicio']) || empty($datos['hora_fin'])) {
            return 'Debe indicar hora de inicio y fin.';
        }
        if ($datos['hora_inicio'] >= $datos['hora_fin']) {
            return 'La hora de fin debe ser posterior a la hora de inicio.';
        }
        return null;
    }

    public function listarHorarios(int $idMedico): array
    {
        return $this->medicoModel->listarHorarios($idMedico);
    }

    public function disponibilidadPorFecha(int $idMedico, string $fecha): array
    {
        return $this->medicoModel->disponibilidadPorFecha($idMedico, $fecha);
    }

    public function listarEspecialidades(): array
    {
        return $this->especialidadModel->listarTodas();
    }

    public function pacientesPorMedico(int $idMedico): array
    {
        return $this->medicoModel->pacientesPorMedico($idMedico);
    }

    public function crearEspecialidad(array $datos): array
    {
        if (empty($datos['nombre']) || strlen(trim($datos['nombre'])) < 3) {
            return ['exito' => false, 'errores' => ['nombre' => 'El nombre de la especialidad es obligatorio (mínimo 3 caracteres).'], 'codigo' => 422];
        }
        try {
            $id = $this->especialidadModel->crear(trim($datos['nombre']), trim($datos['descripcion'] ?? '') ?: null);
            return ['exito' => true, 'id_especialidad' => $id, 'codigo' => 201];
        } catch (PDOException $e) {
            return ['exito' => false, 'mensaje' => 'Ya existe una especialidad con ese nombre.', 'codigo' => 409];
        }
    }

    public function eliminarEspecialidad(int $id): array
    {
        try {
            $ok = $this->especialidadModel->eliminar($id);
            return ['exito' => $ok, 'codigo' => $ok ? 200 : 400];
        } catch (PDOException $e) {
            return ['exito' => false, 'mensaje' => 'No se puede eliminar: hay médicos asociados a esta especialidad.', 'codigo' => 409];
        }
    }

    private function validar(array $datos): array
    {
        $errores = [];
        if (empty($datos['nombre']) || strlen(trim($datos['nombre'])) < 3) {
            $errores['nombre'] = 'El nombre del médico es obligatorio (mínimo 3 caracteres).';
        }
        if (empty($datos['id_especialidad']) || !ctype_digit((string) $datos['id_especialidad'])) {
            $errores['id_especialidad'] = 'Debe seleccionar una especialidad válida.';
        }
        if (!empty($datos['email']) && !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            $errores['email'] = 'El correo electrónico no es válido.';
        }
        return $errores;
    }
}
