# QR SaaS — 02. Pantallas: sitio público, panel del cliente y administración

> Archivo de instrucciones para ejecutar con Claude Code en VS Code.
> Requiere haber completado `01-qr-saas-modelo-y-base-de-datos.md`.

---

## 1. Qué contiene esta entrega

Vistas en **PHP sin framework** listas para XAMPP. Todas leen datos de ejemplo desde un solo archivo (`includes/mock.php`) para poder revisarlas antes de conectar la base de datos.

```
qr-saas/
├── config.php                 # Nombre comercial, dominio corto, helpers (url, e, dinero, fecha_corta)
├── includes/
│   ├── layout.php             # Cabecera, menú lateral, íconos, insignias, cierre de página
│   └── mock.php               # DATOS DE EJEMPLO → reemplazar por consultas
├── assets/
│   ├── css/app.css            # Hoja de estilos única
│   └── js/app.js              # Generador en vivo, filtros, CSV, gráficas, diálogos
├── index.php                  # Página pública (generador en vivo, precios, preguntas)
├── registro.php               # Crear cuenta (?plan=pro preselecciona plan)
├── login.php                  # Iniciar sesión + accesos de demostración
├── app/                       # Panel del cliente
│   ├── index.php              # Mis QR: lista, filtros, pausar, copiar liga, eliminar
│   ├── crear.php              # Crear / editar QR (?editar=ID), vista previa en vivo
│   ├── estadisticas.php       # Escaneos por día, ciudad, dispositivo y hora (bloqueado en Gratis)
│   └── plan.php               # Uso del plan, cambio de plan, recibos, datos fiscales
├── admin/                     # Panel de administración
│   ├── index.php              # Resumen: ingreso recurrente, registros, conversión, alertas
│   ├── usuarios.php           # Todos los usuarios, filtros, copiar correos, exportar CSV
│   ├── usuario.php            # Detalle: suscripción, QR, pagos, acciones (?id=)
│   ├── pagos.php              # Cobros, rechazados, reembolsos, exportar CSV
│   ├── qrs.php                # Todos los QR de la plataforma, pausar
│   └── planes.php             # Editar precios, límites y funciones de cada plan
├── database/qr_saas_schema.sql
└── docs/
```

## 2. Decisiones de diseño

- **Nombre:** `Arma-QR` (dominio `armaqr.com`). Se configuran en Administración › Configuración › Identidad; `APP_NOMBRE` y `APP_DOMINIO` de `config.php` son solo el respaldo.
- **Paleta:** tinta azul marino `#172036`, rosa mexicano `#E4007C` como acento, fondo `#F4F5F9`. Variables en `:root` de `app.css`.
- **Tipografía:** Bricolage Grotesque (títulos) y Public Sans (texto), desde Google Fonts.
- **Héroe:** el generador funcionando es lo primero que se ve; el QR se actualiza al escribir.
- **Panel de administración** con menú lateral oscuro para distinguirlo del panel del cliente.
- **Librerías por CDN:** `qrcodejs 1.0.0` (vista previa) y `Chart.js 4.4.1` (gráficas). En producción el QR final se genera en el servidor con `chillerlan/php-qrcode`.

## 3. Cómo probar

1. Copia la carpeta `qr-saas` a `C:\xampp\htdocs\qr-saas`.
2. Abre `http://localhost/qr-saas/`.
3. Accesos de demostración en `login.php`:
   - Panel del cliente (plan Negocio): `/app/`
   - Panel del cliente como plan Gratis: `/app/?demo=gratis`
   - Administración: `/admin/`

Las acciones (pausar, reembolsar, guardar, suspender) muestran una notificación pero **no guardan nada todavía**. Cada formulario tiene un comentario `<!-- POST real: ... -->` que dice qué debe hacer al conectar la base.

---

## 4. Tareas para Claude Code

Ejecuta en orden y confirma cada paso:

1. **Verifica que todo carga sin errores**
   - Para cada archivo `.php`: `C:\xampp\php\php.exe -l <archivo>`.
   - Abre cada página de la sección 3 y revisa la consola del navegador.

2. **Crea la conexión a la base**
   - Archivo `includes/db.php` con PDO (`utf8mb4`, `ERRMODE_EXCEPTION`, `FETCH_ASSOC`).
   - Credenciales en `config.local.php` (agrégalo a `.gitignore`).

3. **Reemplaza los datos de ejemplo, una función a la vez**, en este orden:
   | Función en `mock.php` | Origen real |
   |---|---|
   | `mock_planes()` | `planes WHERE eliminado = 0 ORDER BY orden` |
   | `mock_usuarios()` | `usuarios` + `suscripciones` vigente + `SUM(pagos.monto)` + conteo de `qr_codigos` |
   | `mock_usuario($id)` | Igual que arriba filtrado por id |
   | `mock_pagos()` | `pagos` |
   | `mock_qrs()` / `mock_qrs_de()` | `qr_codigos WHERE eliminado = 0` |
   | `mock_serie_diaria()` | `qr_escaneos GROUP BY DATE(fecha_escaneo)` |
   | `mock_desgloses()` | `qr_escaneos GROUP BY dispositivo / sistema_operativo / ciudad / HOUR()` |
   | `mock_resumen_admin()` | Agregados de las tablas anteriores |
   | `mock_usuario_actual()` | Usuario de la sesión (`$_SESSION['usuario_id']`) + `vw_plan_usuario` |
   - Conserva los mismos nombres de campos para que las vistas no cambien.
   - Usa siempre consultas preparadas.

4. **Protege las áreas**
   - `app/*` exige sesión iniciada; si no, redirige a `login.php`.
   - `admin/*` exige `usuarios.rol = 'admin'`.
   - Agrega token CSRF a todos los formularios POST.

5. **Agrega a la base lo que estas pantallas necesitan y el esquema 01 no tiene**
   - Tabla `datos_fiscales` (usuario_id, rfc, razon_social, codigo_postal, regimen_fiscal + auditoría).
   - Tabla `notas_usuario` (usuario_id, nota, usuario_creacion, fecha_creacion) para notas internas del admin.
   - Columna `qr_codigos.color` y `qr_codigos.fondo`, o úsalos dentro del JSON `diseno`.

6. **Reporta** cada paso con los archivos modificados.

---

## 5. Pendientes después de esta entrega

- [ ] Lógica real de registro, verificación de correo y login
- [ ] Endpoint `/r/{codigo}` que registre el escaneo y redirija
- [ ] Generación del QR final en el servidor (PNG/SVG/PDF) con logo
- [ ] Integración de pasarela de pago y webhooks
- [ ] Prueba de 14 días del plan Pro (la página pública ya la anuncia; el esquema la soporta con `suscripciones.estatus = 'en_prueba'` y `prueba_fin`)
