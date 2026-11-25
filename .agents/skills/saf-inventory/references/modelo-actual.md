# SAF — Mapa de productos e inventario y decisiones pendientes

**Fuente comprobada:** `aprsoft/saf`, rama `feature/sales-counter-v2`, esquema/migraciones y modelos consultados el 22-11-2026. Este documento es una fotografía del repositorio de ese momento, no una descripción garantizada de la base de datos que hoy está desplegada. Para cambios, revalidar con Laravel Boost MCP y el código actual.

## Esquema realmente presente

| Tabla / modelo | Campos / relaciones relevantes |
| --- | --- |
| `product_services` / `ProductService` | `id`, `category_id`, `codigo` único global, `nombre`, `tipo` (`PRODUCTO`, `SERVICIO`), `stock_unit_id`, `purchase_unit_id`, `sales_unit_id`, `controla_stock`, `afecto_iva`, `activo`, `obs`, soft deletes. Relaciones a categoría, tres unidades, `ProductUnit`, `ProductPrice` e `InventoryMovement` |
| `units` / `Unit` | `nombre`, `simbolo` único global, `permite_decimales` |
| `product_units` / `ProductUnit` | `product_service_id`, `unit_id`, `factor_conversion DECIMAL(18,6)`; único por producto/unidad |
| `price_lists` / `PriceList` | `nombre` único global, `tipo` (`COMPRA`, `VENTA`), `incluye_iva`, `activo` |
| `product_prices` / `ProductPrice` | `product_service_id`, `unit_id`, `price_list_id`, `precio DECIMAL(18,2)`; único por producto/unidad/lista |
| `warehouses` / `Warehouse` | `codigo` único global, `nombre`, `ubicacion`, `activo`; relación a movimientos |
| `inventory_movements` / `InventoryMovement` | `product_service_id`, `warehouse_id`, `tipo` (`ENTRADA`,`SALIDA`,`AJUSTE`), `cantidad_base DECIMAL(18,6)`, `costo_unitario_base DECIMAL(18,4)` nullable, `referencia`, `observacion`, timestamps; índice por producto y bodega |

Los modelos de inventario son ligeros: `Warehouse` expone `movements()`, `ProductService` expone `inventoryMovements()`, `InventoryMovement` expone `product()` y `warehouse()`. La existencia de estas tablas y relaciones **no demuestra** que el registro, consulta de existencias, reserva, transferencia, despacho ni valorización estén implementados y probados.

## Interacción actual con ventas

- `app/Services/Sales/DocumentItemService.php` administra documentos e ítems, **no emite DTE ni descuenta inventario** según su contrato documentado.
- `app/Services/Sales/CheckoutService.php` centraliza cobro/confirmación DTE en caja integrada y caja central; no dar por hecho que descontar existencias forma parte de este servicio.
- El puesto integrado y el separado usan el mismo núcleo comercial. No agregar dos motores o dos reglas de stock independientes.
- `app/Models/Company.php` y distintos servicios consultan `Company::firstOrFail()`; no asumir resolución multiempresa/sucursal resuelta.

## Decisiones funcionales por acordar antes de codificar

1. **Momento de salida de stock:** al confirmar venta, al pagar, al emitir DTE, al entregar físicamente, u otra política configurable. Definir reversiones, entregas parciales y devoluciones.
2. **Reservas:** si una nota en proceso, apartada o pendiente compromete stock; plazo, expiración y liberación.
3. **Política de saldos negativos y concurrencia:** prohibir/permitir, bloqueos y control transaccional frente a ventas simultáneas.
4. **Significado del ajuste:** si `cantidad_base` en `AJUSTE` es diferencia positiva/negativa o saldo físico final. Evitar asumir por el nombre.
5. **Traspaso:** identificar operación padre, dos movimientos atómicos o proceso en tránsito con recepción posterior; eventos idempotentes.
6. **Conversión:** dirección exacta de `factor_conversion` (unidades base por unidad comercial u otra), restricciones de validación y política de redondeo.
7. **Costo/valorización:** promedio ponderado, FIFO u otro. Decidir cómo se relacionan costos de compra, entradas, salidas y ajustes.
8. **Multiempresa y multisucursal:** resolver compañía y sucursal del usuario; propiedad de bodega; asociaciones de documentos, productos y movimientos; aislamiento; cambio de unicidad de códigos si corresponde.
9. **Capacidades avanzadas:** lotes, series, vencimientos, ubicaciones dentro de bodega, stock mínimo, reposición y auditoría, solo si se necesitan.

## Ejemplos conceptuales, NO funcionalidad existente

- Artículo vendido en cajas: si se acuerda que 1 caja = 12 unidades base, comprar 3 cajas representa 36 unidades de stock. El factor debe seguir una convención única en toda la aplicación.
- Traspaso de 5 unidades: salida de bodega A y entrada en bodega B, ambas vinculadas a un mismo identificador de operación y registradas sin duplicación.
- Venta con caja central: crear nota, cobrar y despachar son eventos separados; sin política definida, no es correcto asumir en cuál se rebaja inventario.

## Fuentes conceptuales externas

- ERPNext Stock Entry: https://docs.frappe.io/erpnext/stock-entry
- ERPNext Stock Ledger: https://docs.frappe.io/erpnext/stock-ledger
- ERPNext UOM (venta): https://docs.frappe.io/erpnext/Selling-in-different-UOM
- ERPNext UOM (compra): https://docs.frappe.io/erpnext/purchasing-in-different-unit
