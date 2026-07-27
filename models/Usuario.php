<?php
require_once __DIR__ . '/../config/Database.php';

class Usuario
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function buscarPorEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch();
        return $usuario ?: null;
    }

    public function crear(string $nombre, string $email, string $password, string $rol, ?string $telefono = null): int
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->db->prepare(
            'INSERT INTO usuarios (nombre, email, password_hash, rol, telefono) 
             VALUES (:nombre, :email, :password_hash, :rol, :telefono)'
        );
        $stmt->execute([
            'nombre'        => $nombre,
            'email'         => $email,
            'password_hash' => $hash,
            'rol'           => $rol,
            'telefono'      => $telefono,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function verificarPassword(string $password, string $hashAlmacenado): bool
    {
        return password_verify($password, $hashAlmacenado);
    }

    public function existeEmail(string $email): bool
    {
        return $this->buscarPorEmail($email) !== null;
    }
}
