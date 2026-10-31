-- =====================================================================
-- Datos sintéticos para la medición antes/después del plugin actasentrega
-- (GLPI 11.0.x). Uso exclusivo en ambiente de pruebas.
--
-- Justificación metodológica: por protección de datos (Ley 1581 de 2012)
-- la evaluación usa usuarios y activos ficticios con la misma estructura
-- del inventario real. Corresponde a la hoja «Casos de prueba» (C01–C10).
--
-- Ejecutar:  mysql -u <usuario> -p <base_glpi> < tools/datos_prueba.sql
-- Es idempotente: borra y vuelve a crear solo los registros con prefijo
-- de prueba (usuarioN, PC-0N, MON-0N, IMP-0N).
-- =====================================================================

SET @ahora = NOW();

-- Limpieza de una carga anterior
DELETE FROM glpi_computers   WHERE name IN ('PC-01','PC-02','PC-03','PC-04');
DELETE FROM glpi_monitors    WHERE name IN ('MON-01','MON-02','MON-03');
DELETE FROM glpi_printers    WHERE name IN ('IMP-01');
DELETE FROM glpi_users       WHERE name IN ('usuario1','usuario2','usuario3');

-- Fabricantes y modelos (se reutilizan si ya existen)
INSERT INTO glpi_manufacturers (name, date_creation, date_mod)
  SELECT 'HP', @ahora, @ahora FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM glpi_manufacturers WHERE name = 'HP');
INSERT INTO glpi_manufacturers (name, date_creation, date_mod)
  SELECT 'Dell', @ahora, @ahora FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM glpi_manufacturers WHERE name = 'Dell');
INSERT INTO glpi_manufacturers (name, date_creation, date_mod)
  SELECT 'Epson', @ahora, @ahora FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM glpi_manufacturers WHERE name = 'Epson');
INSERT INTO glpi_computermodels (name, date_creation, date_mod)
  SELECT 'ProDesk 400 G7', @ahora, @ahora FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM glpi_computermodels WHERE name = 'ProDesk 400 G7');
INSERT INTO glpi_monitormodels (name, date_creation, date_mod)
  SELECT 'P2422H', @ahora, @ahora FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM glpi_monitormodels WHERE name = 'P2422H');
INSERT INTO glpi_printermodels (name, date_creation, date_mod)
  SELECT 'EcoTank L3250', @ahora, @ahora FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM glpi_printermodels WHERE name = 'EcoTank L3250');

SET @hp    = (SELECT id FROM glpi_manufacturers WHERE name = 'HP' LIMIT 1);
SET @dell  = (SELECT id FROM glpi_manufacturers WHERE name = 'Dell' LIMIT 1);
SET @epson = (SELECT id FROM glpi_manufacturers WHERE name = 'Epson' LIMIT 1);
SET @mpc   = (SELECT id FROM glpi_computermodels WHERE name = 'ProDesk 400 G7' LIMIT 1);
SET @mmon  = (SELECT id FROM glpi_monitormodels WHERE name = 'P2422H' LIMIT 1);
SET @mimp  = (SELECT id FROM glpi_printermodels WHERE name = 'EcoTank L3250' LIMIT 1);

-- Usuarios ficticios (sin clave: no pueden iniciar sesión)
INSERT INTO glpi_users (name, realname, firstname, is_active, entities_id, authtype, date_creation, date_mod)
VALUES ('usuario1', 'Uno',  'Usuario', 1, 0, 1, @ahora, @ahora),
       ('usuario2', 'Dos',  'Usuario', 1, 0, 1, @ahora, @ahora),
       ('usuario3', 'Tres', 'Usuario', 1, 0, 1, @ahora, @ahora);
SET @u1 = (SELECT id FROM glpi_users WHERE name = 'usuario1');
SET @u2 = (SELECT id FROM glpi_users WHERE name = 'usuario2');
SET @u3 = (SELECT id FROM glpi_users WHERE name = 'usuario3');

-- Activos en su estado inicial (antes de C01)
--   PC-03 no tiene número de inventario (caso C04)
--   PC-02 está asignado a usuario2 (caso C05: se intenta entregar a usuario1)
INSERT INTO glpi_computers (name, serial, otherserial, users_id, manufacturers_id, computermodels_id, entities_id, date_creation, date_mod) VALUES
  ('PC-01', '5CD0001AAA', 'INV-1001', @u1, @hp, @mpc, 0, @ahora, @ahora),
  ('PC-02', '5CD0002BBB', 'INV-1002', @u2, @hp, @mpc, 0, @ahora, @ahora),
  ('PC-03', '5CD0003CCC', '',         @u2, @hp, @mpc, 0, @ahora, @ahora),
  ('PC-04', '5CD0004DDD', 'INV-1004', @u3, @hp, @mpc, 0, @ahora, @ahora);
INSERT INTO glpi_monitors (name, serial, otherserial, users_id, manufacturers_id, monitormodels_id, entities_id, date_creation, date_mod) VALUES
  ('MON-01', 'CN0MON0001', 'INV-2001', @u1, @dell, @mmon, 0, @ahora, @ahora),
  ('MON-02', 'CN0MON0002', 'INV-2002', @u2, @dell, @mmon, 0, @ahora, @ahora),
  ('MON-03', 'CN0MON0003', 'INV-2003', @u3, @dell, @mmon, 0, @ahora, @ahora);
INSERT INTO glpi_printers (name, serial, otherserial, users_id, manufacturers_id, printermodels_id, entities_id, date_creation, date_mod) VALUES
  ('IMP-01', 'X8JK000001', 'INV-3001', @u2, @epson, @mimp, 0, @ahora, @ahora);

SELECT 'Datos de prueba cargados' AS resultado,
       (SELECT COUNT(*) FROM glpi_users WHERE name LIKE 'usuario_') AS usuarios,
       (SELECT COUNT(*) FROM glpi_computers WHERE name LIKE 'PC-0_') +
       (SELECT COUNT(*) FROM glpi_monitors WHERE name LIKE 'MON-0_') +
       (SELECT COUNT(*) FROM glpi_printers WHERE name LIKE 'IMP-0_') AS activos;
