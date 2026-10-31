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

Resultado: **12 casos, 12 superados.**

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

Pendiente: prueba de integración del traslado (requiere seleccionar el custodio anterior) y evaluación antes y después con usuarios reales.
