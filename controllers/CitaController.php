<?php
require_once __DIR__ . '/../models/Cita.php';
require_once __DIR__ . '/../models/Medico.php';
require_once __DIR__ . '/../models/Notificacion.php';

class CitaController
{
    private Cita $citaModel;
    private Medico $medicoModel;
    private Notificacion $notificacionModel;

    public function __construct()
    {
        $this->citaModel = new Cita();
        $this->medicoModel = new Medico();
        $this->notificacionModel = new Notificacion();
    }

    private function validarDatosCita(array $datos): array
    {
        $errores = [];

        if (empty($datos['id_medico']) || !ctype_digit((string) $datos['id_medico'])) {
            $errores['id_medico'] = 'Debe seleccionar un médico válido.';
        }
        if (empty($datos['fecha']) || !$this->esFechaValida($datos['fecha'])) {
            $errores['fecha'] = 'La fecha no es válida.';
        } elseif ($datos['fecha'] < date('Y-m-d')) {
            $errores['fecha'] = 'No se pueden agendar citas en fechas pasadas.';
        }
        if (empty($datos['hora_inicio']) || empty($datos['hora_fin'])) {
            $errores['hora'] = 'Debe indicar hora de inicio y fin.';
        } elseif ($datos['hora_inicio'] >= $datos['hora_fin']) {
            $errores['hora'] = 'La hora de fin debe ser posterior a la de inicio.';
        }

        return $errores;
    }

    private function esFechaValida(string $fecha): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        return $d && $d->format('Y-m-d') === $fecha;
    }

    /** Verificación previa (sin registrar), usada por el frontend en tiempo real */
    public function verificarDisponibilidad(array $datos): array
    {
        $errores = $this->validarDatosCita($datos);
        if (!empty($errores)) {
            return ['exito' => false, 'errores' => $errores];
        }

        $resultado = $this->citaModel->verificarDisponibilidad(
            (int) $datos['id_medico'],
            $datos['fecha'],
            $datos['hora_inicio'],
            $datos['hora_fin']
        );

        return [
            'exito'       => true,
            'disponible'  => (bool) $resultado['disponible'],
            'mensaje'     => $resultado['mensaje'],
        ];
    }

    public function registrarCita(int $idPaciente, array $datos): array
    {
        $errores = $this->validarDatosCita($datos);
        if (!empty($errores)) {
            return ['exito' => false, 'errores' => $errores, 'codigo' => 422];
        }

        $resultado = $this->citaModel->registrar(
            $idPaciente,
            (int) $datos['id_medico'],
            $datos['fecha'],
            $datos['hora_inicio'],
            $datos['hora_fin'],
            trim($datos['motivo'] ?? '') ?: null
        );

        if (!$resultado['exito']) {
            return ['exito' => false, 'mensaje' => $resultado['mensaje'], 'codigo' => 409]; // 409 Conflict
        }

        // Notificación simulada (email en pantalla)
        $this->notificacionModel->enviarSimulada(
            (int) $resultado['id_cita'],
            'email',
            $_SESSION['email'] ?? 'paciente@consultorio.com',
            "Su cita ha sido registrada para el {$datos['fecha']} de {$datos['hora_inicio']} a {$datos['hora_fin']}. Estado: pendiente de confirmación."
        );

        return ['exito' => true, 'mensaje' => $resultado['mensaje'], 'id_cita' => $resultado['id_cita'], 'codigo' => 201];
    }

    public function modificarCita(int $idCita, array $datos): array
    {
        if (empty($datos['fecha']) || empty($datos['hora_inicio']) || empty($datos['hora_fin'])) {
            return ['exito' => false, 'mensaje' => 'Datos incompletos.', 'codigo' => 422];
        }

        $resultado = $this->citaModel->modificar($idCita, $datos['fecha'], $datos['hora_inicio'], $datos['hora_fin']);

        if (!$resultado['exito']) {
            return ['exito' => false, 'mensaje' => $resultado['mensaje'], 'codigo' => 409];
        }

        $this->notificacionModel->enviarSimulada(
            $idCita, 'email', $_SESSION['email'] ?? 'consultorio@consultorio.com',
            "Su cita ha sido reprogramada para el {$datos['fecha']} de {$datos['hora_inicio']} a {$datos['hora_fin']}."
        );

        return ['exito' => true, 'mensaje' => $resultado['mensaje'], 'codigo' => 200];
    }

    public function cambiarEstado(int $idCita, string $estado): array
    {
        $cita = $this->citaModel->buscarPorId($idCita);
        if (!$cita) {
            return ['exito' => false, 'mensaje' => 'Cita no encontrada.', 'codigo' => 404];
        }

        $ok = $this->citaModel->actualizarEstado($idCita, $estado);
        if (!$ok) {
            return ['exito' => false, 'mensaje' => 'Estado inválido.', 'codigo' => 422];
        }

        $mensajes = [
            'confirmada' => 'Su cita ha sido confirmada por el consultorio.',
            'cancelada'  => 'Su cita ha sido cancelada.',
            'completada' => 'Su cita ha sido marcada como completada.',
        ];

        if (isset($mensajes[$estado])) {
            $this->notificacionModel->enviarSimulada($idCita, 'sms', 'notificaciones@consultorio.com', $mensajes[$estado]);
        }

        return ['exito' => true, 'mensaje' => "Cita actualizada a estado: {$estado}.", 'codigo' => 200];
    }

    public function listarPorPaciente(int $idPaciente): array
    {
        return $this->citaModel->listarPorPaciente($idPaciente);
    }

    public function listarPorMedico(int $idMedico): array
    {
        return $this->citaModel->listarPorMedico($idMedico);
    }

    public function listarTodas(): array
    {
        return $this->citaModel->listarTodas();
    }

    public function obtenerPorId(int $id): ?array
    {
        return $this->citaModel->buscarPorId($id);
    }

    public function eliminar(int $id): bool
    {
        return $this->citaModel->eliminar($id);
    }
}
