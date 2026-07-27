-- =====================================================================
-- MIGRACIÓN: Acceso para médicos + historial clínico
-- (diagnósticos, recetas, referencias)
--
-- Ejecuta este script en phpMyAdmin -> pestaña SQL -> pega y ejecuta.
-- NO borra ninguna tabla existente; solo agrega lo nuevo.
-- =====================================================================

USE consultorio_medico;
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1) Permitir el rol 'medico' en usuarios
-- ---------------------------------------------------------------------
ALTER TABLE usuarios
    MODIFY rol ENUM('paciente','admin','medico') NOT NULL DEFAULT 'paciente';

-- ---------------------------------------------------------------------
-- 2) Tabla de historial clínico (diagnósticos, recetas, referencias)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS registros_medicos (
    id_registro    INT AUTO_INCREMENT PRIMARY KEY,
    id_paciente    INT NOT NULL,
    id_medico      INT NOT NULL,
    id_cita        INT NULL,
    tipo           ENUM('diagnostico','receta','referencia') NOT NULL,
    contenido      TEXT NOT NULL,
    creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_registro_paciente FOREIGN KEY (id_paciente)
        REFERENCES pacientes(id_paciente) ON DELETE CASCADE,
    CONSTRAINT fk_registro_medico FOREIGN KEY (id_medico)
        REFERENCES medicos(id_medico) ON DELETE CASCADE,
    CONSTRAINT fk_registro_cita FOREIGN KEY (id_cita)
        REFERENCES citas(id_cita) ON DELETE SET NULL
) ENGINE=InnoDB;

-- CREATE INDEX idx_registro_paciente ON registros_medicos (id_paciente, creado_en);

-- ---------------------------------------------------------------------
-- 3) Procedimientos almacenados nuevos
-- ---------------------------------------------------------------------
DELIMITER $$

DROP PROCEDURE IF EXISTS sp_crear_registro_medico$$
CREATE PROCEDURE sp_crear_registro_medico (
    IN  p_id_paciente INT,
    IN  p_id_medico   INT,
    IN  p_id_cita     INT,
    IN  p_tipo        VARCHAR(20),
    IN  p_contenido   TEXT,
    OUT p_id_registro INT
)
BEGIN
    INSERT INTO registros_medicos (id_paciente, id_medico, id_cita, tipo, contenido)
    VALUES (p_id_paciente, p_id_medico, p_id_cita, p_tipo, p_contenido);
    SET p_id_registro = LAST_INSERT_ID();
END$$

DROP PROCEDURE IF EXISTS sp_registros_por_paciente$$
CREATE PROCEDURE sp_registros_por_paciente (IN p_id_paciente INT)
BEGIN
    SELECT r.*, m.nombre AS medico_nombre
    FROM registros_medicos r
    JOIN medicos m ON m.id_medico = r.id_medico
    WHERE r.id_paciente = p_id_paciente
    ORDER BY r.creado_en DESC;
END$$

DROP PROCEDURE IF EXISTS sp_pacientes_por_medico$$
CREATE PROCEDURE sp_pacientes_por_medico (IN p_id_medico INT)
BEGIN
    SELECT DISTINCT p.id_paciente, p.nombre, p.telefono, p.fecha_nacimiento
    FROM citas c
    JOIN pacientes p ON p.id_paciente = c.id_paciente
    WHERE c.id_medico = p_id_medico
    ORDER BY p.nombre;
END$$

DROP PROCEDURE IF EXISTS sp_citas_por_medico$$
CREATE PROCEDURE sp_citas_por_medico (IN p_id_medico INT)
BEGIN
    SELECT c.id_cita, c.fecha_cita, c.hora_inicio, c.hora_fin, c.estado, c.motivo,
           p.id_paciente, p.nombre AS paciente, p.telefono AS paciente_telefono
    FROM citas c
    JOIN pacientes p ON p.id_paciente = c.id_paciente
    WHERE c.id_medico = p_id_medico
    ORDER BY c.fecha_cita DESC, c.hora_inicio DESC;
END$$

DELIMITER ;
