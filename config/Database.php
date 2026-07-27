<?php
/**
 * Database.php
 * Conexión centralizada a MySQL mediante PDO.
 * Diseñada para trabajar con phpMyAdmin / MySQL / MariaDB.
 */

class Database
{
    private static ?PDO $instance = null;

    // ---- Ajusta estos valores según tu entorno (XAMPP/phpMyAdmin) ----
    // Por defecto, XAMPP trae MySQL con usuario 'root' y sin contraseña.
    // Si tu servidor MySQL exige contraseña para conexiones TCP, crea un
    // usuario dedicado, por ejemplo:
    //   CREATE USER 'appuser'@'%' IDENTIFIED BY 'tu_password';
    //   GRANT ALL PRIVILEGES ON consultorio_medico.* TO 'appuser'@'%';
    //   FLUSH PRIVILEGES;
    // y actualiza USER/PASS abajo.
    private const HOST    = '127.0.0.1';
    private const PORT    = '3306';
    private const DBNAME  = 'consultorio_medico';
    private const USER    = 'root';
    private const PASS    = '';
    private const CHARSET = 'utf8mb4';

    private function __construct()
    {
    }

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                self::HOST,
                self::PORT,
                self::DBNAME,
                self::CHARSET
            );

            try {
                self::$instance = new PDO($dsn, self::USER, self::PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false, // consultas parametrizadas reales
                ]);
            } catch (PDOException $e) {
                // Nunca exponemos detalles de conexión al cliente.
                error_log('Error de conexión a la BD: ' . $e->getMessage());
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'exito'   => false,
                    'mensaje' => 'No fue posible conectar con la base de datos.'
                ]);
                exit;
            }
        }

        return self::$instance;
    }
}
