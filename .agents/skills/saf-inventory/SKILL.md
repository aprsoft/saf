---
name: saf-inventory
description: >-
  Analizar, diseñar, implementar o revisar productos, servicios, categorías,
  unidades de medida y conversiones, listas de precios, bodegas, movimientos,
  existencias, ajustes, traspasos, reservas y despacho del sistema SAF.
  Usar en tareas de inventario o cuando una venta, compra o entrega pueda afectar stock.
---

# SAF — Inventario, productos y bodegas

## Identidad y alcance

SAF es una plataforma comercial adaptable a distintos rubros, con una **arquitectura objetivo** multiempresa y multisucursal. El aislamiento completo por empresa/sucursal **todavía no está implementado** en la rama revisada (`feature/sales-counter-v2`, 22-11-2026): no se debe presentar como funcionalidad existente.

**Un único motor comercial** sirve tanto para caja integrada como para mesón y caja separados. La ubicación física del cobro no define por sí sola el momento de salida de inventario. Las políticas de reserva, entrega y despacho deben definirse explícitamente.

Esta skill complementa `saf-development` y las instrucciones de `AGENTS.md`; no las sustituye. Se usa para reglas funcionales de inventario, no para imponer una arquitectura de terceros.

## Flujo de trabajo obligatorio y eficiente

1. Leer `AGENTS.md` y activar `saf-development` cuando la tarea afecte arquitectura o ventas. Leer únicamente los archivos necesarios para el caso concreto, no todo el repositorio.
2. Consultar `references/modelo-actual.md` cuando se necesiten nombres de tablas, relaciones, reglas pendientes o referencias conceptuales. Verificar el código real si se ha actualizado desde la versión documentada.
3. Inspeccionar las migraciones y modelos concretos con Laravel Boost MCP (`database-schema` para estructura actual; `search-docs` para APIs de Laravel/Livewire dependientes de versión). Evitar consultas redundantes; **no ejecutar escritura** con `database-query`.
4. Separar siempre: **implementado y comprobado**, **inferido del código**, **propuesta aún no implementada**. No inventar servicios, estados, columnas o relaciones.
5. Antes de implementar, describir el hecho de negocio que provoca cada entrada/salida, la bodega implicada, la unidad base, la trazabilidad, las validaciones y cómo revertir sin perder historial.
6. Reutilizar modelos y servicios reales antes de agregar nuevos. Proponer la mínima modificación compatible. Para migraciones, cambios de existencias, datos o documentos tributarios, exponer riesgos y obtener autorización antes de operaciones destructivas.
7. Agregar o ajustar pruebas focalizadas; ejecutar las relevantes cuando sea posible. Informar con precisión qué se verificó y qué no.

## Invariantes del dominio que deben guiar el diseño

- **Producto y servicio no son lo mismo.** `product_services.tipo` distingue `PRODUCTO`/`SERVICIO`; comprobar `controla_stock` antes de cualquier movimiento. No descontar existencias automáticamente a servicios ni a artículos sin control de stock.
- **Una unidad de stock por artículo.** Para un producto con inventario, definir la unidad base (`stock_unit_id`). `purchase_unit_id`, `sales_unit_id` y `product_units.factor_conversion` permiten unidades diferentes; **confirmar primero la dirección del factor de conversión**, porque el esquema por sí solo no la documenta.
- **Convertir antes de contabilizar movimientos.** La cantidad física registrada en `inventory_movements.cantidad_base` se expresa en unidad base. Evitar redondeos arbitrarios y conversiones con coma flotante cuando la precisión importe; respetar `units.permite_decimales` y las escalas decimales del esquema.
- **Separar precios y existencias.** `product_prices` / `price_lists` determinan precios; un precio no define stock, costo de inventario ni equivalencia de unidades.
- **Existencia por producto y bodega.** Obtenerla desde movimientos válidos según una convención explícita de signos y ajustes. El esquema actual contiene `tipo` (`ENTRADA`, `SALIDA`, `AJUSTE`) y `cantidad_base`, pero **no fija por sí solo el signo de los ajustes**: definir y probar esta regla antes de calcular saldos.
- **Trazabilidad y no duplicación.** Cada evento de inventario debe quedar vinculado a su operación de origen. Proteger movimientos frente a reintentos, eventos duplicados, concurrencia y anulaciones. No alterar ni borrar silenciosamente movimientos históricos ya confirmados; preferir movimientos compensatorios trazables cuando corresponda.
- **Traspasos.** Una transferencia confirmada entre bodegas debería registrar salida de origen y entrada a destino de manera atómica, enlazadas por la misma operación. Comprobar y definir política de tránsito si salida y recepción ocurren en distintos momentos.
- **Venta, pago, DTE y despacho son eventos distintos.** Ninguno debe descontar stock por defecto sin una política comercial aprobada. Evitar descuento doble si se genera una nota de venta, se cobra y luego se despacha.
- **Inventario disponible ≠ inventario físico.** No asumir reservas, stock comprometido, stock mínimo, lotes, series o valoración financiera implementados; diseñarlos solo cuando se soliciten y definir reglas primero.
- **Multiempresa y multisucursal es objetivo, no estado actual.** No usar `Company::firstOrFail()` como solución final ni mezclar inventario de distintas empresas. Antes de implementar el aislamiento, definir compañía, sucursal, bodegas, usuarios autorizados, catálogo compartido/aislado y claves únicas por alcance.
- **Valorización pendiente.** No suponer promedio ponderado, FIFO u otro método de costo. `costo_unitario_base` existe, pero un atributo de costo no constituye por sí solo un sistema de valorización.

## Casos que debes probar cuando corresponda

- Entrada, salida y ajuste; cero, valores negativos, límites de precisión y saldos simultáneos.
- Conversión compra/venta/stock: unidades, cajas y cantidades fraccionarias donde estén permitidas.
- Producto con control de stock frente a servicio o producto sin control.
- Transferencia íntegra, error en la segunda pierna y reintentos sin duplicar movimientos.
- Venta pendiente, pagada, anulada, despachada parcial/completa (si dichos estados existen); demostrar que el movimiento ocurre exactamente una vez.
- Restricciones por bodega, compañía y sucursal **cuando esas capacidades se implementen**.

## Forma de responder

En español, técnico y directo. Para revisiones, entregar: **comportamiento actual → hallazgos concretos → riesgos → propuesta mínima → pruebas**. Citar rutas y métodos reales. Evitar explicaciones extensas y lecturas indiscriminadas. Nunca reportar pruebas como aprobadas sin ejecutarlas.

## Referencias conceptuales (no copiar estructura ni código)

ERPNext aporta patrones de registro de entradas/salidas/transferencias, libro de movimientos y unidades de medida. Aplicarlos solo después de contrastarlos con SAF:
- https://docs.frappe.io/erpnext/stock-entry
- https://docs.frappe.io/erpnext/stock-ledger
- https://docs.frappe.io/erpnext/Selling-in-different-UOM
- https://docs.frappe.io/erpnext/purchasing-in-different-unit
