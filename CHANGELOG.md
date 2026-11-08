# Historial de versiones

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/). El proyecto usa [versionado semántico](https://semver.org/lang/es/).

## [1.0.0] — 2026-11-08

Primera versión estable, entregada como resultado del proyecto de grado.

### Agregado
- Manual de usuario (`docs/manual_usuario.md`).
- Guía de instalación, actualización y desinstalación (`docs/guia_instalacion.md`).
- Licencia y términos de uso (`docs/licencia_y_terminos.md`).
- Política de soporte y canales de contacto (`SOPORTE.md`).
- Este historial de versiones.

### Cambiado
- Versión 1.0.0. Sin cambios en la base de datos respecto de 0.2.1.

## [0.2.1] — 2026-11-03

### Agregado
- Restricciones CHECK sobre el tipo, el estado y el consecutivo de la tabla de actas, instaladas mediante una migración que las crea solo si no existen.

## [0.2.0] — 2026-11-02

### Agregado
- Especificaciones técnicas de los activos (tipo de equipo, procesador, RAM, almacenamiento, sistema operativo, IP y pantalla) en la pestaña, la ficha y el PDF.
- Cargo, dependencia y ubicación de quien entrega y quien recibe.
- Accesorios entregados.
- Versión del contenido canónico, para seguir verificando las actas generadas con 0.1.0.

## [0.1.0] — 2026-10-31

### Agregado
- Generación de actas de entrega, devolución y traslado desde la ficha del usuario, con consecutivo anual por tipo.
- Validación de consistencia inventario–acta antes de generar.
- Contenido canónico, huella SHA-256, PDF con código QR y verificación pública con datos mínimos.
- Inmutabilidad: estados generada → firmada → anulada, con motivo obligatorio y bitácora.
- Historial de actas en la ficha de cada activo.
- Derechos por perfil.
- Panel de gestión con indicadores, pendientes por antigüedad, cobertura, filtros y exportación CSV.
- «Mis actas» en la interfaz simplificada.
- Pruebas unitarias (PHPUnit y ejecutor mínimo) y ambiente Docker.
