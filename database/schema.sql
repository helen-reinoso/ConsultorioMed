-- =====================================================================
-- SISTEMA DE RESERVAS PARA CONSULTORIOS MÉDICOS
-- Universidad Tecnológica de Panamá - Desarrollo de Software VII
-- Script de base de datos: tablas, restricciones, procedimientos
-- almacenados e inserciones de prueba.
-- Motor: MySQL / MariaDB (compatible con phpMyAdmin)
-- =====================================================================

DROP DATABASE IF EXISTS consultorio_medico;
CREATE DATABASE consultorio_medico CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE consultorio_medico;

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- TABLA: usuarios  (login / control de roles)
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id_usuario      INT AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(120)  NOT NULL,
    email           VARCHAR(150)  NOT NULL UNIQUE,
    password_hash   VARCHAR(255)  NOT NULL,
    rol             ENUM('paciente','admin','medico') NOT NULL DEFAULT 'paciente',
    telefono        VARCHAR(20)   NULL,
    activo          TINYINT(1)    NOT NULL DEFAULT 1,
    creado_en       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- TABLA: especialidades
-- ---------------------------------------------------------------------
CREATE TABLE especialidades (
    id_especialidad INT AUTO_INCREMENT PRIMARY KEY,
    nombre           VARCHAR(100) NOT NULL UNIQUE,
    descripcion      VARCHAR(255) NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- TABLA: medicos
-- ---------------------------------------------------------------------
CREATE TABLE medicos (
    id_medico        INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario       INT NULL,                 -- opcional: si el médico también inicia sesión
    nombre           VARCHAR(120) NOT NULL,
    id_especialidad  INT NOT NULL,
    email            VARCHAR(150) NULL,
    telefono         VARCHAR(20)  NULL,
    activo           TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_medico_especialidad FOREIGN KEY (id_especialidad)
        REFERENCES especialidades(id_especialidad) ON DELETE RESTRICT,
    CONSTRAINT fk_medico_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- TABLA: horarios_medico (horario laboral por día de la semana)
-- dia_semana: 1=Lunes ... 7=Domingo
-- ---------------------------------------------------------------------
CREATE TABLE horarios_medico (
    id_horario     INT AUTO_INCREMENT PRIMARY KEY,
    id_medico      INT NOT NULL,
    dia_semana     TINYINT NOT NULL CHECK (dia_semana BETWEEN 1 AND 7),
    hora_inicio    TIME NOT NULL,
    hora_fin       TIME NOT NULL,
    CONSTRAINT fk_horario_medico FOREIGN KEY (id_medico)
        REFERENCES medicos(id_medico) ON DELETE CASCADE,
    CONSTRAINT chk_horario_valido CHECK (hora_fin > hora_inicio)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- TABLA: pacientes
-- ---------------------------------------------------------------------
CREATE TABLE pacientes (
    id_paciente      INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario       INT NOT NULL,
    nombre           VARCHAR(120) NOT NULL,
    fecha_nacimiento DATE NULL,
    telefono         VARCHAR(20)  NULL,
    direccion        VARCHAR(200) NULL,
    creado_en        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_paciente_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- TABLA: citas
-- ---------------------------------------------------------------------
CREATE TABLE citas (
    id_cita        INT AUTO_INCREMENT PRIMARY KEY,
    id_paciente    INT NOT NULL,
    id_medico      INT NOT NULL,
    fecha_cita     DATE NOT NULL,
    hora_inicio    TIME NOT NULL,
    hora_fin       TIME NOT NULL,
    estado         ENUM('pendiente','confirmada','cancelada','completada') NOT NULL DEFAULT 'pendiente',
    motivo         VARCHAR(255) NULL,
    creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cita_paciente FOREIGN KEY (id_paciente)
        REFERENCES pacientes(id_paciente) ON DELETE CASCADE,
    CONSTRAINT fk_cita_medico FOREIGN KEY (id_medico)
        REFERENCES medicos(id_medico) ON DELETE CASCADE,
    CONSTRAINT chk_cita_horas CHECK (hora_fin > hora_inicio)
) ENGINE=InnoDB;

-- Índice para acelerar la validación de traslapes (reto principal)
CREATE INDEX idx_cita_medico_fecha ON citas (id_medico, fecha_cita, estado);

-- ---------------------------------------------------------------------
-- TABLA: notificaciones (simulación email/SMS)
-- ---------------------------------------------------------------------
CREATE TABLE notificaciones (
    id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
    id_cita         INT NOT NULL,
    tipo            ENUM('email','sms') NOT NULL DEFAULT 'email',
    destinatario    VARCHAR(150) NOT NULL,
    mensaje         VARCHAR(500) NOT NULL,
    enviado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_cita FOREIGN KEY (id_cita)
        REFERENCES citas(id_cita) ON DELETE CASCADE
) ENGINE=InnoDB;


-- ---------------------------------------------------------------------
-- TABLA: registros_medicos (diagnósticos, recetas y referencias que el
-- médico agrega al historial de un paciente)
-- ---------------------------------------------------------------------
CREATE TABLE registros_medicos (
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

CREATE INDEX idx_registro_paciente ON registros_medicos (id_paciente, creado_en);

-- =====================================================================
-- PROCEDIMIENTOS ALMACENADOS
-- =====================================================================
DELIMITER $$

-- ---------------------------------------------------------------------
-- sp_verificar_disponibilidad
-- RETO: valida que el médico trabaje ese día/hora y que no exista
-- traslape con otra cita (pendiente o confirmada) del mismo médico.
-- Devuelve un solo registro: disponible (1/0) y un mensaje.
-- ---------------------------------------------------------------------
CREATE PROCEDURE sp_verificar_disponibilidad (
    IN p_id_medico    INT,
    IN p_fecha        DATE,
    IN p_hora_inicio  TIME,
    IN p_hora_fin     TIME
)
BEGIN
    DECLARE v_dia_semana TINYINT;
    DECLARE v_dentro_horario INT DEFAULT 0;
    DECLARE v_traslapes INT DEFAULT 0;

    -- MySQL DAYOFWEEK: 1=Domingo..7=Sábado -> convertir a 1=Lunes..7=Domingo
    SET v_dia_semana = ((DAYOFWEEK(p_fecha) + 5) % 7) + 1;

    SELECT COUNT(*) INTO v_dentro_horario
    FROM horarios_medico
    WHERE id_medico = p_id_medico
      AND dia_semana = v_dia_semana
      AND p_hora_inicio >= hora_inicio
      AND p_hora_fin    <= hora_fin;

    IF v_dentro_horario = 0 THEN
        SELECT 0 AS disponible, 'El horario solicitado está fuera del horario laboral del médico ese día.' AS mensaje;
    ELSE
        SELECT COUNT(*) INTO v_traslapes
        FROM citas
        WHERE id_medico = p_id_medico
          AND fecha_cita = p_fecha
          AND estado IN ('pendiente','confirmada')
          AND (p_hora_inicio < hora_fin AND p_hora_fin > hora_inicio);

        IF v_traslapes > 0 THEN
            SELECT 0 AS disponible, 'El médico ya tiene una cita registrada que se traslapa con ese horario.' AS mensaje;
        ELSE
            SELECT 1 AS disponible, 'Horario disponible.' AS mensaje;
        END IF;
    END IF;
END$$

-- ---------------------------------------------------------------------
-- sp_registrar_cita
-- Reutiliza la validación anterior de forma atómica antes de insertar.
-- Usa una variable de sesión como "señal" de resultado ya que los SP
-- no pueden devolver OUT y result set fácilmente combinados; aquí se
-- opta por un parámetro OUT con el código de resultado.
-- ---------------------------------------------------------------------
CREATE PROCEDURE sp_registrar_cita (
    IN  p_id_paciente   INT,
    IN  p_id_medico     INT,
    IN  p_fecha         DATE,
    IN  p_hora_inicio   TIME,
    IN  p_hora_fin      TIME,
    IN  p_motivo        VARCHAR(255),
    OUT p_resultado     INT,          -- 1 = ok, 0 = no disponible
    OUT p_mensaje       VARCHAR(255),
    OUT p_id_cita       INT
)
BEGIN
    DECLARE v_dia_semana TINYINT;
    DECLARE v_dentro_horario INT DEFAULT 0;
    DECLARE v_traslapes INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_resultado = 0;
        SET p_mensaje = 'Error interno al registrar la cita.';
        SET p_id_cita = NULL;
    END;

    START TRANSACTION;

    SET v_dia_semana = ((DAYOFWEEK(p_fecha) + 5) % 7) + 1;

    SELECT COUNT(*) INTO v_dentro_horario
    FROM horarios_medico
    WHERE id_medico = p_id_medico
      AND dia_semana = v_dia_semana
      AND p_hora_inicio >= hora_inicio
      AND p_hora_fin    <= hora_fin;

    IF v_dentro_horario = 0 THEN
        ROLLBACK;
        SET p_resultado = 0;
        SET p_mensaje = 'El horario solicitado está fuera del horario laboral del médico ese día.';
        SET p_id_cita = NULL;
    ELSE
        -- SELECT ... FOR UPDATE bloquea filas existentes para evitar
        -- condiciones de carrera con citas concurrentes.
        SELECT COUNT(*) INTO v_traslapes
        FROM citas
        WHERE id_medico = p_id_medico
          AND fecha_cita = p_fecha
          AND estado IN ('pendiente','confirmada')
          AND (p_hora_inicio < hora_fin AND p_hora_fin > hora_inicio)
        FOR UPDATE;

        IF v_traslapes > 0 THEN
            ROLLBACK;
            SET p_resultado = 0;
            SET p_mensaje = 'El médico ya tiene una cita registrada que se traslapa con ese horario.';
            SET p_id_cita = NULL;
        ELSE
            INSERT INTO citas (id_paciente, id_medico, fecha_cita, hora_inicio, hora_fin, motivo, estado)
            VALUES (p_id_paciente, p_id_medico, p_fecha, p_hora_inicio, p_hora_fin, p_motivo, 'pendiente');

            SET p_id_cita = LAST_INSERT_ID();
            COMMIT;
            SET p_resultado = 1;
            SET p_mensaje = 'Cita registrada exitosamente.';
        END IF;
    END IF;
END$$

-- ---------------------------------------------------------------------
-- sp_actualizar_estado_cita  (confirmar / cancelar / completar)
-- ---------------------------------------------------------------------
CREATE PROCEDURE sp_actualizar_estado_cita (
    IN p_id_cita INT,
    IN p_nuevo_estado VARCHAR(20)
)
BEGIN
    UPDATE citas
    SET estado = p_nuevo_estado
    WHERE id_cita = p_id_cita;

    SELECT ROW_COUNT() AS filas_afectadas;
END$$

-- ---------------------------------------------------------------------
-- sp_modificar_cita (reprograma fecha/hora, revalida traslapes)
-- ---------------------------------------------------------------------
CREATE PROCEDURE sp_modificar_cita (
    IN  p_id_cita      INT,
    IN  p_fecha        DATE,
    IN  p_hora_inicio  TIME,
    IN  p_hora_fin     TIME,
    OUT p_resultado    INT,
    OUT p_mensaje      VARCHAR(255)
)
BEGIN
    DECLARE v_id_medico INT;
    DECLARE v_dia_semana TINYINT;
    DECLARE v_dentro_horario INT DEFAULT 0;
    DECLARE v_traslapes INT DEFAULT 0;

    SELECT id_medico INTO v_id_medico FROM citas WHERE id_cita = p_id_cita;

    SET v_dia_semana = ((DAYOFWEEK(p_fecha) + 5) % 7) + 1;

    SELECT COUNT(*) INTO v_dentro_horario
    FROM horarios_medico
    WHERE id_medico = v_id_medico
      AND dia_semana = v_dia_semana
      AND p_hora_inicio >= hora_inicio
      AND p_hora_fin    <= hora_fin;

    IF v_dentro_horario = 0 THEN
        SET p_resultado = 0;
        SET p_mensaje = 'Fuera del horario laboral del médico.';
    ELSE
        SELECT COUNT(*) INTO v_traslapes
        FROM citas
        WHERE id_medico = v_id_medico
          AND fecha_cita = p_fecha
          AND estado IN ('pendiente','confirmada')
          AND id_cita <> p_id_cita
          AND (p_hora_inicio < hora_fin AND p_hora_fin > hora_inicio);

        IF v_traslapes > 0 THEN
            SET p_resultado = 0;
            SET p_mensaje = 'Traslape con otra cita existente.';
        ELSE
            UPDATE citas
            SET fecha_cita = p_fecha, hora_inicio = p_hora_inicio, hora_fin = p_hora_fin
            WHERE id_cita = p_id_cita;
            SET p_resultado = 1;
            SET p_mensaje = 'Cita modificada exitosamente.';
        END IF;
    END IF;
END$$

-- ---------------------------------------------------------------------
-- sp_citas_por_paciente
-- ---------------------------------------------------------------------
CREATE PROCEDURE sp_citas_por_paciente (IN p_id_paciente INT)
BEGIN
    SELECT c.id_cita, c.fecha_cita, c.hora_inicio, c.hora_fin, c.estado, c.motivo,
           m.nombre AS medico, e.nombre AS especialidad
    FROM citas c
    JOIN medicos m ON m.id_medico = c.id_medico
    JOIN especialidades e ON e.id_especialidad = m.id_especialidad
    WHERE c.id_paciente = p_id_paciente
    ORDER BY c.fecha_cita DESC, c.hora_inicio DESC;
END$$

-- ---------------------------------------------------------------------
-- sp_citas_todas (para el administrador / consultorio)
-- ---------------------------------------------------------------------
CREATE PROCEDURE sp_citas_todas ()
BEGIN
    SELECT c.id_cita, c.fecha_cita, c.hora_inicio, c.hora_fin, c.estado, c.motivo,
           p.nombre AS paciente, m.nombre AS medico, e.nombre AS especialidad
    FROM citas c
    JOIN pacientes p ON p.id_paciente = c.id_paciente
    JOIN medicos m ON m.id_medico = c.id_medico
    JOIN especialidades e ON e.id_especialidad = m.id_especialidad
    ORDER BY c.fecha_cita DESC, c.hora_inicio DESC;
END$$

-- ---------------------------------------------------------------------
-- sp_horarios_disponibles_medico: genera bloques de 30 min y marca ocupados
-- ---------------------------------------------------------------------
CREATE PROCEDURE sp_horarios_disponibles_medico (
    IN p_id_medico INT,
    IN p_fecha DATE
)
BEGIN
    DECLARE v_dia_semana TINYINT;
    SET v_dia_semana = ((DAYOFWEEK(p_fecha) + 5) % 7) + 1;

    SELECT hm.hora_inicio, hm.hora_fin
    FROM horarios_medico hm
    WHERE hm.id_medico = p_id_medico AND hm.dia_semana = v_dia_semana;

    SELECT c.hora_inicio, c.hora_fin
    FROM citas c
    WHERE c.id_medico = p_id_medico
      AND c.fecha_cita = p_fecha
      AND c.estado IN ('pendiente','confirmada');
END$$

-- ---------------------------------------------------------------------
-- sp_crear_registro_medico / sp_registros_por_paciente
-- sp_pacientes_por_medico / sp_citas_por_medico
-- Soportan el acceso del médico: su propia agenda, sus pacientes,
-- y el historial clínico (diagnósticos, recetas, referencias).
-- ---------------------------------------------------------------------
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

CREATE PROCEDURE sp_registros_por_paciente (IN p_id_paciente INT)
BEGIN
    SELECT r.*, m.nombre AS medico_nombre
    FROM registros_medicos r
    JOIN medicos m ON m.id_medico = r.id_medico
    WHERE r.id_paciente = p_id_paciente
    ORDER BY r.creado_en DESC;
END$$

CREATE PROCEDURE sp_pacientes_por_medico (IN p_id_medico INT)
BEGIN
    SELECT DISTINCT p.id_paciente, p.nombre, p.telefono, p.fecha_nacimiento
    FROM citas c
    JOIN pacientes p ON p.id_paciente = c.id_paciente
    WHERE c.id_medico = p_id_medico
    ORDER BY p.nombre;
END$$

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


-- =====================================================================
-- DATOS DE PRUEBA
-- =====================================================================

-- Contraseña real para TODOS los usuarios de prueba: "Password123!"
-- Hash generado con password_hash() de PHP (bcrypt)
INSERT INTO usuarios (nombre, email, password_hash, rol, telefono) VALUES
('Administrador General', 'admin@consultorio.com', '$2y$10$CZboetmDQ9Os241JB4Qop.blWXZbMCKYSz64FndS5gTnq8CHM9z8W', 'admin', '6000-0000'),
('Ana Torres', 'ana.torres@mail.com', '$2y$10$CZboetmDQ9Os241JB4Qop.blWXZbMCKYSz64FndS5gTnq8CHM9z8W', 'paciente', '6111-1111'),
('Luis Pérez', 'luis.perez@mail.com', '$2y$10$CZboetmDQ9Os241JB4Qop.blWXZbMCKYSz64FndS5gTnq8CHM9z8W', 'paciente', '6222-2222'),
('Dr. Carlos Méndez', 'carlos.mendez@consultorio.com', '$2y$10$CZboetmDQ9Os241JB4Qop.blWXZbMCKYSz64FndS5gTnq8CHM9z8W', 'medico', '6333-3333');

INSERT INTO especialidades (nombre, descripcion) VALUES
('Medicina General', 'Consulta general y chequeos de rutina'),
('Pediatría', 'Atención médica infantil'),
('Cardiología', 'Enfermedades del corazón'),
('Dermatología', 'Piel, cabello y uñas');

INSERT INTO medicos (nombre, id_especialidad, email, telefono, id_usuario) VALUES
('Dr. Carlos Méndez', 1, 'carlos.mendez@consultorio.com', '6333-3333', 4),
('Dra. Sofía Ramírez', 2, 'sofia.ramirez@consultorio.com', '6444-4444', NULL),
('Dr. Ricardo Núñez', 3, 'ricardo.nunez@consultorio.com', '6555-5555', NULL);

-- Horarios laborales (1=Lunes ... 5=Viernes)
INSERT INTO horarios_medico (id_medico, dia_semana, hora_inicio, hora_fin) VALUES
(1, 1, '08:00:00', '12:00:00'),
(1, 2, '08:00:00', '12:00:00'),
(1, 3, '08:00:00', '12:00:00'),
(1, 4, '13:00:00', '17:00:00'),
(1, 5, '08:00:00', '12:00:00'),
(2, 1, '09:00:00', '15:00:00'),
(2, 3, '09:00:00', '15:00:00'),
(2, 5, '09:00:00', '15:00:00'),
(3, 2, '10:00:00', '16:00:00'),
(3, 4, '10:00:00', '16:00:00');

INSERT INTO pacientes (id_usuario, nombre, fecha_nacimiento, telefono, direccion) VALUES
(2, 'Ana Torres', '1995-04-12', '6111-1111', 'San Francisco, Ciudad de Panamá'),
(3, 'Luis Pérez', '1988-09-30', '6222-2222', 'El Cangrejo, Ciudad de Panamá');

-- Una cita de prueba ya existente (para poder demostrar el traslape en la sustentación)
INSERT INTO citas (id_paciente, id_medico, fecha_cita, hora_inicio, hora_fin, estado, motivo) VALUES
(1, 1, CURDATE() + INTERVAL (1 - WEEKDAY(CURDATE()) + 7) % 7 DAY, '09:00:00', '09:30:00', 'confirmada', 'Chequeo general de rutina');
