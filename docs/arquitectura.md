# Arquitectura

El plugin sigue la estructura de la [guía oficial de desarrollo de complementos de GLPI](https://glpi-developer-documentation.readthedocs.io/en/master/plugins/) (Teclib'), con una organización compatible con MVC:

- **Modelo:** `src/` (clases que heredan de `CommonDBTM`) y la lógica de dominio pura `ActaBuilder`.
- **Controlador:** `front/` (puntos de entrada que GLPI 11 carga mediante su controlador de scripts heredados).
- **Vista:** `templates/` (Twig, mediante `TemplateRenderer`).

`ActaBuilder` no depende de GLPI: valida, numera, construye el contenido canónico y calcula la huella. Por eso se prueba de forma aislada.

## Diagrama de componentes

```mermaid
flowchart LR
  subgraph GLPI["GLPI 11 (núcleo, sin modificar)"]
    UI[Interfaz web<br/>fichas Usuario / Activo]
    INV[(Inventario<br/>glpi_computers, glpi_monitors…)]
    DOCS[Documentos<br/>Document / Document_Item]
    TCPDF[TCPDF<br/>incluida en GLPI]
    PERF[Perfiles y derechos]
  end
  subgraph PLUGIN["Plugin actasentrega"]
    FRONT[front/<br/>acta.form.php · acta.php · verificar.php]
    ACTA[Acta<br/>generación · inmutabilidad · estados · verificación]
    BUILDER[ActaBuilder<br/>validación · consecutivo · canónico · SHA-256]
    PDF[PdfRenderer]
    ITEM[ActaItem<br/>snapshot · historial]
    PROF[Profile]
    TPL[templates/ Twig]
    DB[(Tablas del plugin<br/>actas · actas_items · eventos)]
  end
  UI --> FRONT --> ACTA
  ACTA --> BUILDER
  ACTA --> PDF --> TCPDF
  ACTA --> ITEM
  ACTA --> DB
  ITEM --> DB
  ACTA -- lee asignación y datos --> INV
  ACTA -- guarda PDF y vínculos --> DOCS
  PROF --> PERF
  ACTA --> TPL
  QR((QR del PDF)) --> FRONT
```

## Diagrama de secuencia: generación del acta

```mermaid
sequenceDiagram
  actor T as Técnico
  participant F as acta.form.php
  participant A as Acta
  participant G as Inventario GLPI
  participant B as ActaBuilder
  participant P as PdfRenderer
  participant D as Documentos GLPI
  participant BD as Tablas del plugin

  T->>F: POST generar (tipo, usuario, activos, estado físico)
  F->>F: Verifica sesión, derecho CREATE y token CSRF
  F->>A: generar(datos)
  loop cada activo seleccionado
    A->>G: Lee el activo en este instante (nombre, serie, inventario, modelo, usuario asignado)
  end
  A->>B: validar(acta)
  alt hay inconsistencias
    B-->>A: lista de errores
    A-->>T: «No se generó el acta…» + causas
  else consistente
    A->>BD: BEGIN y MAX(consecutivo) del tipo y año
    A->>B: numero(), canonico(), huella SHA-256
    A->>P: render(acta, huella, URL de verificación)
    P-->>A: PDF (con huella y QR)
    A->>D: Document::add(PDF)
    A->>BD: INSERT acta (canónico, huella, huella del PDF) e ítems (snapshot)
    A->>D: Vincula el PDF al acta, a las personas y a cada activo
    A->>BD: Evento «generada» y COMMIT
    A-->>T: Ficha del acta: «Integridad verificada»
  end
```

## Diagrama de secuencia: verificación pública (QR)

```mermaid
sequenceDiagram
  actor C as Cualquier persona
  participant V as verificar.php (sin sesión)
  participant A as Acta
  participant BD as Tablas del plugin
  C->>V: GET ?n=AE-2026-000001&h=<huella del PDF>
  V->>BD: Busca el acta por número
  V->>A: verificarIntegridad()
  A->>A: SHA-256(canónico guardado) = huella guardada
  A->>BD: Lee ítems
  A->>A: Canónico reconstruido (cabecera + ítems) = canónico guardado
  V->>V: Huella del QR = huella guardada
  V->>BD: Evento «verificacion»
  V-->>C: Auténtica e íntegra / anulada / NO válida (sin nombres ni datos de activos)
```

## Modelo de datos

```mermaid
erDiagram
  glpi_users ||--o{ glpi_plugin_actasentrega_actas : "entrega / recibe / genera"
  glpi_entities ||--o{ glpi_plugin_actasentrega_actas : contiene
  glpi_documents ||--|| glpi_plugin_actasentrega_actas : "PDF"
  glpi_plugin_actasentrega_actas ||--|{ glpi_plugin_actasentrega_actas_items : incluye
  glpi_plugin_actasentrega_actas ||--o{ glpi_plugin_actasentrega_eventos : registra
  glpi_computers ||--o{ glpi_plugin_actasentrega_actas_items : "itemtype + items_id"

  glpi_plugin_actasentrega_actas {
    int id PK
    int entities_id FK
    varchar numero UK "AE-2026-000001"
    smallint anio
    int consecutivo "UK (tipo, anio, consecutivo)"
    varchar tipo "entrega | devolucion | traslado"
    varchar estado "generada | firmada | anulada"
    int users_id_entrega FK
    int users_id_recibe FK
    int users_id_tecnico FK
    timestamp fecha_generacion
    text observaciones
    longtext contenido_canonico "lo que se firmó con la huella"
    char huella_sha256 "64 hex"
    char huella_pdf "SHA-256 del archivo PDF"
    int documents_id FK
    text motivo_anulacion
  }
  glpi_plugin_actasentrega_actas_items {
    int id PK
    int plugin_actasentrega_actas_id FK
    varchar itemtype "Computer, Monitor…"
    int items_id
    varchar nombre
    varchar serial
    varchar otherserial "n.º de inventario"
    varchar fabricante
    varchar modelo
    varchar estado_fisico
  }
  glpi_plugin_actasentrega_eventos {
    int id PK
    int plugin_actasentrega_actas_id FK
    int users_id "0 = verificación pública"
    varchar accion
    varchar detalle
    timestamp date_creation
  }
```

## Despliegue

```mermaid
flowchart LR
  N[Navegador del técnico] -- HTTPS --> W[Servidor web + PHP 8.2+<br/>GLPI 11 / plugins/actasentrega]
  M[Celular: lectura del QR] -- HTTPS --> W
  W -- MySQL --> BD[(MariaDB / MySQL<br/>tablas de GLPI y del plugin)]
  W -- archivos --> F[(files/_files<br/>PDF de las actas)]
```

## Decisiones de diseño

| Decisión | Alternativa descartada | Motivo |
|---|---|---|
| Inmutabilidad en la capa de modelo (`prepareInputForUpdate`, `pre_deleteItem`) | Disparadores en la base de datos | Muchos servidores no conceden el privilegio TRIGGER; la verificación de integridad detecta además cualquier cambio hecho por fuera de GLPI |
| Guardar el contenido canónico además de la huella | Recalcular solo desde las tablas | Los nombres de las personas pueden cambiar en GLPI; el canónico conserva exactamente lo que se firmó |
| Snapshot de los activos | Leer el inventario actual al mostrar el acta | El acta debe reflejar lo entregado, no el estado actual del activo |
| TCPDF de GLPI | dompdf u otra biblioteca | Evita dependencias nuevas y conflictos de versiones con el núcleo |
| Página de verificación pública con datos mínimos | Verificación solo con sesión | Quien recibe una copia del acta (auditoría, otra dependencia) puede comprobarla sin usuario de GLPI; la Ley 1581 obliga a minimizar los datos expuestos |
| Consecutivo por tipo y año con clave única | Contador en tabla de configuración | La clave única impide duplicados aun con generaciones simultáneas |
