-- =====================================================================
-- ACTUALIZACIÓN: un solo plan activo por socio (cambio de plan).
-- Ejecutar UNA sola vez sobre la base gimnasio ya existente (no borra datos).
-- =====================================================================
USE gimnasio;

ALTER TABLE membresias
    ADD COLUMN cancelada_en DATETIME NULL DEFAULT NULL AFTER fecha_vencimiento;
