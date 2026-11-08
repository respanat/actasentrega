# Guía de instalación — Actas de entrega para GLPI

Versión 1.0.0

Esta guía está dirigida al administrador de GLPI. Explica cómo instalar, configurar, actualizar y desinstalar el complemento, y cómo levantar un ambiente de pruebas.

## 1. Requisitos

| Componente | Versión |
|---|---|
| GLPI | 11.0.x (probado en 11.0.11) |
| PHP | 8.2 o superior (probado en 8.5) |
| Base de datos | MySQL 8 o MariaDB 10.5 o superior, la misma de la instancia de GLPI |
| Dependencias externas | Ninguna: el PDF y el código QR usan la biblioteca TCPDF que ya incluye GLPI |

Se necesita acceso al servidor (o a la carpeta `plugins` de GLPI) y una cuenta con perfil Super-Admin.

## 2. Obtener el complemento

**Opción A — Paquete de la versión.** Descargue `actasentrega-1.0.0.zip` y descomprímalo dentro de la carpeta `plugins` de GLPI:

```bash
cd /ruta/de/glpi/plugins
unzip /ruta/descargas/actasentrega-1.0.0.zip
```

**Opción B — Repositorio.**

```bash
cd /ruta/de/glpi/plugins
git clone --branch v1.0.0 https://github.com/respanat/actasentrega.git actasentrega
```

En ambos casos la carpeta **debe llamarse `actasentrega`**. Asegúrese de que el usuario del servidor web pueda leerla:

```bash
sudo chown -R www-data:www-data /ruta/de/glpi/plugins/actasentrega
```

(El usuario puede ser `apache` u otro, según la distribución.)

## 3. Instalar y activar

**Desde la interfaz.** Inicie sesión como Super-Admin y vaya a **Configuración › Complementos**. En la fila **Actas de entrega**, pulse **Instalar** y luego **Activar**.

**Desde la consola**, en la carpeta raíz de GLPI y con el usuario del servidor web:

```bash
sudo -u www-data php bin/console plugin:install actasentrega --username=glpi
sudo -u www-data php bin/console plugin:activate actasentrega
```

La instalación crea tres tablas (`glpi_plugin_actasentrega_actas`, `_actas_items` y `_eventos`) con sus restricciones de integridad, y registra el derecho del complemento.

## 4. Configurar permisos

El perfil Super-Admin recibe todos los derechos. Para los demás perfiles, vaya a **Administración › Perfiles › *perfil* › Actas de entrega** y asigne:

| Derecho | Perfil sugerido |
|---|---|
| Leer | Técnicos y coordinadores de TI |
| Generar | Técnicos que entregan equipos |
| Firmar / anular | Coordinador o responsable de inventario |

Los usuarios de la interfaz simplificada no necesitan derechos para ver sus propias actas en **Mis actas**.

La página pública de verificación (`/plugins/actasentrega/front/verificar.php`) no requiere sesión y solo muestra datos mínimos. Para que el código QR funcione fuera de la red interna, la URL de GLPI configurada en **Configuración › General › URL de la aplicación** debe ser accesible desde donde se lea el QR.

## 5. Comprobar la instalación

1. En **Configuración › Complementos**, el complemento aparece como *Instalado / Activado* con la versión 1.0.0.
2. En **Herramientas** aparece **Actas de entrega** (panel de gestión).
3. En la ficha de un usuario aparece la pestaña **Actas**.

## 6. Actualizar desde una versión anterior

```bash
cd /ruta/de/glpi/plugins/actasentrega
git fetch --tags && git checkout v1.0.0      # o reemplace la carpeta por la del paquete 1.0.0
```

Luego, en **Configuración › Complementos › Actas de entrega**, pulse **Actualizar** y después **Activar** (o use los dos comandos de consola del paso 3).

La actualización es segura: agrega columnas y restricciones solo si no existen y **no modifica las actas ya generadas**, que se siguen verificando con el formato con que se crearon. La versión 1.0.0 no cambia la base de datos respecto de la 0.2.1.

## 7. Desinstalar

En **Configuración › Complementos**, pulse **Desactivar** y luego **Desinstalar**.

> **Atención:** desinstalar elimina las tres tablas del complemento, es decir, el registro de las actas, sus huellas y su bitácora. Los PDF generados permanecen en *Gestión › Documentos*. Haga una copia de seguridad de la base de datos antes de desinstalar.

## 8. Ambiente de pruebas con Docker

El repositorio incluye un `docker-compose.yml` que levanta GLPI 11.0.11 con MariaDB 10.11 y monta el complemento:

```bash
git clone https://github.com/respanat/actasentrega.git
cd actasentrega
docker compose up -d          # GLPI en http://localhost:8080 (usuario glpi / glpi)
```

Instale el complemento como en el paso 3. Para cargar los usuarios y activos sintéticos usados en la evaluación:

```bash
docker compose exec -T db mariadb -uglpi -pglpi glpi < tools/datos_prueba.sql
```

## 9. Pruebas automatizadas

```bash
composer install && composer test     # PHPUnit
php tests/tools/mini-runner.php       # sin Composer ni conexión a internet
```

## 10. Solución de problemas

| Síntoma | Causa probable | Solución |
|---|---|---|
| El complemento no aparece en Complementos | La carpeta no se llama `actasentrega` o el servidor web no puede leerla | Renombre la carpeta y revise el propietario y los permisos |
| La consola falla con un error de permisos sobre los archivos de registro | El comando se ejecutó con un usuario distinto al del servidor web | Ejecútelo con `sudo -u www-data` o instale desde la interfaz |
| Aparece «Versión de GLPI no compatible» | La instancia no es GLPI 11.0.x | Use GLPI 11.0.x |
| El QR abre una dirección que no carga | La URL de la aplicación apunta a una dirección interna | Ajuste **Configuración › General › URL de la aplicación** |
| No se genera el acta | La validación encontró una inconsistencia en el inventario | Lea el mensaje, corrija el dato en GLPI y vuelva a intentar (ver el Manual de usuario) |

Para otros problemas, consulte [SOPORTE.md](../SOPORTE.md).
