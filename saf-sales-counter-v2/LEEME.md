# SAF - Mesón de ventas v2.1

Basado en `aprsoft/saf` rama `develop` SHA `63561ff`.

## Instalación

1. Descomprimir y copiar `app/`, `resources/`, `routes/` y `tests/` a la raíz de SAF, rama `feature/sales-counter-v2`.
2. Ejecutar `bash instalar-rutas.sh` desde la raíz (registra las rutas sin modificar las existentes).
3. Abrir **https://saf.test/sales-counter-v2**. Solo existe una ruta nueva.
4. Verificar con `php artisan route:list --path=sales-counter-v2` y `php artisan test tests/Unit/SalesCounter/DocumentItemServiceTest.php`.

## Diseño

- **SalesCounter.php + sales-counter.blade.php:** única vista maestra, selector de Nota de Venta (2) o Cotización (19).
- **Notes/CreateSalesNote.php:** acciones específicas de nota de venta.
- **Quotations/CreateQuotation.php:** acciones específicas de cotización.
- **SalesDocumentEditor.php:** interacción Livewire común.
- **DocumentItemService.php:** persistencia, precios y totales.
- **Forms/**: estado y validación de entradas.
- Parciales reutilizados para catálogo, cliente, detalle y resumen.
- Evita cambiar de tipo si hay un documento activo, para no perder su estado de edición.

El mesón anterior y su ruta `sales-notes/customer/create` no se reemplazan.
No se emiten DTE ni se modifica inventario.

No se ejecutaron las pruebas dentro del entorno SAF/Lerd; se verificó sintaxis PHP local cuando estuvo disponible.
