# Pruebas

## Ambiente

| Elemento | Versión |
|---|---|
| GLPI | 11.0.11 (paquete oficial) |
| PHP | 8.3.6 (CLI y servidor embebido) |
| Base de datos | Servidor compatible con MySQL 8.0.33 (ambiente aislado de desarrollo) |
| Navegador de prueba | Chromium (Playwright), 1366 × 900 |
| Fecha de ejecución | 31 de octubre de 2026 |
| Datos | Ficticios: 3 usuarios (Pérez Ana, Gómez Luis, técnico), 3 computadores y 1 monitor; un computador sin número de inventario a propósito |

> El ambiente de desarrollo usó un servidor compatible con MySQL porque el aislado no permitía instalar MariaDB. Para la evaluación antes y después y la sustentación se recomienda repetir estas pruebas con `docker compose up -d` (GLPI 11.0.11 + MariaDB 10.11).

## Pruebas unitarias (`tests/ActaBuilderTest.php`)

Resultado: **20 pruebas, 28 aserciones, 0 fallidas.**

| ID | Prueba | Entrada | Resultado esperado | Obtenido |
|---|---|---|---|---|
| U-01 | Acta válida | 2 activos completos asignados a quien recibe | Sin errores | ✔ |
| U-02 | Tipo no permitido | tipo = «prestamo» | «Tipo de acta no válido.» | ✔ |
| U-03 | Misma persona | entrega = recibe | Error de personas distintas | ✔ |
| U-04 | Sin activos | lista vacía | «El acta debe incluir al menos un activo.» | ✔ |
| U-05 | Serie vacía | serial = '' | «falta número de serie» | ✔ |
| U-06 | Inventario vacío | otherserial = '  ' | «falta número de inventario» | ✔ |
| U-07 | Activo repetido | mismo activo dos veces | «activo repetido en el acta» | ✔ |
| U-08 | Entrega inconsistente | activo asignado a otro usuario | «no está asignado a quien lo recibe» | ✔ |
| U-09 | Devolución | activo asignado a quien devuelve / a otro | Válida / «no está asignado a quien lo devuelve» | ✔ |
| U-10 | Traslado | activo ya reasignado a quien recibe | Válida | ✔ |
| U-11 | Consecutivo por tipo | (entrega, 2026, 123), (devolución, 2026, 7), (traslado, 2027, 1) | AE-2026-000123, AD-2026-000007, AT-2027-000001 | ✔ |
| U-12 | Formato de huella | acta válida | 64 hexadecimales | ✔ |
| U-13 | Huella estable | claves y activos en otro orden | Misma huella | ✔ |
| U-14 | Huella ignora datos ajenos | cambia `users_id_asignado` y `tipo_activo` | Misma huella | ✔ |
| U-15 | Verificación correcta | huella propia (minúsculas y mayúsculas) | Verdadero | ✔ |
| U-16 | Alteración de un carácter | serial 5CD1234XYZ → 5CD1234XYY | Falso | ✔ |
| U-17 | Cambio de receptor | otro nombre de quien recibe | Falso | ✔ |
| U-18 | Tildes | «Técnico Soporte» | Se conservan sin escapes | ✔ |
| U-19 | Completitud | 12/12 y 11/12 campos | 100,0 % y 91,7 % | ✔ |
| U-20 | Asignado esperado | entrega, devolución, traslado, otro | 12, 3, 12, null | ✔ |

## Pruebas de integración en GLPI (navegador)

Resultado: **16 casos, 16 superados.**

| ID | Caso | Pasos | Resultado esperado | Obtenido | Evidencia |
|---|---|---|---|---|---|
| I-01 | Pestaña del usuario | Abrir Pérez Ana › Actas | Lista sus 3 activos; marca en amarillo el que no tiene inventario | ✔ | [01](img/01_pestana_usuario.png) |
| I-02 | Rechazo por inconsistencia | Seleccionar los 3 activos y generar | No se genera; mensaje «PC-ADM-052: falta número de inventario» | ✔ | [02](img/02_validacion_rechaza.png) |
| I-03 | Generación | Seleccionar los 2 activos completos, observación, generar | Acta AE-2026-000001, PDF en Documentos, «Integridad verificada», SHA-256 del archivo = huella_pdf | ✔ | [03](img/03_acta_generada.png) |
| I-04 | Verificación pública válida | Abrir la URL del QR sin sesión | «Acta auténtica e íntegra», sin nombres | ✔ | [05](img/05_verificacion_valida.png) |
| I-05 | Documento falsificado | URL con huella distinta | «Acta NO válida» | ✔ | [06](img/06_verificacion_huella_falsa.png) |
| I-06 | Manipulación en la base de datos | UPDATE directo del serial de un ítem | «Acta NO válida: los datos… fueron modificados»; al restaurar vuelve a ser válida | ✔ | [07](img/07_verificacion_bd_manipulada.png) |
| I-07 | Edición genérica | POST `update` con observaciones nuevas | Rechazado; observaciones sin cambio | ✔ | — |
| I-08 | Borrado | POST `purge` | Rechazado; el acta sigue existiendo | ✔ | — |
| I-09 | Anular sin motivo | POST `anular` con motivo vacío (saltando la validación del navegador) | Estado sigue «generada» | ✔ | — |
| I-10 | Historial del activo | PC-ADM-041 › Actas | Aparece AE-2026-000001 | ✔ | [08](img/08_historial_activo.png) |
| I-11 | Firma y bitácora | Marcar como firmada | Estado «firmada»; bitácora con generación, verificaciones y firma | ✔ | [10](img/10_ficha_firmada_bitacora.png) |
| I-12 | Devolución | Gómez Luis › Actas › Devolución, estado «Regular» | Acta AD-2026-000001 con texto de devolución | ✔ | [PDF](img/04_pdf_devolucion.png) |

| I-13 | Panel de gestión | Herramientas › Actas de entrega | Indicadores, pendientes por antigüedad, cobertura 4/12, listado de 4 actas | ✔ | [11](img/11_panel_gestion.png) |
| I-14 | Filtros y exportación | Estado «generada» + búsqueda «PC»; exportar CSV | 3 filas; CSV UTF-8 con 4 actas | ✔ | — |
| I-15 | Autogestión | Usuario simplificado (post-only) › Plugins › Actas de entrega | Ve solo su acta AE-2026-000002 y la descarga (PDF) | ✔ | [12](img/12_mis_actas_autogestion.png) |
| I-16 | Acceso indebido | El mismo usuario pide el PDF de un acta ajena y el panel | 403 en ambos casos | ✔ | — |

Pendiente: prueba de integración del traslado (requiere seleccionar el custodio anterior) y evaluación antes y después con usuarios reales.

## Procedimiento de la medición antes/después

Instrumento: hoja «Medición» (casos, mediciones, línea base y resumen) y plantilla de acta manual en Google Docs.

1. Cargar los datos sintéticos en el GLPI de pruebas: `mysql -u <usuario> -p <base_glpi> < tools/datos_prueba.sql`
   (3 usuarios ficticios y 8 activos: PC-01…04, MON-01…03, IMP-01; PC-03 sin n.º de inventario a propósito).
2. **Antes (manual):** para cada caso C01–C10, cronometrar desde que se abre una copia de la plantilla hasta que el acta queda lista para firmar, consultando los datos en GLPI y transcribiéndolos a mano.
3. **Después (plugin):** recargar los datos (paso 1) y repetir C01–C10 desde la pestaña «Actas» del usuario, cronometrando hasta que se abre el PDF.
4. Registrar por acta: tiempo, campos correctos (de 6 por activo) y errores de consistencia frente a GLPI.
5. Casos que requieren preparación en GLPI: C08 (reasignar MON-01 a usuario2 antes del traslado) y C06/C07 (devoluciones sobre activos aún asignados).
6. Ideal: un segundo técnico ejecuta la mitad de los casos para reducir el sesgo del evaluador (limitación señalada en el Informe crítico).

| Caso | Tipo | Usuario | Activos | Qué se prueba |
|---|---|---|---|---|
| C01 | Entrega | usuario1 | PC-01 | Entrega simple |
| C02 | Entrega | usuario1 | PC-01, MON-01 | Dos activos |
| C03 | Entrega | usuario2 | PC-02, MON-02, IMP-01 | Tres activos |
| C04 | Entrega | usuario2 | PC-03 | Activo sin n.º de inventario (el plugin bloquea) |
| C05 | Entrega | usuario1 | PC-02 | Activo asignado a otra persona (el plugin bloquea) |
| C06 | Devolución | usuario1 | PC-01 | Devolución simple |
| C07 | Devolución | usuario2 | PC-02, MON-02 | Devolución de dos activos |
| C08 | Traslado | usuario2 (anterior usuario1) | MON-01 | Traslado entre usuarios |
| C09 | Entrega | usuario3 | PC-04, MON-03 | Serie que a mano se transcribe |
| C10 | Entrega | usuario3 | PC-04, MON-03 (+ duplicado) | Activo repetido (el plugin no lo permite) |
