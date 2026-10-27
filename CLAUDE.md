# CLAUDE.md

Guía del proyecto para Claude Code. Léela antes de analizar el código desde cero.

## Qué es

**Arma-QR**: SaaS mexicano (MXN) para generar códigos QR, tipo getqr.com. Registro obligatorio, modelo freemium.

- **QR estático** (gratis): el contenido va dentro del código; no se edita ni se mide.
- **QR dinámico** (de pago): el código apunta a `https://{dominio}/r/{codigo_corto}`; [r.php](r.php) registra el escaneo y redirige a `url_destino` o, en los tipos de página, muestra una página propia. Todo se puede cambiar sin reimprimir.
- **18 tipos dinámicos** (catálogo en [includes/qr_tipos.php](includes/qr_tipos.php)):
  - Redirigen: sitio web, WhatsApp, Facebook e Instagram. Aplicaciones manda a la tienda según el celular.
  - Muestran página propia: PDF, lista de enlaces, vCard, menú, cupón, Wi-Fi, evento, empresa, video, imágenes, redes sociales, MP3 y e-mail.
  - Texto solo existe como estático.
- Planes: Gratis / Pro / Negocio. Límites y funciones viven en la tabla `planes` (`NULL` = ilimitado), no en el código.

Especificación de negocio y BD: [docs/01-qr-saas-modelo-y-base-de-datos.md](docs/01-qr-saas-modelo-y-base-de-datos.md). Pantallas: [docs/02-qr-saas-pantallas-front.md](docs/02-qr-saas-pantallas-front.md) (nota: describe la etapa con datos mock, ya superada).

## Stack y entorno

- PHP 8.2 sin framework ni Composer, MySQL/MariaDB, XAMPP en Windows. URL local: `http://localhost/QR/`.
- `BASE_URL` se calcula solo en [config.php](config.php); usar siempre `url()`, `url_app()` y `asset()` para rutas.
- Zona horaria `America/Mexico_City`.
- Credenciales de BD en `database/.env` (ignorado por git; plantilla en `database/.env.example`). Conexión: `App\Core\Database::connection()` → helper `datos_pdo()`.
- Front sin build: [assets/css/app.css](assets/css/app.css) (hoja única) y [assets/js/app.js](assets/js/app.js) (vanilla JS). La imagen del QR se genera **en el navegador** con `qrcode-generator` desde CDN (`CDN_QR` en [includes/layout.php](includes/layout.php)); PNG/SVG/PDF se exportan desde JS.

## Comandos

```bash
php tests/run.php                       # todas las pruebas (scripts sueltos, exit 0 = pasa)
php tests/QrTest.php                    # una prueba
php database/setup_schema.php           # aplica qr_saas_schema.sql (solo si la BD está vacía)
php database/migrate_configuracion.php  # migraciones idempotentes (CREATE TABLE IF NOT EXISTS)
php database/migrate_plantillas_correo.php
php database/migrate_documentos_legales.php
php database/migrate_notas_usuario.php
php database/migrate_pagos.php          # columna usuarios.id_cliente_conekta
php database/migrate_planes.php         # quita planes.id_externo_mensual/anual (sin uso)
php database/migrate_paginas_qr.php     # qr_codigos.tipo_contenido de ENUM a VARCHAR(30) (páginas de QR)
php database/vencer_suscripciones.php   # cron diario: cierra periodos vencidos y regresa a Gratis
php database/create_admin.php "Nombre" correo@x.com "contraseña"   # crea un admin (o vía ADMIN_NAME/ADMIN_EMAIL/ADMIN_PASSWORD)
```

Los scripts de `database/` y `tests/` solo corren por CLI (devuelven 404 en web).

## Arquitectura (híbrida: importante)

El código real es **PHP procedural, una página por archivo**. Existe una capa MVC delgada que solo envuelve algunas páginas:

- [index.php](index.php) ejecuta `App\Core\Application` (router en [app/Core/Application.php](app/Core/Application.php)) → `PageController` → `app/Views/*.php`. Esas vistas **solo hacen `include` del archivo procedural original** (definiendo `MVC_CONTROLLER_RUNNING` o `MVC_LEGACY`).
- No hay reescritura global de URLs: Apache sirve cada `.php` directamente (`/QR/app/crear.php`, `/QR/admin/usuarios.php`…). La única regla de rewrite es `/r/{codigo}` → `r.php?c=` en [.htaccess](.htaccess).
- `app/Models/MockRepository.php` e [includes/mock.php](includes/mock.php) son restos de la etapa mock; solo los usan las pruebas. Los datos reales salen de [includes/datos.php](includes/datos.php) y `DatabaseRepository`.

**Para una página nueva, sigue el patrón procedural** (no hace falta tocar el router):

```php
<?php
require_once __DIR__ . '/../includes/layout.php';
$usuario = datos_usuario_actual();          // exige sesión; trae 'plan_datos'
pagina_inicio('Título', 'app', 'clave-menu'); // área: publico | app | admin
?> ...HTML... <?php pagina_fin('app', [CDN_QR]);
```

### Carpetas

| Carpeta | Qué hay |
|---|---|
| raíz | Páginas públicas: `index.php` (landing + generador), `login`, `registro`, `recuperar`, `restablecer`, `verificar`, `privacidad`, `terminos`, `r.php` |
| [app/](app/) | Panel del cliente: `index` (Mis QR), `crear` (crear/editar), `estadisticas`, `csv` (masivo), `plan` |
| [admin/](admin/) | Panel admin: `index`, `usuarios`/`usuario`, `qrs` (moderación), `planes`, `pagos`, `correos`/`correo`/`correo-variables`/`correo-ver`, `legales`/`legal`, `configuracion` |
| [auth/](auth/) | **Endpoints POST** (acciones): login, registro, logout, qr, qr-csv, pago, planes, usuarios, correos, legales, configuracion, recuperar, restablecer, verificar. Validan método, CSRF y permisos, luego `flash()` + `redirigir()` (PRG) |
| [includes/](includes/) | Librerías de funciones con prefijo por módulo (ver abajo) |
| [webhooks/](webhooks/) | `stripe.php` y `conekta.php`: avisos de las pasarelas (sin sesión ni CSRF; Stripe valida firma, Conekta se reconsulta a la API) |
| [database/](database/) | Esquema, migraciones, `Database.php`, scripts CLI |
| [tests/](tests/) | Pruebas propias (sin PHPUnit), runner `run.php` |
| `assets/uploads/` | Logos de QR (`qr/`), de marca (`brand/`) y archivos de las páginas de QR (`paginas/`: PDF, audio, imágenes), nombres hash |

### Módulos en `includes/` (prefijo = módulo)

- `layout.php`: `pagina_inicio()`/`panel_inicio()`/`pagina_fin()`, `icono()`, `aviso()`, `insignia_*()`, `url_app()`, `asset()`. Carga config, seguridad, datos y tema.
- `seguridad.php`: inicia la sesión, `redirigir()`, `csrf_token()/csrf_campo()/csrf_valido()`, `flash()/flash_tomar()`, `limite_*()` (bloqueo por intentos fallidos, guardado en archivos).
- `datos.php`: consultas `datos_*()` (planes, usuarios, QR, pagos, series de escaneos, resumen admin), `configuracion_activa()`, `nombre_portal()`, `dominio_portal()`.
- `qr.php`: `qr_contenido()` (debe coincidir con `construirContenido()` de app.js), límites de plan (`qr_limite_alcanzado`, `qr_ajustar_al_plan`: al bajar de plan pausa los dinámicos más nuevos que excedan el límite), `qr_insertar`, `qr_por_codigo`, `qr_registrar_escaneo` (IP como `sha256(ip + sal)`, la sal se guarda en `configuracion.escaneos_sal`), detección de robots y agente.
- `qr_tipos.php`: catálogo `QR_TIPOS` (estáticos) y `QR_TIPOS_DINAMICOS` (nombre, descripción, modo `redireccion`|`pagina`), `qr_tipo_usa_url()`, `qr_tipo_nombre()`. Lo carga `datos.php`.
- `qr_paginas.php`: páginas de los dinámicos.
  - `qr_pagina_contenido()` valida los campos `pg[tipo][...]` y guarda los archivos en `assets/uploads/paginas/`. Devuelve `['campos', 'texto']`; `texto` es `''` en los tipos de página, que guardan `url_destino = ''`.
  - Los archivos solo viven en las claves `QR_PAGINA_CAMPOS_ARCHIVO` (`qr_pagina_rutas()`). Al editar se borran los que dejan de usarse.
  - También genera los `.vcf` y `.ics` y elige la tienda para Aplicaciones.
- `qr_paginas_form.php`: un `<fieldset data-panel-tipo data-panel-modo="dinamico">` por tipo en `app/crear.php`. El JS deshabilita los ocultos para que no se envíen. Las listas repetibles usan `<template data-lista-plantilla>` con los marcadores `__i__` y `__j__`.
- `qr_pagina_vista.php`: `qr_pagina_mostrar()`, la página pública que muestra `r.php`. El color del QR es el acento.
  - `r.php?descargar=vcf|ics` sirve los archivos y no cuenta como escaneo.
  - `?vista_previa=1` (dueño o admin) muestra la página sin contar escaneo, aunque el QR esté pausado.
- `qr_csv.php`: lectura/validación/escritura de CSV para la creación masiva.
- `correo.php` + `smtp.php`: plantillas de correo desde BD con variables `{{var}}`, cliente SMTP propio (sin PHPMailer). Ver [docs/plantillas-de-correo.md](docs/plantillas-de-correo.md).
- `tokens.php`, `verificacion.php`, `recuperacion.php`: tokens de un solo uso (se guarda solo el hash) para verificar el correo y recuperar la contraseña.
- `legales.php`, `pagina_legal.php`: aviso de privacidad y términos editables, con versiones y borradores, y HTML sanitizado.
- `tema.php`: colores claro/oscuro configurables desde admin (variables CSS).
- `archivos.php`: subida y validación de imágenes, PDF y audio por su tipo real (`finfo`). `archivo_borrar()` solo borra nombres hash dentro de `assets/uploads/`.
- `pagos.php`: Stripe (suscripción con cobro automático, prorrateo, portal, reembolsos) y Conekta (pago único por periodo con tarjeta/OXXO/SPEI). Webhooks idempotentes vía `eventos_pasarela`; el plan solo cambia desde webhooks. cURL directo, sin SDK. Detalle: [docs/04-pagos-stripe-conekta.md](docs/04-pagos-stripe-conekta.md).

Autenticación: `App\Core\Auth` ([app/Core/Auth.php](app/Core/Auth.php)): `requireUser()`, `requireAdmin()`, `isAdminRole()`. La sesión guarda `$_SESSION['usuario_id']`; solo entran usuarios con `estatus = 'activo'`.

## Base de datos

- Esquema base: [database/qr_saas_schema.sql](database/qr_saas_schema.sql) (planes, usuarios, tokens_usuario, suscripciones, pagos, eventos_pasarela, qr_codigos, qr_historial_destinos, qr_escaneos, uso_mensual, claves_api, vista `vw_plan_usuario`…).
- Suscripción vigente: `s.usuario_vigente = u.id AND SQL_SUSCRIPCION_AL_DIA` (constante en `datos.php`): un periodo vencido + 3 días de gracia cuenta como Gratis aunque el cron no haya corrido. Usa esa condición en todo JOIN nuevo a la suscripción vigente.
- Tablas agregadas por migraciones: `configuracion` (clave/valor: identidad, SMTP, pagos, tema, sal), `plantillas_correo`, `variables_correo`, `documentos_legales`, `notas_usuario`.
- Convenciones: nombres en español; tablas maestras con `usuario_creacion`, `fecha_creacion`, `usuario_modificacion`, `fecha_modificacion` y `eliminado` (borrado lógico, **filtrar siempre `eliminado = 0`**); las tablas de bitácora y transacciones no tienen borrado lógico; tokens y claves API solo como hash.
- Un cambio de esquema = nuevo `database/migrate_*.php` idempotente + actualizar `qr_saas_schema.sql` si aplica + `DatabaseSyntaxTest`.

## Estado actual

**Funciona con BD real:** pagos con Stripe y Conekta (checkout, webhooks, cambio de plan, cancelación, portal, reembolsos totales), registro/login con límite de intentos, verificación de correo, recuperación de contraseña, crear/editar/pausar/eliminar QR estáticos y dinámicos (incluidas las páginas propias de los 18 tipos), límites por plan (incluida la retención de estadísticas por plan), redirección y registro de escaneos, estadísticas, creación masiva por CSV, panel admin (usuarios, notas, moderación de QR, planes con precios, límites y funciones, plantillas de correo, documentos legales, configuración de identidad, SMTP, tema y pagos), envío de correo por SMTP.

**Pendiente o simulado:**
- **Pagos:** facturación CFDI (datos fiscales y "Descargar factura" en `app/plan.php`) sigue simulada (`data-simular*`). Las claves de pasarela se guardan sin cifrar en `configuracion`.
- **API pública y claves API:** existe la tabla `claves_api` y el límite `planes.max_claves_api`, pero no hay endpoints; por eso la API no se anuncia en los precios ni en `app/plan.php`.
- La capa MVC está a medias (ver Arquitectura).

## Convenciones de código

- Todo en español: variables, funciones, comentarios, mensajes y textos de UI (con acentos correctos).
- `declare(strict_types=1);` en los archivos de lógica; funciones con prefijo del módulo; SQL siempre con sentencias preparadas.
- Escapar toda salida con `e()`. Todo formulario POST lleva `csrf_campo()`, y su endpoint en `auth/` valida con `csrf_valido()`.
- Fechas: `fecha_corta()`; dinero: `dinero()`; números: `numero()`.
- Agregar o actualizar la prueba correspondiente en `tests/` y correr `php tests/run.php` antes de terminar.
- Mantén este archivo al día cuando cambie algo estructural (nuevo módulo, tabla, flujo o pendiente resuelto).
