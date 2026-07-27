<?php
require_once __DIR__ . '/../config/Database.php';

class RegistroMedico
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function crear(int $idPaciente, int $idMedico, ?int $idCita, string $tipo, string $contenido): int
    {
        $stmt = $this->db->prepare('CALL sp_crear_registro_medico(:p, :m, :c, :t, :cont, @id_registro)');
        $stmt->execute([
            'p'    => $idPaciente,
            'm'    => $idMedico,
            'c'    => $idCita,
            't'    => $tipo,
            'cont' => $contenido,
        ]);
        $stmt->closeCursor();

        $out = $this->db->query('SELECT @id_registro AS id')->fetch();
        return (int) $out['id'];
    }

    public function listarPorPaciente(int $idPaciente): array
    {
        $stmt = $this->db->prepare('CALL sp_registros_por_paciente(:id)');
        $stmt->execute(['id' => $idPaciente]);
        $rows = $stmt->fetchAll();
        $stmt->closeCursor();
        return $rows;
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM registros_medicos WHERE id_registro = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM registros_medicos WHERE id_registro = :id');
        return $stmt->execute(['id' => $id]);
    }
}
