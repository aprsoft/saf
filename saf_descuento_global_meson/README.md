# SAF — descuento global del mesón a caja, cotización PDF y DTE

Base verificada en GitHub: `aprsoft/saf`, `develop` y `feature/sales-counter-v2` (commit `c1d8e977b87eb0b5074a61f3aad667c8a8143236`).

## Archivos que entrega

El paquete **genera los siguientes nueve archivos completos**, combinando el contenido original verificado de SAF con los cambios:

1. `app/Livewire/SalesCounters/Forms/SalesCounterForm.php`
2. `app/Livewire/SalesCounters/SalesDocumentEditor.php`
3. `app/Services/Sales/DocumentItemService.php`
4. `app/Livewire/Payments/Customer/CreatePayment.php`
5. `app/Services/Pdf/Classes/QuotationPdf.php`
6. `resources/views/livewire/sales-counters/partials/resumen.blade.php`
7. `resources/views/pdf/documents/quotation.blade.php`
8. `database/migrations/2026_11_20_230000_add_sales_totals_to_documents_table.php` (nuevo)
9. `tests/Feature/Sales/DescuentoGlobalMesonTest.php` (nuevo; seis pruebas)

Los archivos 1, 6, 8 y 9 también están incluidos como **código completo directamente en el ZIP**. Los demás se generan desde los archivos completos de tu propia rama, no desde versiones antiguas o reconstrucciones parciales. Así el aplicador **se niega** a pisar cambios que no coincidan con el código comprobado en GitHub.

## Paso 1. Descomprimir en el proyecto

Desde `~/Proyectos/saf`:

```bash
unzip -o ~/Descargas/saf_descuento_global_meson_codigo_completo.zip -d .
```

Ajusta la ruta al lugar donde descargaste el ZIP.

## Paso 2. Generar todos los archivos completos para revisión (sin modificar SAF)

```bash
php saf_descuento_global_meson/preparar_archivos.php --repo="$PWD"
```

Generará un directorio `../saf-descuento-global-preview-<PID>` con todos los archivos completos. Si algún archivo local cambió frente a la versión revisada en GitHub, el programa se detiene sin escribir cambios en SAF.

## Paso 3. Aplicar si la revisión es correcta

```bash
php saf_descuento_global_meson/preparar_archivos.php --repo="$PWD" --apply
```

Se crea una copia de los siete archivos originales **fuera del repositorio**, bajo `../saf-descuento-global-backup-...`, antes de reemplazarlos. No se realizan operaciones Git destructivas, commits ni push.

## Paso 4. Verificar

```bash
git status --short
git diff --check
git diff --stat
php artisan migrate
```

Para pruebas con SQLite aislado en memoria mediante Lerd:

```bash
LERD_PASSTHROUGH_ENV=APP_ENV,DB_CONNECTION,DB_DATABASE,DB_URL APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= lerd php artisan test
```

No uses `git-s` antes de revisar los cambios: ese comando en tu entorno crea commit y hace push automáticamente.

## Alcance y comportamiento

- Los descuentos individuales de los ítems se mantienen; el descuento global se calcula sobre la suma de los `MontoItem` ya descontados.
- Descuento global porcentual (0–99 %) o monto entero CLP, validado en el servidor.
- Resumen del mesón muestra subtotal, descuento, neto, IVA, exento y total.
- Guardar ítem, guardar cotización y enviar nota a caja persisten el descuento global en la misma transacción.
- Editar o eliminar ítems recalcula los totales. Si un descuento fijo deja de ser válido tras eliminar un ítem, la operación se revierte y el usuario debe corregir el descuento antes.
- Recuperar nota/cotización carga el descuento desde el documento.
- Caja carga el descuento y el monto recibido inicial del total ya descontado; permite editarlo antes de emitir.
- Boleta y factura continúan usando `GlobalDiscountService` en sus mappers para `DscRcgGlobal`, sin agregar una segunda aplicación.
- Cotización PDF muestra el descuento global y usa el mismo cálculo que el mesón.
- La migración nueva agrega cinco columnas de totales que el servicio existente intenta persistir y que no aparecen en las migraciones de GitHub. Su `down()` es intencionalmente conservador: no elimina columnas históricas que pudieran existir localmente.

**No ejecutado:** pruebas Pest sobre el entorno Lerd ni emisión real de DTE. Solo se revisó el código en GitHub y se comprobó la sintaxis PHP de los archivos independientes. `preparar_archivos.php` valida y comprueba sintaxis de todos los archivos generados antes de aplicar.
