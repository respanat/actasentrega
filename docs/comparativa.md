# Comparativa con complementos existentes

**Referencia fija del estado del arte:** Protocols Manager **v2.2.0** (commit `623a7f9`, 28 de octubre de 2026), revisado en su código fuente y su README. Lo que ese complemento publique después de esta fecha queda fuera de la comparación.

> También se revisó **Used Items Export** v2.7.0 (F. Legastelois, pluginsGLPI): exporta a PDF los elementos de un usuario desde su ficha, con número de referencia consecutivo; permite borrar las exportaciones y no tiene huella, estados ni vista global.

| Característica | Acta en papel / Word | Plugin PDF (pluginsGLPI/pdf) | Protocols Manager v2.2.0 | **Actas de entrega (este plugin)** |
|---|---|---|---|---|
| Toma los datos del inventario de GLPI | No (se transcriben) | Sí (ficha de un activo) | Sí (activos asignados al usuario) | Sí (activos asignados al usuario, leídos en el instante de generar) |
| Plantillas configurables (fuente, logo, textos) | Sí | Limitado | **Sí, muy completas** | Plantilla fija institucional (pendiente: configurable) |
| Guarda el PDF en Documentos de GLPI | No | No | Sí | Sí, vinculado al acta, a ambas personas y a cada activo |
| Envío por correo | Manual | No | **Sí** | No (fuera de alcance) |
| Filas personalizadas (activos que no están en GLPI) | Sí | No | **Sí** | No, a propósito: todo activo del acta debe existir y ser consistente en GLPI |
| **Valida la consistencia inventario-acta antes de generar** | No | No | No | **Sí**: asignación según el tipo, serie, inventario y duplicados |
| **Tipos de acta (entrega, devolución, traslado)** | Formatos separados | No | No (un solo tipo de «protocolo») | **Sí**, con regla de consistencia y texto propios |
| **Número consecutivo** | Manual | No | No | **Sí**, por tipo y año, sin duplicados |
| **Huella SHA-256 e integridad verificable** | No | No | No | **Sí**, impresa en el PDF y recalculada al verificar |
| **QR y verificación pública** | No | No | No | **Sí**, sin sesión y con datos mínimos (Ley 1581) |
| **Inmutabilidad** | No | No aplica | No: el listado tiene un botón para **eliminar** actas | **Sí**: no se editan ni se borran; solo firmada o anulada con motivo |
| **Estados y anulación con motivo** | No | No | No | **Sí** |
| **Copia de los datos del activo al momento del acta** | El papel | No | El PDF únicamente | **Sí**, en base de datos (consultable y verificable) |
| **Historial de actas en la ficha del activo** | No | No | El documento se asocia al activo | **Sí**, pestaña con todos los movimientos |
| **Bitácora de eventos** | No | No | No | **Sí** (generación, firma, anulación, verificaciones, descargas) |
| **Vista global de gestión de actas** | No | No | No: las actas solo se listan dentro de la ficha de cada usuario (consulta filtrada por `user_id`); su único menú es de configuración de plantillas | **Sí**: panel con indicadores, pendientes de firma por antigüedad, cobertura de activos, filtros y exportación CSV |
| **Autogestión del usuario** | Pide copia al área de TI | No | No | **Sí**: «Mis actas» en la interfaz simplificada, con descarga controlada solo de sus actas |
| Confirmación digital por el usuario | Firma manuscrita | No | En su lista de pendientes («Digital confirmation of document by user») | No (se evita competir en esa línea) |

## Evidencia de la revisión de Protocols Manager

- `inc/generate.class.php`, método `deleteDocs()`: elimina actas seleccionadas desde la pestaña del usuario.
- No hay cálculo de huellas: la búsqueda de `sha256`, `hash(` e `integrity` en el código no arroja resultados (salvo contraseñas y CSRF).
- No hay tipos de acta, estados ni consecutivo: el formulario genera un único tipo de «protocolo» por plantilla.
- README, sección *Current status*: «Digital confirmation of document by user» figura como pendiente.
- `setup.php`: `menu_toadd` solo en `config` (plantillas); `inc/generate.class.php` línea 429 lista actas con `WHERE user_id = $id`; la tabla `glpi_plugin_protocolsmanager_protocols` no tiene campo de estado.

## Origen de la propuesta

Los mockups del autor elaborados en el semestre anterior (módulo de Aseguramiento de la calidad de software, 2025) ya incluían el panel con estados de las actas, el QR de verificación y la huella SHA en el pie del documento, antes de conocer Protocols Manager. Se anexan como evidencia.

## Frase de diferenciación

> Protocols Manager imprime el inventario de un usuario en un PDF configurable; **Actas de entrega** convierte ese PDF en un registro confiable: solo se genera si el inventario es consistente, no se puede alterar ni borrar, y cualquiera puede verificar su integridad con el QR.
