<?php
require_once __DIR__ . '/../config/Database.php';

class Cita
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * RETO: verifica disponibilidad médica en tiempo real
     * (horario laboral + traslapes) llamando al procedimiento almacenado.
     */
    public function verificarDisponibilidad(int $idMedico, string $fecha, string $horaInicio, string $horaFin): array
    {
        $stmt = $this->db->prepare('CALL sp_verificar_disponibilidad(:id_medico, :fecha, :hora_inicio, :hora_fin)');
        $stmt->execute([
            'id_medico'   => $idMedico,
            'fecha'       => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin'    => $horaFin,
        ]);
        $resultado = $stmt->fetch();
        $stmt->closeCursor();
        return $resultado;
    }

    /**
     * Registra la cita de forma transaccional a través del SP,
     * usando parámetros OUT para conocer el resultado exacto.
     */
    public function registrar(int $idPaciente, int $idMedico, string $fecha, string $horaInicio, string $horaFin, ?string $motivo): array
    {
        $sql = 'CALL sp_registrar_cita(:id_paciente, :id_medico, :fecha, :hora_inicio, :hora_fin, :motivo, @resultado, @mensaje, @id_cita)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id_paciente' => $idPaciente,
            'id_medico'   => $idMedico,
            'fecha'       => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin'    => $horaFin,
            'motivo'      => $motivo,
        ]);
        $stmt->closeCursor();

        $out = $this->db->query('SELECT @resultado AS resultado, @mensaje AS mensaje, @id_cita AS id_cita')->fetch();

        return [
            'exito'   => (bool) $out['resultado'],
            'mensaje' => $out['mensaje'],
            'id_cita' => $out['id_cita'],
        ];
    }

    public function modificar(int $idCita, string $fecha, string $horaInicio, string $horaFin): array
    {
        $sql = 'CALL sp_modificar_cita(:id_cita, :fecha, :hora_inicio, :hora_fin, @resultado, @mensaje)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id_cita'     => $idCita,
            'fecha'       => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin'    => $horaFin,
        ]);
        $stmt->closeCursor();

        $out = $this->db->query('SELECT @resultado AS resultado, @mensaje AS mensaje')->fetch();

        return [
            'exito'   => (bool) $out['resultado'],
            'mensaje' => $out['mensaje'],
        ];
    }

    public function actualizarEstado(int $idCita, string $nuevoEstado): bool
    {
        $estadosValidos = ['pendiente', 'confirmada', 'cancelada', 'completada'];
        if (!in_array($nuevoEstado, $estadosValidos, true)) {
            return false;
        }

        $stmt = $this->db->prepare('CALL sp_actualizar_estado_cita(:id_cita, :estado)');
        $stmt->execute(['id_cita' => $idCita, 'estado' => $nuevoEstado]);
        $stmt->closeCursor();
        return true;
    }

    public function listarPorPaciente(int $idPaciente): array
    {
        $stmt = $this->db->prepare('CALL sp_citas_por_paciente(:id_paciente)');
        $stmt->execute(['id_paciente' => $idPaciente]);
        $resultado = $stmt->fetchAll();
        $stmt->closeCursor();
        return $resultado;
    }

    public function listarPorMedico(int $idMedico): array
    {
        $stmt = $this->db->prepare('CALL sp_citas_por_medico(:id_medico)');
        $stmt->execute(['id_medico' => $idMedico]);
        $resultado = $stmt->fetchAll();
        $stmt->closeCursor();
        return $resultado;
    }

    public function listarTodas(): array
    {
        $stmt = $this->db->query('CALL sp_citas_todas()');
        $resultado = $stmt->fetchAll();
        $stmt->closeCursor();
        return $resultado;
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, p.nombre AS paciente, p.id_usuario, m.nombre AS medico, e.nombre AS especialidad
             FROM citas c
             JOIN pacientes p ON p.id_paciente = c.id_paciente
             JOIN medicos m ON m.id_medico = c.id_medico
             JOIN especialidades e ON e.id_especialidad = m.id_especialidad
             WHERE c.id_cita = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM citas WHERE id_cita = :id');
        return $stmt->execute(['id' => $id]);
    }
}
