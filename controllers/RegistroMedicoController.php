<?php
require_once __DIR__ . '/../models/RegistroMedico.php';

class RegistroMedicoController
{
    private RegistroMedico $model;
    private array $tiposValidos = ['diagnostico', 'receta', 'referencia'];

    public function __construct()
    {
        $this->model = new RegistroMedico();
    }

    public function crear(int $idMedico, array $datos): array
    {
        $idPaciente = (int) ($datos['id_paciente'] ?? 0);
        $tipo = $datos['tipo'] ?? '';
        $contenido = trim($datos['contenido'] ?? '');
        $idCita = !empty($datos['id_cita']) ? (int) $datos['id_cita'] : null;

        if ($idPaciente <= 0) {
            return ['exito' => false, 'mensaje' => 'Debe indicar el paciente.', 'codigo' => 422];
        }
        if (!in_array($tipo, $this->tiposValidos, true)) {
            return ['exito' => false, 'mensaje' => 'Tipo de registro inválido.', 'codigo' => 422];
        }
        if (strlen($contenido) < 3) {
            return ['exito' => false, 'mensaje' => 'El contenido no puede estar vacío.', 'codigo' => 422];
        }

        $id = $this->model->crear($idPaciente, $idMedico, $idCita, $tipo, $contenido);
        return ['exito' => true, 'id_registro' => $id, 'codigo' => 201];
    }

    public function listarPorPaciente(int $idPaciente): array
    {
        return $this->model->listarPorPaciente($idPaciente);
    }

    public function eliminar(int $id, array $usuario): array
    {
        $registro = $this->model->buscarPorId($id);
        if (!$registro) {
            return ['exito' => false, 'mensaje' => 'Registro no encontrado.', 'codigo' => 404];
        }
        // Un médico solo puede eliminar registros que él mismo creó; el admin puede eliminar cualquiera.
        if ($usuario['rol'] === 'medico' && (int) $registro['id_medico'] !== (int) $usuario['id_medico']) {
            return ['exito' => false, 'mensaje' => 'Solo puede eliminar sus propios registros.', 'codigo' => 403];
        }
        $ok = $this->model->eliminar($id);
        return ['exito' => $ok, 'codigo' => $ok ? 200 : 400];
    }
}
