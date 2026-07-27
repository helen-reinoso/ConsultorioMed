<?php
require_once __DIR__ . '/../config/Database.php';

class Especialidad
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function listarTodas(): array
    {
        return $this->db->query('SELECT * FROM especialidades ORDER BY nombre')->fetchAll();
    }

    public function crear(string $nombre, ?string $descripcion): int
    {
        $stmt = $this->db->prepare('INSERT INTO especialidades (nombre, descripcion) VALUES (:nombre, :descripcion)');
        $stmt->execute(['nombre' => $nombre, 'descripcion' => $descripcion]);
        return (int) $this->db->lastInsertId();
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM especialidades WHERE id_especialidad = :id');
        return $stmt->execute(['id' => $id]);
    }
}
