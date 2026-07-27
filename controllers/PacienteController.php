<?php
require_once __DIR__ . '/../models/Paciente.php';

class PacienteController
{
    private Paciente $pacienteModel;

    public function __construct()
    {
        $this->pacienteModel = new Paciente();
    }

    public function listar(): array
    {
        return $this->pacienteModel->listarTodos();
    }

    public function obtener(int $id): ?array
    {
        return $this->pacienteModel->buscarPorId($id);
    }

    public function actualizar(int $id, array $datos): array
    {
        if (empty($datos['nombre']) || strlen(trim($datos['nombre'])) < 3) {
            return ['exito' => false, 'errores' => ['nombre' => 'Nombre inválido.'], 'codigo' => 422];
        }

        $ok = $this->pacienteModel->actualizar(
            $id,
            trim($datos['nombre']),
            trim($datos['telefono'] ?? '') ?: null,
            trim($datos['direccion'] ?? '') ?: null
        );

        return ['exito' => $ok, 'codigo' => $ok ? 200 : 400];
    }

    public function eliminar(int $id): array
    {
        $ok = $this->pacienteModel->eliminar($id);
        return ['exito' => $ok, 'codigo' => $ok ? 200 : 400];
    }
}
