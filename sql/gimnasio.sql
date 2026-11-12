-- =====================================================================
-- gimnasio.sql — Crea la base de datos, las tablas y datos de ejemplo.
-- Se ejecuta en phpMyAdmin (pestaña "SQL") o desde la consola de MySQL.
-- ATENCIÓN: si la base "gimnasio" ya existe, este script la borra y la recrea.
-- =====================================================================

DROP DATABASE IF EXISTS gimnasio;
CREATE DATABASE gimnasio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gimnasio;

-- ---------------------------------------------------------------------
-- 1) USUARIOS DEL SISTEMA (cuentas para iniciar sesión)
--    rol: 'usuario' (panel personal) o 'admin' (acceso administrativo)
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(60)  NOT NULL,
    apellido      VARCHAR(60)  NOT NULL,
    dni           VARCHAR(8)   NOT NULL UNIQUE,            -- no se puede repetir
    email         VARCHAR(120) NOT NULL UNIQUE,            -- no se puede repetir
    contrasena    VARCHAR(255) NOT NULL,                   -- hash de password_hash(), nunca texto plano
    rol           ENUM('usuario', 'admin') NOT NULL DEFAULT 'usuario',
    creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archivado_en  DATETIME NULL DEFAULT NULL               -- NULL = activa; con fecha = archivada
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 2) ENTRENADORES
-- ---------------------------------------------------------------------
CREATE TABLE entrenadores (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(60)  NOT NULL,
    apellido      VARCHAR(60)  NOT NULL,
    dni           VARCHAR(8)   NOT NULL UNIQUE,
    email         VARCHAR(120) NULL,
    telefono      VARCHAR(30)  NULL,
    especialidad  VARCHAR(100) NULL,
    creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archivado_en  DATETIME NULL DEFAULT NULL
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 3) SOCIOS (cada socio puede tener un entrenador asignado)
-- ---------------------------------------------------------------------
CREATE TABLE socios (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre         VARCHAR(60)  NOT NULL,
    apellido       VARCHAR(60)  NOT NULL,
    dni            VARCHAR(8)   NOT NULL UNIQUE,
    email          VARCHAR(120) NULL,
    telefono       VARCHAR(30)  NULL,
    entrenador_id  INT UNSIGNED NULL,
    creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archivado_en   DATETIME NULL DEFAULT NULL,
    CONSTRAINT fk_socio_entrenador FOREIGN KEY (entrenador_id) REFERENCES entrenadores (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 4) PLANES (mensual = 1 mes, trimestral = 3, anual = 12)
-- ---------------------------------------------------------------------
CREATE TABLE planes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(50)   NOT NULL UNIQUE,
    duracion_meses  TINYINT UNSIGNED NOT NULL,
    precio          DECIMAL(10,2) NOT NULL,
    descripcion     VARCHAR(255)  NULL,
    creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archivado_en    DATETIME NULL DEFAULT NULL,
    CONSTRAINT chk_plan_duracion CHECK (duracion_meses IN (1, 3, 12)),
    CONSTRAINT chk_plan_precio CHECK (precio >= 0)
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 5) MEMBRESÍAS (plan asignado a un socio, con su vencimiento)
--    'precio' guarda el valor pactado en ese momento.
-- ---------------------------------------------------------------------
CREATE TABLE membresias (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    socio_id           INT UNSIGNED  NOT NULL,
    plan_id            INT UNSIGNED  NOT NULL,
    precio             DECIMAL(10,2) NOT NULL,
    fecha_inicio       DATE NOT NULL,
    fecha_vencimiento  DATE NOT NULL,
    cancelada_en       DATETIME NULL DEFAULT NULL,   -- NULL = activa; con fecha = se cambió a otro plan
    creado_en          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_membresia_socio FOREIGN KEY (socio_id) REFERENCES socios (id),
    CONSTRAINT fk_membresia_plan FOREIGN KEY (plan_id) REFERENCES planes (id),
    CONSTRAINT chk_membresia_fechas CHECK (fecha_vencimiento > fecha_inicio),
    CONSTRAINT chk_membresia_precio CHECK (precio >= 0)
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 6) PAGOS (pueden estar asociados a una membresía)
-- ---------------------------------------------------------------------
CREATE TABLE pagos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    socio_id        INT UNSIGNED  NOT NULL,
    membresia_id    INT UNSIGNED  NULL,
    monto           DECIMAL(10,2) NOT NULL,
    fecha_pago      DATE NOT NULL,
    metodo          ENUM('efectivo', 'transferencia', 'tarjeta') NOT NULL DEFAULT 'efectivo',
    estado          ENUM('pagado', 'pendiente') NOT NULL DEFAULT 'pagado',
    observaciones   VARCHAR(255) NULL,
    creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pago_socio FOREIGN KEY (socio_id) REFERENCES socios (id),
    CONSTRAINT fk_pago_membresia FOREIGN KEY (membresia_id) REFERENCES membresias (id),
    CONSTRAINT chk_pago_monto CHECK (monto > 0)
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 7) RUTINAS DE ENTRENAMIENTO (asignadas a un socio)
-- ---------------------------------------------------------------------
-- socio_id NULL = rutina del catálogo general (la pueden elegir los usuarios)
CREATE TABLE rutinas (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    socio_id       INT UNSIGNED NULL,
    entrenador_id  INT UNSIGNED NULL,
    nombre         VARCHAR(100) NOT NULL,
    objetivo       VARCHAR(255) NULL,
    creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archivado_en   DATETIME NULL DEFAULT NULL,
    CONSTRAINT fk_rutina_socio FOREIGN KEY (socio_id) REFERENCES socios (id),
    CONSTRAINT fk_rutina_entrenador FOREIGN KEY (entrenador_id) REFERENCES entrenadores (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 8) EJERCICIOS de cada rutina (con series y repeticiones)
-- ---------------------------------------------------------------------
CREATE TABLE rutina_ejercicios (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rutina_id     INT UNSIGNED NOT NULL,
    ejercicio     VARCHAR(100) NOT NULL,
    series        TINYINT UNSIGNED  NOT NULL,
    repeticiones  SMALLINT UNSIGNED NOT NULL,
    CONSTRAINT fk_ejercicio_rutina FOREIGN KEY (rutina_id) REFERENCES rutinas (id) ON DELETE CASCADE,
    CONSTRAINT chk_ejercicio_series CHECK (series BETWEEN 1 AND 20),
    CONSTRAINT chk_ejercicio_reps CHECK (repeticiones BETWEEN 1 AND 500)
) ENGINE = InnoDB;

-- =====================================================================
-- DATOS DE EJEMPLO
-- Usuario administrador inicial:  admin@gimnasio.com  /  Admin1234
-- (La contraseña está guardada como hash; cambiala desde "Usuarios" al ingresar.)
-- =====================================================================
INSERT INTO usuarios (nombre, apellido, dni, email, contrasena, rol) VALUES
('Administrador', 'Gimnasio', '30000000', 'admin@gimnasio.com',
 '$2y$10$N.fN3KyO.BEGo0nMvNfILeo/9PYGCcFYwk166/NgTH78H25en7DXe', 'admin');

INSERT INTO planes (nombre, duracion_meses, precio, descripcion) VALUES
('Plan mensual',    1,   25000.00, 'Acceso a sala de musculación y cardio'),
('Plan trimestral', 3,   65000.00, 'Acceso completo durante 3 meses'),
('Plan anual',      12, 220000.00, 'Acceso completo durante 12 meses');

INSERT INTO entrenadores (nombre, apellido, dni, email, telefono, especialidad) VALUES
('Lucas', 'Pérez', '28123456', 'lucas@gimnasio.com', '11-5555-0001', 'Musculación');

INSERT INTO socios (nombre, apellido, dni, email, telefono, entrenador_id) VALUES
('Martín', 'Gómez',   '35111222', 'martin@mail.com', '11-5555-1111', 1),
('Lucía',  'Benítez', '40222333', NULL,              NULL,            NULL);

-- ---------------------------------------------------------------------
-- CATÁLOGO GENERAL DE RUTINAS (socio_id NULL)
-- ---------------------------------------------------------------------
INSERT INTO rutinas (socio_id, entrenador_id, nombre, objetivo) VALUES
(NULL, 1, 'Full body principiante', 'Tonificación general para empezar');
SET @r1 = LAST_INSERT_ID();
INSERT INTO rutina_ejercicios (rutina_id, ejercicio, series, repeticiones) VALUES
(@r1, 'Sentadilla con peso corporal', 3, 12),
(@r1, 'Flexiones de pecho', 3, 10),
(@r1, 'Remo con mancuerna', 3, 12),
(@r1, 'Plancha (segundos)', 3, 30);

INSERT INTO rutinas (socio_id, entrenador_id, nombre, objetivo) VALUES
(NULL, 1, 'Fuerza tren superior', 'Ganar fuerza en pecho, espalda y brazos');
SET @r2 = LAST_INSERT_ID();
INSERT INTO rutina_ejercicios (rutina_id, ejercicio, series, repeticiones) VALUES
(@r2, 'Press de banca', 4, 8),
(@r2, 'Dominadas', 4, 6),
(@r2, 'Press militar', 3, 10),
(@r2, 'Curl de bíceps', 3, 12);
