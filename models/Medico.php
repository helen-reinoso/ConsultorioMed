<?php
require_once __DIR__ . '/../config/Database.php';

class Medico
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function listarTodos(): array
    {
        $stmt = $this->db->query(
            'SELECT m.*, e.nombre AS especialidad
             FROM medicos m
             JOIN especialidades e ON e.id_especialidad = m.id_especialidad
             WHERE m.activo = 1
             ORDER BY m.nombre'
        );
        return $stmt->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*, e.nombre AS especialidad
             FROM medicos m JOIN especialidades e ON e.id_especialidad = m.id_especialidad
             WHERE m.id_medico = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function crear(string $nombre, int $idEspecialidad, ?string $email, ?string $telefono, ?int $idUsuario = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO medicos (nombre, id_especialidad, email, telefono, id_usuario)
             VALUES (:nombre, :id_especialidad, :email, :telefono, :id_usuario)'
        );
        $stmt->execute([
            'nombre'          => $nombre,
            'id_especialidad' => $idEspecialidad,
            'email'           => $email,
            'telefono'        => $telefono,
            'id_usuario'      => $idUsuario,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function buscarPorUsuario(int $idUsuario): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM medicos WHERE id_usuario = :id_usuario LIMIT 1');
        $stmt->execute(['id_usuario' => $idUsuario]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function vincularUsuario(int $idMedico, int $idUsuario): bool
    {
        $stmt = $this->db->prepare('UPDATE medicos SET id_usuario = :id_usuario WHERE id_medico = :id');
        return $stmt->execute(['id_usuario' => $idUsuario, 'id' => $idMedico]);
    }

    /** Pacientes que han tenido al menos una cita con este médico */
    public function pacientesPorMedico(int $idMedico): array
    {
        $stmt = $this->db->prepare('CALL sp_pacientes_por_medico(:id)');
        $stmt->execute(['id' => $idMedico]);
        $rows = $stmt->fetchAll();
        $stmt->closeCursor();
        return $rows;
    }

    public function actualizar(int $id, string $nombre, int $idEspecialidad, ?string $email, ?string $telefono): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE medicos SET nombre = :nombre, id_especialidad = :id_especialidad,
             email = :email, telefono = :telefono WHERE id_medico = :id'
        );
        return $stmt->execute([
            'nombre'          => $nombre,
            'id_especialidad' => $idEspecialidad,
            'email'           => $email,
            'telefono'        => $telefono,
            'id'              => $id,
        ]);
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE medicos SET activo = 0 WHERE id_medico = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function agregarHorario(int $idMedico, int $diaSemana, string $horaInicio, string $horaFin): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO horarios_medico (id_medico, dia_semana, hora_inicio, hora_fin)
             VALUES (:id_medico, :dia_semana, :hora_inicio, :hora_fin)'
        );
        $stmt->execute([
            'id_medico'   => $idMedico,
            'dia_semana'  => $diaSemana,
            'hora_inicio' => $horaInicio,
            'hora_fin'    => $horaFin,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listarHorarios(int $idMedico): array
    {
        $stmt = $this->db->prepare('SELECT * FROM horarios_medico WHERE id_medico = :id ORDER BY dia_semana, hora_inicio');
        $stmt->execute(['id' => $idMedico]);
        return $stmt->fetchAll();
    }

    public function actualizarHorario(int $idHorario, int $diaSemana, string $horaInicio, string $horaFin): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE horarios_medico SET dia_semana = :dia, hora_inicio = :hi, hora_fin = :hf WHERE id_horario = :id'
        );
        return $stmt->execute(['dia' => $diaSemana, 'hi' => $horaInicio, 'hf' => $horaFin, 'id' => $idHorario]);
    }

    public function eliminarHorario(int $idHorario): bool
    {
        $stmt = $this->db->prepare('DELETE FROM horarios_medico WHERE id_horario = :id');
        return $stmt->execute(['id' => $idHorario]);
    }

    /**
     * RETO: consulta los bloques laborales y las citas ya ocupadas
     * para una fecha específica, usando el procedimiento almacenado.
     */
    public function disponibilidadPorFecha(int $idMedico, string $fecha): array
    {
        $stmt = $this->db->prepare('CALL sp_horarios_disponibles_medico(:id_medico, :fecha)');
        $stmt->execute(['id_medico' => $idMedico, 'fecha' => $fecha]);
        $horarioLaboral = $stmt->fetchAll();
        $stmt->nextRowset();
        $citasOcupadas = $stmt->fetchAll();
        $stmt->closeCursor();

        return [
            'horario_laboral' => $horarioLaboral,
            'citas_ocupadas'  => $citasOcupadas,
        ];
    }
}
