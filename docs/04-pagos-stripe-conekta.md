# QR SaaS — 04. Pagos con Stripe y Conekta

## Cómo cobra cada pasarela

| | Stripe | Conekta |
|---|---|---|
| Métodos | Tarjeta | Tarjeta, OXXO y SPEI |
| Modelo | Suscripción con **cobro automático** (mensual o anual) | **Pago único** de un periodo; no se renueva solo |
| Cambio de plan | Se cobra la diferencia prorrateada al instante | El plan nuevo empieza al pagar y reemplaza al actual |
| Renovar | Automático | El cliente paga otro periodo; se suma al final del vigente |
| Cancelar | Al final del periodo (se puede reanudar) | No aplica: vence solo |
| Cambiar tarjeta / facturas | Portal de Stripe | — |

Una pasarela se ofrece a los clientes solo si tiene sus claves y su interruptor "Ofrecer … a los clientes" está encendido (Configuración › Pagos; `pago_stripe_habilitado` / `pago_conekta_habilitado`, encendido por omisión). Apagada, deja de ofrecerse para pagos nuevos y cambios de plan, pero sus webhooks se siguen atendiendo (renovaciones de Stripe, pagos OXXO/SPEI que lleguen después) y los suscriptores de Stripe pueden cancelar o entrar a su portal. Si un cliente ya tiene una suscripción de Stripe activa, solo puede cambiar de plan por Stripe, para que no le cobren dos veces.

## Flujo

1. El cliente elige un plan en **Plan y pagos** ([app/plan.php](../app/plan.php)), que envía el formulario a [auth/pago.php](../auth/pago.php).
2. Se le manda al Checkout de la pasarela. **Aquí no se activa nada.**
3. La pasarela avisa por webhook ([webhooks/stripe.php](../webhooks/stripe.php), [webhooks/conekta.php](../webhooks/conekta.php)).
4. El webhook vuelve a consultar el objeto en la API de la pasarela, que es la fuente de verdad, y actualiza `suscripciones` y `pagos`. Cada evento se guarda en `eventos_pasarela` y no se procesa dos veces; si falla, responde 500 y la pasarela lo reintenta.
5. Un periodo vencido (más `PAGOS_GRACIA_DIAS` = 3 días) cuenta como plan Gratis de inmediato, y [database/vencer_suscripciones.php](../database/vencer_suscripciones.php) cierra esa suscripción en la tabla.

Toda la lógica está en [includes/pagos.php](../includes/pagos.php). No usa Composer: llama a las API REST con cURL.

## Puesta en marcha

1. Corre la migración (agrega `usuarios.id_cliente_conekta`):
   ```
   php database/migrate_pagos.php
   ```
2. En **Administración › Configuración › Pagos** captura:
   - Stripe · clave secreta (`sk_test_…` para pruebas, `sk_live_…` en producción).
   - Stripe · secreto del webhook (`whsec_…`).
   - Conekta · llave privada (`key_…`).
3. **Stripe › Developers › Webhooks:** agrega la URL que muestra esa misma pantalla (`https://{dominio}/webhooks/stripe.php`) con los eventos `checkout.session.completed`, `customer.subscription.created`, `customer.subscription.updated`, `customer.subscription.deleted`, `invoice.paid`, `invoice.payment_failed` y `charge.refunded`. Copia el *Signing secret* a la configuración.
4. **Stripe › Settings › Billing › Customer portal:** guarda la configuración del portal una vez; sin eso, el botón "Cambiar tarjeta o ver facturas" falla.
5. **Conekta › Desarrolladores › Webhooks:** agrega `https://{dominio}/webhooks/conekta.php`. Conekta no firma con un secreto compartido; por eso el portal vuelve a consultar cada orden con la llave privada antes de activar un plan.
6. Programa una vez al día:
   ```
   php database/vencer_suscripciones.php
   ```

Los precios de Stripe se crean solos desde la tabla `planes`. Se buscan por `lookup_key` (`armaqr_{plan}_{periodicidad}_{centavos}`), así que si cambias un precio se crea otro nuevo; las suscripciones existentes conservan el precio anterior hasta que el cliente cambie de plan.

## Pruebas locales

- Los webhooks necesitan una URL pública. Con Stripe CLI: `stripe listen --forward-to localhost/QR/webhooks/stripe.php`, y usa el `whsec_` que imprime.
- Tarjeta de prueba de Stripe: `4242 4242 4242 4242`, con cualquier fecha futura y cualquier CVC.
- Si cURL falla con un error de certificado en XAMPP, configura `curl.cainfo` en `php.ini` apuntando a un `cacert.pem` vigente.

## Pendiente

- Facturación CFDI: el formulario de datos fiscales y el botón "Descargar factura" siguen simulados.
- Reembolsos parciales: Administración › Pagos solo hace reembolsos totales, y no cambia el plan del cliente.
