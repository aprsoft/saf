# SAF · Mesón de ventas v2

Código basado en `aprsoft/saf` · `develop` SHA `63561ff` (17-11-2026).

## Instalación en `feature/sales-counter-v2`

1. Copiar `app/`, `resources/`, `routes/`, `tests/` e `instalar-rutas.sh` a la raíz de SAF. No se sobreescribe el mesón anterior.
2. Ejecutar `bash instalar-rutas.sh` desde la raíz de SAF (agrega un `require` a `routes/web.php` una sola vez).
3. Abrir `https://saf.test/sales-counter-v2/notes` o `https://saf.test/sales-counter-v2/quotations`.
4. Ejecutar `php artisan route:list --path=sales-counter-v2` y `php artisan test tests/Unit/SalesCounter/DocumentItemServiceTest.php`.

## Incluye

- Notas de venta: productos, cliente, descuento, recuperación, espera, anulación y envío a caja.
- Cotizaciones: creación, recuperación, guardado y renovación manual de vigencia.
- Servicio compartido con transacciones, bloqueo de documentos, precios del catálogo y totales.
- Form Objects Livewire 4 y parciales Blade reutilizables.
- Sin nuevas dependencias, JavaScript ni migraciones.
- Se mantienen `Document`/`Detail`, los tipos 2/19 y el estado `FINALIZADO` utilizado por caja.

## Restricciones

- Solo modo punto de venta `BASICO`, como el componente actual.
- No emite DTE ni genera movimientos de inventario.
- Precio público tratado como precio final CLP para preservar la regla actual. Descuentos porcentuales enteros, IVA del 19%.
- No se han ejecutado pruebas con la aplicación SAF/Lerd; solo comprobación sintáctica PHP del paquete.
