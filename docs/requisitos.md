# Matriz de requisitos

Estado al 31 de octubre de 2026 (versión 0.1.0). Las pruebas citadas están en [pruebas.md](pruebas.md).

## Requisitos funcionales

| ID | Requisito | Criterio de aceptación | Prioridad | Estado | Prueba |
|---|---|---|---|---|---|
| RF-01 | Listar los activos asignados a un usuario desde el inventario de GLPI | La pestaña Actas del usuario muestra todos los activos de tipos asignables con `users_id` = usuario, sin borrados ni plantillas, respetando las entidades del técnico | Crítica | Hecho | I-01 |
| RF-02 | Validar la consistencia inventario-acta antes de generar | No se genera el acta si un activo no tiene serie o inventario, está repetido o no está asignado a la persona que corresponde según el tipo; se informa cada causa | Crítica | Hecho | U-01…U-10, I-02 |
| RF-03 | Generar el acta con número consecutivo por tipo y año | Números AE/AD/AT-AAAA-NNNNNN sin repetición, incluso con generaciones simultáneas (clave única) | Crítica | Hecho | U-11, I-03 |
| RF-04 | Calcular la huella SHA-256 del contenido canónico | Huella de 64 hexadecimales, independiente del orden de los datos; cambia con cualquier alteración | Crítica | Hecho | U-12…U-18 |
| RF-05 | Generar el PDF con datos, firmas, huella y QR | PDF con texto real, tabla de activos, declaración según el tipo, espacios de firma, huella y QR de verificación | Crítica | Hecho | I-03, I-12 |
| RF-06 | Guardar el PDF en Documentos de GLPI y vincularlo | Documento vinculado al acta, a quien entrega, a quien recibe y a cada activo | Crítica | Hecho | I-03 |
| RF-07 | Conservar una copia de los datos del activo al momento del acta | El acta muestra los datos entregados aunque el activo cambie después en GLPI | Importante | Hecho | I-03 |
| RF-08 | Impedir la modificación y el borrado de un acta generada | Las ediciones y los borrados se rechazan; solo se permiten generada → firmada → anulada | Importante | Hecho | I-07, I-08 |
| RF-09 | Anular con motivo obligatorio | Sin motivo no hay anulación; el motivo queda en el acta y en la bitácora | Importante | Hecho | I-09 |
| RF-10 | Actas de devolución y traslado | Tipos devolución y traslado con su regla de consistencia y su texto en el PDF | Importante | Devolución hecha; traslado con pruebas unitarias, falta prueba de integración | U-09, U-10, I-12 |
| RF-11 | Verificar la integridad de un acta | La ficha y la página pública detectan huella falsa y datos alterados en la base de datos | Deseable | Hecho | I-04…I-06 |
| RF-12 | Historial de actas por activo | Pestaña Actas en la ficha del activo con todos sus movimientos | Deseable | Hecho | I-10 |
| RF-13 | Bitácora de eventos | Se registran generación, firma, anulación y cada verificación pública | Deseable | Hecho | I-11 |
| RF-14 | Indicadores (tiempo por acta, completitud, consistencia) | Reporte de indicadores para la evaluación antes y después | Deseable | Parcial: el panel muestra estados, antigüedad y cobertura; la completitud queda en la bitácora | I-13 |
| RF-15 | Panel de gestión de todas las actas | Una sola vista con indicadores (generadas, pendientes de firma, firmadas en el mes, cobertura de activos), pendientes por antigüedad, listado con búsqueda y filtros por estado, tipo y periodo, y exportación CSV | Importante | Hecho | I-13, I-14 |
| RF-16 | Autogestión del usuario («Mis actas») | El usuario de la interfaz simplificada ve y descarga solo sus propias actas; el acceso a actas ajenas se rechaza | Importante | Hecho | I-15, I-16 |

## Requisitos no funcionales

| ID | Requisito | Criterio de aceptación | Estado |
|---|---|---|---|
| RNF-01 | No modificar el núcleo de GLPI | El plugin solo usa la API de complementos (setup.php, hook.php, clases en `src/`) | Hecho |
| RNF-02 | Compatibilidad | GLPI 11.0.x, PHP ≥ 8.2 | Hecho (probado en 11.0.11 con PHP 8.3) |
| RNF-03 | Seguridad | Derechos por perfil, token CSRF en formularios, consultas con el iterador de GLPI, escape de salida en Twig y PDF | Hecho |
| RNF-04 | Protección de datos (Ley 1581 de 2012) | La verificación pública no muestra nombres ni datos de activos; el acta no incluye documento de identidad | Hecho |
| RNF-05 | Sin dependencias nuevas | PDF y QR con la TCPDF incluida en GLPI | Hecho |
| RNF-06 | Mantenibilidad | Lógica de dominio aislada (`ActaBuilder`) con pruebas unitarias | Hecho (20 pruebas) |
| RNF-07 | Accesibilidad del PDF | Texto seleccionable, tamaño ≥ 7,5 pt, tabla con encabezados | Hecho |
