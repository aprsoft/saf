# Impresión directa de DTE con QZ Tray

## Alcance

SAF continúa siendo web. El navegador comunica el PDF a QZ Tray instalado en el PC del puesto, sin abrir el visor ni el diálogo de impresión. La impresora debe estar instalada con su controlador. Se reutiliza el PDF A4 existente; esta entrega no crea un formato térmico ni garantiza compatibilidad con todas las impresoras.

`PdfActions` permanece intacto. No se modifica `companies` ni se agregan migraciones. `config/printing.php`, opción `enabled`, define el comportamiento global desde código. Al desactivar esta opción se conserva el flujo anterior, sin abrir automáticamente otro PDF.

## Archivos

- `config/printing.php`: activación global y rutas de certificados.
- `app/Services/Printing/QzSigningService.php`: lectura de credenciales y firma RSA/SHA512.
- `app/Http/Controllers/Printing/QzController.php`: certificado público, preparación de PDF y firma restringida.
- `routes/web.php`: rutas `printing.qz.certificate`, `printing.qz.prepare` y `printing.qz.sign`, autenticadas, con CSRF para POST y límite de solicitudes.
- `app/Livewire/Printing/PrinterSettings.php` y su vista: panel independiente para conectar, seleccionar impresora, imprimir prueba y reintentar fallos.
- `resources/js/printing/qz.js`: conexión local, mensajes firmados, selección y estado de envío.
- `resources/js/app.js` y `resources/views/layouts/app.blade.php`: registro y montaje del panel.
- `app/Livewire/Payments/Customer/CreatePayment.php`: solicitud posterior al cobro confirmado.
- `tests/Feature/Printing/QzPrintingTest.php`: seguridad y preparación.
- `tests/Feature/Sales/ModalidadMesonTest.php`: activación solo después del cobro confirmado, rechazo, incertidumbre y desactivación.

## Qué significa «aceptado» en esta implementación

`CheckoutService::cobrar()` exige `accepted === true`, folio positivo y XML con TED válido; luego guarda documento, pago e intento `CONFIRMADA` en una transacción. Solo después de regresar correctamente se solicita impresión.

El adaptador actual `DteApiResponse` deriva `accepted` de la respuesta del proveedor con TIMBRE, incluso cuando contiene WARNING. **Esto no implementa una consulta independiente de aceptación definitiva del SII.** Si el negocio exige ese estado posterior, debe incorporarse su consulta/conciliación y trasladar el disparador a esa confirmación. No se debe equiparar un HTTP 200 con aceptación.

## Ciclo de vida

1. El usuario confirma el cobro; se ejecuta el motor comercial existente.
2. Rechazo, XML inválido o resultado incierto: no se solicita impresión.
3. Aceptación y persistencia completas: caja integrada despacha `qz-print-dte`; caja central guarda `qz_print_document` en flash de sesión y redirige. El panel de destino recupera el ID.
4. El panel encola el ID en memoria y `sessionStorage` de esa pestaña/usuario. La impresora se recuerda en `localStorage` por navegador/origen.
5. QZ conecta al equipo local mediante WebSocket seguro. Laravel entrega únicamente el certificado público.
6. `prepare` verifica la política `DocumentPolicy::view`, el intento `CONFIRMADA`, el folio coincidente y el TED. Reutiliza `PdfGenerator` y genera base64. Nunca cobra ni llama al emisor DTE.
7. Laravel guarda los parámetros exactos del trabajo en caché durante cinco minutos, vinculados al usuario y a un ticket aleatorio. La prueba es un HTML fijo sin datos comerciales.
8. El cliente captura el JSON exacto antes de calcular su SHA256 mediante la API oficial `setSha256Type` y lo presenta al endpoint de firma; `sign` valida contenido, timestamp (60 segundos), ticket y usuario. Solo firma descubrimiento de impresoras o parámetros exactos preparados por SAF. Impide archivos, destinos de red arbitrarios, contenido sustituido, copias adicionales y otras operaciones QZ.
9. El servidor firma con SHA512 el hash hexadecimal SHA256 del JSON exacto, según el protocolo de QZ. El ticket permite una sola firma mediante una operación atómica en caché. La clave privada nunca llega al navegador.
10. QZ recibe el trabajo y lo entrega al controlador. El mensaje «enviado» **no garantiza papel impreso**.
11. Fallo o desconexión: permanece «sin confirmar». El operador revisa el papel y puede reenviar únicamente impresión, con una confirmación explícita para evitar duplicados. Nunca se repite cobro ni emisión.

El navegador debe seguir abierto para completar el envío. Al recargar, los trabajos pendientes/inciertos no se reenvían automáticamente. No existe cola durable de impresión en base de datos, recuperación entre dispositivos ni garantía de exactamente una copia física. El historial por pestaña evita repetir eventos locales; cerrar la pestaña puede perderlo. `PdfActions` sigue disponible para la recuperación manual habitual.

## Instalación del puesto y certificados sin licencia pagada

1. Instalar QZ Tray 2.2 o superior desde https://qz.io/download/ en Windows, Linux o macOS y habilitar su inicio con la sesión.
2. Instalar la impresora y comprobar una impresión desde el sistema operativo.
3. Para la prueba inicial, QZ Tray → Advanced → Site Manager → `+` → Create New permite generar claves de demostración y confiar localmente en ellas. Estas claves son para desarrollo.
4. Para producción sin certificado comercial, administrar una raíz propia y un certificado de firma compatible con QZ; instalar la confianza pública mediante `override.crt` o `authcert.override` en cada PC. Distribuir solo certificados públicos a los puestos, nunca la clave privada de producción. Gestionar vencimiento, renovación y sustitución en servidor y puestos.
5. En el servidor, aprovisionar `digital-certificate.txt` y `private-key.pem` (RSA, PKCS#8) en `storage/app/private/printing/` o en un volumen privado de secretos. La carpeta predeterminada está excluida de Git. Permitir lectura de la clave únicamente al proceso PHP; no usar `public/` ni el disco público.
6. Configurar, si se usan otras rutas:

```dotenv
QZ_CERTIFICATE_PATH=/ruta/privada/digital-certificate.txt
QZ_PRIVATE_KEY_PATH=/ruta/privada/private-key.pem
QZ_PRIVATE_KEY_PASSPHRASE=
```

7. Ejecutar `npm ci`, `npm run build` y actualizar las cachés de configuración/rutas del despliegue. Usar caché persistente compartida entre procesos PHP (por ejemplo, Redis o database); no `array` en producción.
8. Abrir SAF mediante HTTPS, desplegar «Impresora del puesto», conectar, seleccionar y probar. Confiar inicialmente en el certificado/sitio en QZ según su configuración. El navegador puede pedir permiso de acceso local; los certificados no eliminan permisos propios del navegador.
9. Verificar físicamente márgenes, legibilidad y timbre del PDF con la impresora real antes de usarlo en producción.

La integración JavaScript está fijada por `package-lock.json` (qz-tray 2.3.0). Las opciones firmadas se validan estrictamente; al actualizar QZ deben comprobarse nuevamente parámetros y compatibilidad.

## Seguridad y límites existentes

Se reutiliza la política de documentos de SAF. Actualmente su acceso es por rol/permiso, no un aislamiento multiempresa completo; esta integración no lo presenta como resuelto. Descubrimiento y prueba están disponibles para usuarios autenticados; los documentos requieren `documents.view` o el acceso equivalente ya definido por la política.

No se registran claves, PDF ni firmas en logs de aplicación. La caché contiene temporalmente el contenido del PDF y debe protegerse como los demás datos comerciales. La firma es de un solo uso desde la API, pero no constituye una garantía contra reenvío físico de una solicitud ya entregada al cliente.

Los móviles no están incluidos: requieren QZ como servidor de impresión de red, certificados de conexión y validación adicional de acceso local del navegador. No exponer los puertos de QZ a Internet.

## Verificación

```bash
php artisan test --compact tests/Feature/Printing/QzPrintingTest.php tests/Feature/Sales/ModalidadMesonTest.php
node --test tests/Unit/Printing/qz.test.js
npm run build
```

Las pruebas usan SQLite en memoria y dobles del proveedor/PDF; no emiten DTE ni envían trabajos reales. La instalación de QZ, certificados de producción y prueba física requieren acceso al equipo del local.

Referencias oficiales: https://qz.io/docs/signing · https://qz.io/docs/pixel · https://qz.io/docs/print-server
