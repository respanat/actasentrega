# Licencia y términos de uso — Actas de entrega para GLPI

Versión 1.0.0

## 1. Licencia

El complemento **Actas de entrega para GLPI** es software libre, distribuido bajo la **Licencia Pública General de GNU, versión 3 o posterior (GPL-3.0-or-later)**. El texto completo está en el archivo [LICENSE](../LICENSE) y en <https://www.gnu.org/licenses/gpl-3.0.html>.

Se eligió esta licencia porque es la misma de GLPI, lo que garantiza la compatibilidad del complemento con la plataforma en la que se ejecuta.

Copyright © 2026 Ramiro Alfonso España Tejada.

## 2. Qué permite la licencia

- **Usar** el complemento con cualquier propósito, en cualquier número de instalaciones, sin costo.
- **Estudiar** el código fuente y **modificarlo** para adaptarlo a las necesidades de la organización.
- **Redistribuir** copias, con o sin modificaciones.

## 3. Qué exige la licencia

- Quien distribuya el complemento, original o modificado, debe entregar también el código fuente bajo la misma licencia GPL-3.0-or-later.
- Debe conservar los avisos de copyright y de licencia y dejar claro qué cambios hizo.
- No puede imponer restricciones adicionales a quienes reciban el software.

Usar o modificar el complemento solo dentro de la propia organización, sin distribuirlo a terceros, no obliga a publicar los cambios.

## 4. Ausencia de garantía

Como establece la GPL-3.0 (secciones 15 y 16), el complemento se entrega **«tal cual», sin garantía de ningún tipo**, explícita o implícita, incluidas las de comerciabilidad o idoneidad para un propósito particular. El autor no responde por daños derivados de su uso. Se recomienda probarlo primero en un ambiente de pruebas y mantener copias de seguridad de la base de datos de GLPI.

## 5. Responsabilidades de la organización que lo usa

- **Protección de datos personales.** La organización es responsable del tratamiento de los datos de sus usuarios conforme a la Ley 1581 de 2012 y sus normas reglamentarias. El complemento aplica minimización: las actas incluyen solo los datos necesarios, la verificación pública no muestra nombres ni datos de los activos y cada usuario ve únicamente sus propias actas.
- **Valor probatorio.** La huella SHA-256 demuestra que el contenido del acta no ha cambiado (integridad), pero **no reemplaza la firma** de las partes ni constituye una firma digital certificada en los términos de la Ley 527 de 1999. La firma sigue siendo manuscrita sobre el documento impreso, salvo que la organización integre un mecanismo de firma electrónica.
- **Conservación documental.** La organización define los tiempos de retención de las actas y de los PDF almacenados en GLPI según su programa de gestión documental.
- **Exactitud del inventario.** El complemento valida el acta contra los datos de GLPI; la exactitud de esos datos depende de quien administra el inventario.

## 6. Componentes de terceros

El complemento no incluye bibliotecas propias de terceros. Usa las que ya trae GLPI (TCPDF para el PDF y el código QR, Twig para las vistas), cada una bajo su propia licencia.

## 7. Uso académico

El complemento se desarrolló como proyecto de grado del Programa de Ingeniería de Software de la Universidad de Cartagena (2026-2). Puede citarse como:

> España Tejada, R. A. (2026). *Actas de entrega para GLPI* (Versión 1.0.0) [Software]. https://github.com/respanat/actasentrega
