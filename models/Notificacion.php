<?php
require_once __DIR__ . '/../config/Database.php';

class Notificacion
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Simula el envío de una notificación (no se conecta a un proveedor
     * real de correo/SMS) y la deja registrada para mostrarse en pantalla.
     */
    public function enviarSimulada(int $idCita, string $tipo, string $destinatario, string $mensaje): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO notificaciones (id_cita, tipo, destinatario, mensaje)
             VALUES (:id_cita, :tipo, :destinatario, :mensaje)'
        );
        $stmt->execute([
            'id_cita'      => $idCita,
            'tipo'         => $tipo,
            'destinatario' => $destinatario,
            'mensaje'      => $mensaje,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listarPorCita(int $idCita): array
    {
        $stmt = $this->db->prepare('SELECT * FROM notificaciones WHERE id_cita = :id_cita ORDER BY enviado_en DESC');
        $stmt->execute(['id_cita' => $idCita]);
        return $stmt->fetchAll();
    }
}
