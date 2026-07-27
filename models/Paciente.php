<?php
require_once __DIR__ . '/../config/Database.php';

class Paciente
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function crear(int $idUsuario, string $nombre, ?string $fechaNacimiento, ?string $telefono, ?string $direccion): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pacientes (id_usuario, nombre, fecha_nacimiento, telefono, direccion)
             VALUES (:id_usuario, :nombre, :fecha_nacimiento, :telefono, :direccion)'
        );
        $stmt->execute([
            'id_usuario'        => $idUsuario,
            'nombre'            => $nombre,
            'fecha_nacimiento'  => $fechaNacimiento,
            'telefono'          => $telefono,
            'direccion'         => $direccion,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function buscarPorUsuario(int $idUsuario): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM pacientes WHERE id_usuario = :id_usuario LIMIT 1');
        $stmt->execute(['id_usuario' => $idUsuario]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listarTodos(): array
    {
        $stmt = $this->db->query('SELECT * FROM pacientes ORDER BY nombre');
        return $stmt->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM pacientes WHERE id_paciente = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function actualizar(int $id, string $nombre, ?string $telefono, ?string $direccion): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE pacientes SET nombre = :nombre, telefono = :telefono, direccion = :direccion WHERE id_paciente = :id'
        );
        return $stmt->execute([
            'nombre'    => $nombre,
            'telefono'  => $telefono,
            'direccion' => $direccion,
            'id'        => $id,
        ]);
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM pacientes WHERE id_paciente = :id');
        return $stmt->execute(['id' => $id]);
    }
}
