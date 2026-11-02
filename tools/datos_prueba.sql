-- =====================================================================
-- Datos sintéticos para la medición antes/después del plugin actasentrega
-- (GLPI 11.0.x). Uso exclusivo en ambiente de pruebas.
--
-- Justificación metodológica: por protección de datos (Ley 1581 de 2012)
-- la evaluación usa usuarios y activos ficticios con la misma estructura
-- del inventario real. Corresponde a la hoja «Casos de prueba» (C01–C10).
-- Los mismos datos se usan en la ronda manual (formato en hoja de cálculo)
-- y en la ronda con el plugin, para que la comparación sea válida.
--
-- Ejecutar:  mysql -u <usuario> -p <base_glpi> < tools/datos_prueba.sql
--
-- Se puede ejecutar varias veces: crea lo que falte y devuelve usuarios y
-- activos de prueba a su estado inicial (antes de C01) sin cambiar sus
-- identificadores, así que las actas ya generadas siguen apuntando a ellos.
-- Solo toca registros de prueba (usuarioN, PC-0N, MON-0N, IMP-0N).
-- =====================================================================

SET @ahora = NOW();

-- ---------------------------------------------------------------------
-- Catálogos (se reutilizan si ya existen)
-- ---------------------------------------------------------------------
INSERT INTO glpi_manufacturers (name, date_creation, date_mod)
  SELECT v.n, @ahora, @ahora FROM (SELECT 'HP' AS n UNION SELECT 'Dell' UNION SELECT 'Epson' UNION SELECT 'Intel' UNION SELECT 'Microsoft') v
  WHERE NOT EXISTS (SELECT 1 FROM glpi_manufacturers m WHERE m.name = v.n);
INSERT INTO glpi_computermodels (name, date_creation, date_mod)
  SELECT v.n, @ahora, @ahora FROM (SELECT 'ProDesk 400 G7' AS n UNION SELECT 'Latitude 5540') v
  WHERE NOT EXISTS (SELECT 1 FROM glpi_computermodels m WHERE m.name = v.n);
INSERT INTO glpi_computertypes (name, date_creation, date_mod)
  SELECT v.n, @ahora, @ahora FROM (SELECT 'PC de escritorio' AS n UNION SELECT 'Portátil') v
  WHERE NOT EXISTS (SELECT 1 FROM glpi_computertypes m WHERE m.name = v.n);
INSERT INTO glpi_monitormodels (name, date_creation, date_mod)
  SELECT 'P2422H', @ahora, @ahora FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM glpi_monitormodels WHERE name = 'P2422H');
INSERT INTO glpi_printermodels (name, date_creation, date_mod)
  SELECT 'EcoTank L3250', @ahora, @ahora FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM glpi_printermodels WHERE name = 'EcoTank L3250');

-- Personas: cargo (título), dependencia (grupo) y ubicación (sede)
INSERT INTO glpi_usertitles (name, date_creation, date_mod)
  SELECT v.n, @ahora, @ahora FROM (SELECT 'Auxiliar contable' AS n UNION SELECT 'Analista de nómina' UNION SELECT 'Asesor comercial') v
  WHERE NOT EXISTS (SELECT 1 FROM glpi_usertitles m WHERE m.name = v.n);
INSERT INTO glpi_groups (name, completename, entities_id, is_recursive, level, date_creation, date_mod)
  SELECT v.n, v.n, 0, 1, 1, @ahora, @ahora FROM (SELECT 'Contabilidad' AS n UNION SELECT 'Talento Humano' UNION SELECT 'Comercial') v
  WHERE NOT EXISTS (SELECT 1 FROM glpi_groups m WHERE m.name = v.n);
INSERT INTO glpi_locations (name, completename, entities_id, is_recursive, level, date_creation, date_mod)
  SELECT v.n, v.n, 0, 1, 1, @ahora, @ahora FROM (SELECT 'Sede Principal' AS n UNION SELECT 'Sucursal Norte') v
  WHERE NOT EXISTS (SELECT 1 FROM glpi_locations m WHERE m.name = v.n);

-- Componentes, sistema operativo y red
INSERT INTO glpi_devicememorytypes (name, date_creation, date_mod)
  SELECT v.n, @ahora, @ahora FROM (SELECT 'DDR4' AS n UNION SELECT 'DDR5') v
  WHERE NOT EXISTS (SELECT 1 FROM glpi_devicememorytypes m WHERE m.name = v.n);
INSERT INTO glpi_deviceharddrivetypes (name)
  SELECT v.n FROM (SELECT 'SSD' AS n UNION SELECT 'HDD') v
  WHERE NOT EXISTS (SELECT 1 FROM glpi_deviceharddrivetypes m WHERE m.name = v.n);
INSERT INTO glpi_interfacetypes (name, date_creation, date_mod)
  SELECT 'NVMe', @ahora, @ahora FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM glpi_interfacetypes WHERE name = 'NVMe');
INSERT INTO glpi_operatingsystems (name, date_creation, date_mod)
  SELECT v.n, @ahora, @ahora FROM (SELECT 'Windows 11' AS n UNION SELECT 'Windows 10') v
  WHERE NOT EXISTS (SELECT 1 FROM glpi_operatingsystems m WHERE m.name = v.n);
INSERT INTO glpi_operatingsystemeditions (name, date_creation, date_mod)
  SELECT 'Pro', @ahora, @ahora FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM glpi_operatingsystemeditions WHERE name = 'Pro');
INSERT INTO glpi_operatingsystemversions (name, date_creation, date_mod)
  SELECT v.n, @ahora, @ahora FROM (SELECT '23H2' AS n UNION SELECT '22H2') v
  WHERE NOT EXISTS (SELECT 1 FROM glpi_operatingsystemversions m WHERE m.name = v.n);
INSERT INTO glpi_operatingsystemarchitectures (name, date_creation, date_mod)
  SELECT '64 bits', @ahora, @ahora FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM glpi_operatingsystemarchitectures WHERE name = '64 bits');

SET @hp    = (SELECT id FROM glpi_manufacturers WHERE name = 'HP' LIMIT 1);
SET @dell  = (SELECT id FROM glpi_manufacturers WHERE name = 'Dell' LIMIT 1);
SET @epson = (SELECT id FROM glpi_manufacturers WHERE name = 'Epson' LIMIT 1);
SET @intel = (SELECT id FROM glpi_manufacturers WHERE name = 'Intel' LIMIT 1);
SET @mpc   = (SELECT id FROM glpi_computermodels WHERE name = 'ProDesk 400 G7' LIMIT 1);
SET @mlap  = (SELECT id FROM glpi_computermodels WHERE name = 'Latitude 5540' LIMIT 1);
SET @tesc  = (SELECT id FROM glpi_computertypes WHERE name = 'PC de escritorio' LIMIT 1);
SET @tpor  = (SELECT id FROM glpi_computertypes WHERE name = 'Portátil' LIMIT 1);
SET @mmon  = (SELECT id FROM glpi_monitormodels WHERE name = 'P2422H' LIMIT 1);
SET @mimp  = (SELECT id FROM glpi_printermodels WHERE name = 'EcoTank L3250' LIMIT 1);

-- ---------------------------------------------------------------------
-- Usuarios ficticios (sin clave: no pueden iniciar sesión)
-- ---------------------------------------------------------------------
INSERT INTO glpi_users (name, realname, firstname, is_active, entities_id, authtype, date_creation, date_mod)
  SELECT v.n, v.r, 'Usuario', 1, 0, 1, @ahora, @ahora
  FROM (SELECT 'usuario1' AS n, 'Uno' AS r UNION SELECT 'usuario2', 'Dos' UNION SELECT 'usuario3', 'Tres') v
  WHERE NOT EXISTS (SELECT 1 FROM glpi_users u WHERE u.name = v.n);
SET @u1 = (SELECT id FROM glpi_users WHERE name = 'usuario1');
SET @u2 = (SELECT id FROM glpi_users WHERE name = 'usuario2');
SET @u3 = (SELECT id FROM glpi_users WHERE name = 'usuario3');

UPDATE glpi_users SET
  usertitles_id = (SELECT id FROM glpi_usertitles WHERE name = 'Auxiliar contable' LIMIT 1),
  groups_id     = (SELECT id FROM glpi_groups WHERE name = 'Contabilidad' LIMIT 1),
  locations_id  = (SELECT id FROM glpi_locations WHERE name = 'Sede Principal' LIMIT 1)
WHERE id = @u1;
UPDATE glpi_users SET
  usertitles_id = (SELECT id FROM glpi_usertitles WHERE name = 'Analista de nómina' LIMIT 1),
  groups_id     = (SELECT id FROM glpi_groups WHERE name = 'Talento Humano' LIMIT 1),
  locations_id  = (SELECT id FROM glpi_locations WHERE name = 'Sede Principal' LIMIT 1)
WHERE id = @u2;
UPDATE glpi_users SET
  usertitles_id = (SELECT id FROM glpi_usertitles WHERE name = 'Asesor comercial' LIMIT 1),
  groups_id     = (SELECT id FROM glpi_groups WHERE name = 'Comercial' LIMIT 1),
  locations_id  = (SELECT id FROM glpi_locations WHERE name = 'Sucursal Norte' LIMIT 1)
WHERE id = @u3;

-- Pertenencia a los grupos (la dependencia también se ve en la ficha del grupo)
INSERT INTO glpi_groups_users (users_id, groups_id)
  SELECT u.id, u.groups_id FROM glpi_users u
  WHERE u.id IN (@u1, @u2, @u3) AND u.groups_id > 0
    AND NOT EXISTS (SELECT 1 FROM glpi_groups_users g WHERE g.users_id = u.id AND g.groups_id = u.groups_id);

-- ---------------------------------------------------------------------
-- Activos: se crean si faltan y se devuelven a su estado inicial (antes de C01)
--   PC-03 no tiene número de inventario (caso C04)
--   PC-02 está asignado a usuario2 (caso C05: se intenta entregar a usuario1)
-- ---------------------------------------------------------------------
INSERT INTO glpi_computers (name, entities_id, date_creation, date_mod)
  SELECT v.n, 0, @ahora, @ahora FROM (SELECT 'PC-01' AS n UNION SELECT 'PC-02' UNION SELECT 'PC-03' UNION SELECT 'PC-04') v
  WHERE NOT EXISTS (SELECT 1 FROM glpi_computers c WHERE c.name = v.n);
INSERT INTO glpi_monitors (name, entities_id, date_creation, date_mod)
  SELECT v.n, 0, @ahora, @ahora FROM (SELECT 'MON-01' AS n UNION SELECT 'MON-02' UNION SELECT 'MON-03') v
  WHERE NOT EXISTS (SELECT 1 FROM glpi_monitors c WHERE c.name = v.n);
INSERT INTO glpi_printers (name, entities_id, date_creation, date_mod)
  SELECT 'IMP-01', 0, @ahora, @ahora FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM glpi_printers WHERE name = 'IMP-01');

UPDATE glpi_computers SET serial = '5CD0001AAA', otherserial = 'INV-1001', users_id = @u1, manufacturers_id = @hp,   computermodels_id = @mpc,  computertypes_id = @tesc, is_deleted = 0 WHERE name = 'PC-01';
UPDATE glpi_computers SET serial = '5CD0002BBB', otherserial = 'INV-1002', users_id = @u2, manufacturers_id = @hp,   computermodels_id = @mpc,  computertypes_id = @tesc, is_deleted = 0 WHERE name = 'PC-02';
UPDATE glpi_computers SET serial = '5CD0003CCC', otherserial = '',         users_id = @u2, manufacturers_id = @hp,   computermodels_id = @mpc,  computertypes_id = @tesc, is_deleted = 0 WHERE name = 'PC-03';
UPDATE glpi_computers SET serial = 'DL5540XK29', otherserial = 'INV-1004', users_id = @u3, manufacturers_id = @dell, computermodels_id = @mlap, computertypes_id = @tpor, is_deleted = 0 WHERE name = 'PC-04';
UPDATE glpi_monitors SET serial = 'CN0MON0001', otherserial = 'INV-2001', users_id = @u1, manufacturers_id = @dell, monitormodels_id = @mmon, size = 24, is_deleted = 0 WHERE name = 'MON-01';
UPDATE glpi_monitors SET serial = 'CN0MON0002', otherserial = 'INV-2002', users_id = @u2, manufacturers_id = @dell, monitormodels_id = @mmon, size = 24, is_deleted = 0 WHERE name = 'MON-02';
UPDATE glpi_monitors SET serial = 'CN0MON0003', otherserial = 'INV-2003', users_id = @u3, manufacturers_id = @dell, monitormodels_id = @mmon, size = 27, is_deleted = 0 WHERE name = 'MON-03';
UPDATE glpi_printers SET serial = 'X8JK000001', otherserial = 'INV-3001', users_id = @u2, manufacturers_id = @epson, printermodels_id = @mimp, is_deleted = 0 WHERE name = 'IMP-01';

SET @pc1 = (SELECT id FROM glpi_computers WHERE name = 'PC-01' LIMIT 1);
SET @pc2 = (SELECT id FROM glpi_computers WHERE name = 'PC-02' LIMIT 1);
SET @pc3 = (SELECT id FROM glpi_computers WHERE name = 'PC-03' LIMIT 1);
SET @pc4 = (SELECT id FROM glpi_computers WHERE name = 'PC-04' LIMIT 1);

-- ---------------------------------------------------------------------
-- Especificaciones técnicas de los computadores
-- (procesador, memoria, disco, sistema operativo e IP; lo que pide el formato manual)
-- ---------------------------------------------------------------------
INSERT INTO glpi_deviceprocessors (designation, frequence, frequency_default, manufacturers_id, entities_id, is_recursive, date_creation, date_mod)
  SELECT v.d, v.f, v.f, @intel, 0, 1, @ahora, @ahora
  FROM (SELECT 'Intel Core i5-10500' AS d, 3100 AS f UNION SELECT 'Intel Core i3-10100', 3600 UNION SELECT 'Intel Core i5-1335U', 1300) v
  WHERE NOT EXISTS (SELECT 1 FROM glpi_deviceprocessors p WHERE p.designation = v.d);
INSERT INTO glpi_devicememories (designation, size_default, devicememorytypes_id, entities_id, is_recursive, date_creation, date_mod)
  SELECT v.d, v.s, (SELECT id FROM glpi_devicememorytypes WHERE name = v.t LIMIT 1), 0, 1, @ahora, @ahora
  FROM (SELECT 'Módulo DDR4 8 GB' AS d, 8192 AS s, 'DDR4' AS t UNION SELECT 'Módulo DDR5 8 GB', 8192, 'DDR5') v
  WHERE NOT EXISTS (SELECT 1 FROM glpi_devicememories m WHERE m.designation = v.d);
INSERT INTO glpi_deviceharddrives (designation, capacity_default, deviceharddrivetypes_id, interfacetypes_id, entities_id, is_recursive, date_creation, date_mod)
  SELECT v.d, v.c, (SELECT id FROM glpi_deviceharddrivetypes WHERE name = v.t LIMIT 1), (SELECT id FROM glpi_interfacetypes WHERE name = v.i LIMIT 1), 0, 1, @ahora, @ahora
  FROM (SELECT 'SSD SATA 256 GB' AS d, 256000 AS c, 'SSD' AS t, 'SATA' AS i
        UNION SELECT 'SSD NVMe 512 GB', 512000, 'SSD', 'NVMe'
        UNION SELECT 'HDD SATA 1 TB', 1000000, 'HDD', 'SATA') v
  WHERE NOT EXISTS (SELECT 1 FROM glpi_deviceharddrives h WHERE h.designation = v.d);

SET @cpu_i5   = (SELECT id FROM glpi_deviceprocessors WHERE designation = 'Intel Core i5-10500' LIMIT 1);
SET @cpu_i3   = (SELECT id FROM glpi_deviceprocessors WHERE designation = 'Intel Core i3-10100' LIMIT 1);
SET @cpu_i5u  = (SELECT id FROM glpi_deviceprocessors WHERE designation = 'Intel Core i5-1335U' LIMIT 1);
SET @ram4     = (SELECT id FROM glpi_devicememories WHERE designation = 'Módulo DDR4 8 GB' LIMIT 1);
SET @ram5     = (SELECT id FROM glpi_devicememories WHERE designation = 'Módulo DDR5 8 GB' LIMIT 1);
SET @ssd256   = (SELECT id FROM glpi_deviceharddrives WHERE designation = 'SSD SATA 256 GB' LIMIT 1);
SET @nvme512  = (SELECT id FROM glpi_deviceharddrives WHERE designation = 'SSD NVMe 512 GB' LIMIT 1);
SET @hdd1tb   = (SELECT id FROM glpi_deviceharddrives WHERE designation = 'HDD SATA 1 TB' LIMIT 1);

-- Se borran y recrean solo los componentes de los PC de prueba
DELETE FROM glpi_items_deviceprocessors WHERE itemtype = 'Computer' AND items_id IN (@pc1, @pc2, @pc3, @pc4);
DELETE FROM glpi_items_devicememories   WHERE itemtype = 'Computer' AND items_id IN (@pc1, @pc2, @pc3, @pc4);
DELETE FROM glpi_items_deviceharddrives WHERE itemtype = 'Computer' AND items_id IN (@pc1, @pc2, @pc3, @pc4);
DELETE FROM glpi_items_operatingsystems WHERE itemtype = 'Computer' AND items_id IN (@pc1, @pc2, @pc3, @pc4);

INSERT INTO glpi_items_deviceprocessors (itemtype, items_id, deviceprocessors_id, frequency, nbcores, nbthreads, entities_id) VALUES
  ('Computer', @pc1, @cpu_i5,  3100, 6, 12, 0),
  ('Computer', @pc2, @cpu_i5,  3100, 6, 12, 0),
  ('Computer', @pc3, @cpu_i3,  3600, 4, 8,  0),
  ('Computer', @pc4, @cpu_i5u, 1300, 10, 12, 0);
-- Memoria: PC-01 y PC-03 con un módulo (8 GB); PC-02 y PC-04 con dos (16 GB)
INSERT INTO glpi_items_devicememories (itemtype, items_id, devicememories_id, size, entities_id) VALUES
  ('Computer', @pc1, @ram4, 8192, 0),
  ('Computer', @pc2, @ram4, 8192, 0), ('Computer', @pc2, @ram4, 8192, 0),
  ('Computer', @pc3, @ram4, 8192, 0),
  ('Computer', @pc4, @ram5, 8192, 0), ('Computer', @pc4, @ram5, 8192, 0);
INSERT INTO glpi_items_deviceharddrives (itemtype, items_id, deviceharddrives_id, capacity, entities_id) VALUES
  ('Computer', @pc1, @ssd256,  256000,  0),
  ('Computer', @pc2, @nvme512, 512000,  0),
  ('Computer', @pc3, @hdd1tb,  1000000, 0),
  ('Computer', @pc4, @nvme512, 512000,  0);
INSERT INTO glpi_items_operatingsystems (itemtype, items_id, operatingsystems_id, operatingsystemeditions_id, operatingsystemversions_id, operatingsystemarchitectures_id, entities_id, date_creation, date_mod)
  SELECT 'Computer', v.pc,
         (SELECT id FROM glpi_operatingsystems WHERE name = v.so LIMIT 1),
         (SELECT id FROM glpi_operatingsystemeditions WHERE name = 'Pro' LIMIT 1),
         (SELECT id FROM glpi_operatingsystemversions WHERE name = v.ver LIMIT 1),
         (SELECT id FROM glpi_operatingsystemarchitectures WHERE name = '64 bits' LIMIT 1),
         0, @ahora, @ahora
  FROM (SELECT @pc1 AS pc, 'Windows 11' AS so, '23H2' AS ver UNION SELECT @pc2, 'Windows 11', '23H2'
        UNION SELECT @pc3, 'Windows 10', '22H2' UNION SELECT @pc4, 'Windows 11', '23H2') v;

-- Red: un puerto Ethernet por PC con su nombre de red e IPv4 fija
DELETE FROM glpi_ipaddresses WHERE mainitemtype = 'Computer' AND mainitems_id IN (@pc1, @pc2, @pc3, @pc4);
DELETE n FROM glpi_networknames n JOIN glpi_networkports p ON n.itemtype = 'NetworkPort' AND n.items_id = p.id
  WHERE p.itemtype = 'Computer' AND p.items_id IN (@pc1, @pc2, @pc3, @pc4);
DELETE FROM glpi_networkports WHERE itemtype = 'Computer' AND items_id IN (@pc1, @pc2, @pc3, @pc4);

INSERT INTO glpi_networkports (itemtype, items_id, entities_id, logical_number, name, instantiation_type, date_creation, date_mod)
  SELECT 'Computer', v.pc, 0, 1, 'Ethernet', 'NetworkPortEthernet', @ahora, @ahora
  FROM (SELECT @pc1 AS pc UNION SELECT @pc2 UNION SELECT @pc3 UNION SELECT @pc4) v;
INSERT INTO glpi_networknames (itemtype, items_id, entities_id, name, date_creation, date_mod)
  SELECT 'NetworkPort', p.id, 0, LOWER(c.name), @ahora, @ahora
  FROM glpi_networkports p JOIN glpi_computers c ON p.itemtype = 'Computer' AND p.items_id = c.id
  WHERE c.id IN (@pc1, @pc2, @pc3, @pc4);
INSERT INTO glpi_ipaddresses (itemtype, items_id, entities_id, version, name, binary_0, binary_1, binary_2, binary_3, mainitemtype, mainitems_id)
  SELECT 'NetworkName', n.id, 0, 4, v.ip, 0, 0, 65535, INET_ATON(v.ip), 'Computer', c.id
  FROM glpi_networknames n
  JOIN glpi_networkports p ON n.itemtype = 'NetworkPort' AND n.items_id = p.id
  JOIN glpi_computers c ON p.itemtype = 'Computer' AND p.items_id = c.id
  JOIN (SELECT 'PC-01' AS pc, '192.168.10.21' AS ip UNION SELECT 'PC-02', '192.168.10.22'
        UNION SELECT 'PC-03', '192.168.10.23' UNION SELECT 'PC-04', '192.168.10.24') v ON v.pc = c.name
  WHERE c.id IN (@pc1, @pc2, @pc3, @pc4);

SELECT 'Datos de prueba cargados' AS resultado,
       (SELECT COUNT(*) FROM glpi_users WHERE name IN ('usuario1','usuario2','usuario3')) AS usuarios,
       (SELECT COUNT(*) FROM glpi_computers WHERE name IN ('PC-01','PC-02','PC-03','PC-04')) +
       (SELECT COUNT(*) FROM glpi_monitors WHERE name IN ('MON-01','MON-02','MON-03')) +
       (SELECT COUNT(*) FROM glpi_printers WHERE name = 'IMP-01') AS activos,
       (SELECT COUNT(*) FROM glpi_ipaddresses WHERE mainitemtype = 'Computer' AND mainitems_id IN (@pc1, @pc2, @pc3, @pc4)) AS direcciones_ip;
