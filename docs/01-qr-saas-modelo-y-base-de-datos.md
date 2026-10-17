# QR SaaS — 01. Modelo de negocio y base de datos

> Archivo de instrucciones para ejecutar con Claude Code en VS Code.
> Colócalo en la raíz del proyecto junto con `database/qr_saas_schema.sql`.

---

## 1. Contexto del proyecto

Plataforma SaaS para generar códigos QR, similar a getqr.com.

- **Registro obligatorio:** todo usuario debe registrarse para usar la plataforma.
- **Modelo freemium:** al registrarse, el usuario queda en el plan gratuito y puede pasar a un plan de pago.
- **Stack:** PHP + MySQL 8+ / MariaDB 10.5+ (XAMPP en Windows).
- **Mercado:** México (moneda MXN, pasarelas que aceptan tarjetas mexicanas).

## 2. Tipos de QR (lo que define qué se cobra)

| Tipo | Cómo funciona | Plan |
|---|---|---|
| **Estático** | El contenido (URL, texto, WiFi, vCard) va dentro del código. No se edita ni se mide. | Gratis |
| **Dinámico** | El QR apunta a `https://<dominio>/r/{codigo_corto}`, que redirige a `url_destino`. Se puede cambiar el destino sin reimprimir y se registran los escaneos. | De pago |

## 3. Planes iniciales (precios de ejemplo)

| Plan | Mensual | Anual | Incluye |
|---|---|---|---|
| Gratis | $0 | $0 | 50 QR estáticos/mes, PNG, marca de agua |
| Pro | $149 MXN | $1,490 MXN | 50 QR dinámicos, analítica (365 días), logo, SVG/PDF, sin marca de agua |
| Negocio | $499 MXN | $4,990 MXN | Todo Pro + dinámicos ilimitados, generación masiva por CSV, 5 claves API |

Los límites viven en la tabla `planes` (`NULL` = ilimitado), así se ajustan sin tocar código.

## 4. Decisiones técnicas

- **Librería QR:** `chillerlan/php-qrcode` o `endroid/qr-code` (instalar con Composer).
- **Pasarelas de pago:** Stripe, Mercado Pago o Conekta. El plan del usuario se activa o se baja **solo** según los webhooks de la pasarela.
- **Webhooks idempotentes:** cada evento se registra en `eventos_pasarela` con llave única `(pasarela, id_evento)`.
- **Middleware de límites:** antes de cada acción se consulta `vw_plan_usuario`.
- **Privacidad (LFPDPPP):** la IP de cada escaneo se guarda como `sha256(ip + sal)`.
- **Fechas en UTC** en la base; la zona horaria del usuario se aplica en la app.

## 5. Convenciones de base de datos

- Nombres de tablas y columnas en español.
- Tablas maestras: campos de auditoría `usuario_creacion`, `fecha_creacion`, `usuario_modificacion`, `fecha_modificacion` + `eliminado` para borrado lógico.
- Tablas transaccionales o de bitácora (`pagos`, `qr_escaneos`, `eventos_pasarela`, etc.): sin borrado lógico.
- Tokens y claves API: se guarda solo el hash, nunca el valor en claro.

## 6. Tablas del esquema

| # | Tabla | Propósito |
|---|---|---|
| 1 | `planes` | Precios, límites y funcionalidades de cada plan |
| 2 | `usuarios` | Cuentas, rol (`cliente`/`admin`) y estatus |
| 3 | `tokens_usuario` | Verificación de email y recuperación de contraseña |
| 4 | `suscripciones` | Plan vigente del usuario; columna generada + índice único garantizan **una sola** suscripción vigente |
| 5 | `pagos` | Cobros registrados desde la pasarela |
| 6 | `eventos_pasarela` | Bitácora de webhooks (idempotencia) |
| 7 | `qr_codigos` | QR del usuario; `CHECK` obliga a que los dinámicos tengan `codigo_corto` y `url_destino` |
| 8 | `qr_historial_destinos` | Cambios de URL de los QR dinámicos |
| 9 | `qr_escaneos` | Bitácora de escaneos (alto volumen) |
| 10 | `uso_mensual` | Contadores por mes para validar límites rápido |
| 11 | `claves_api` | Claves API por usuario (plan Negocio) |
| — | `vw_plan_usuario` | Vista: plan, límites y consumo actual de cada usuario |

---

## 7. Tareas para Claude Code

Ejecuta en orden y confirma cada paso antes de seguir:

1. **Verifica la versión del motor**
   - Ejecuta `SELECT VERSION();` en la instancia de XAMPP.
   - Si es MariaDB menor a 10.5 o MySQL menor a 8.0, detente y avísame.

2. **Crea la estructura de carpetas** (si no existe):
   ```
   qr-saas/
   ├── database/
   │   └── qr_saas_schema.sql
   ├── public/
   ├── src/
   └── docs/
       └── 01-qr-saas-modelo-y-base-de-datos.md
   ```

3. **Ejecuta el esquema**
   - Archivo: `database/qr_saas_schema.sql`. Si no está, pídemelo; no lo reescribas de memoria.
   - En XAMPP: `C:\xampp\mysql\bin\mysql.exe -u root -p < database\qr_saas_schema.sql`
   - Crea la base `qr_saas`, las 11 tablas, la vista y los 3 planes.

4. **Valida la instalación**
   ```sql
   USE qr_saas;
   SHOW TABLES;                          -- 11 tablas + 1 vista
   SELECT codigo, precio_mensual FROM planes;   -- gratis, pro, negocio
   ```

5. **Prueba las reglas clave**
   - Inserta un usuario de prueba y su suscripción al plan `gratis`.
   - Intenta insertar una **segunda** suscripción `activa` para el mismo usuario → debe fallar por `uq_suscripcion_vigente`.
   - Intenta insertar un QR `dinamico` sin `codigo_corto` → debe fallar por `ck_qr_dinamico`.
   - Consulta `SELECT * FROM vw_plan_usuario;` y verifica que muestre el plan y los límites.
   - Al terminar, borra los datos de prueba.

6. **Reporta** qué pasó en cada paso y cualquier error con su mensaje exacto.

---

## 8. Siguientes pasos (pendientes)

- [ ] Registro, verificación de email y login (PHP, `password_hash`)
- [ ] Endpoint de redirección `/r/{codigo}` con registro de escaneo
- [ ] Generación de QR estático (PNG) con la librería elegida
- [ ] Middleware de límites por plan
- [ ] Integración de pasarela de pago + webhooks
