-- =====================================================================
-- ACTUALIZACIÓN: catálogo general de rutinas.
-- Ejecutar UNA sola vez sobre la base gimnasio ya existente (sin borrar datos).
-- =====================================================================
USE gimnasio;

ALTER TABLE rutinas MODIFY socio_id INT UNSIGNED NULL;

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
