# Catálogo de ferretería — Laravel 12

Copiar `app/Models/*` y `database/migrations/*` y `database/seeders/FerreteriaSeeder.php` al proyecto.

Antes de ejecutar: comprobar que las tablas no existan, que los nombres de modelos no colisionen y que se está usando la conexión correcta (central/tenant). Hacer respaldo.

```bash
php artisan migrate
php artisan db:seed --class=FerreteriaSeeder
```

Opcional: añadir `$this->call(FerreteriaSeeder::class);` al `DatabaseSeeder` del ámbito correspondiente.

**Reglas a validar en servicios de aplicación:** factor_conversion > 0; precio >= 0; categoría sin ciclos; unidad base con factor 1; unidad de precio presente en product_units; productos de stock con unidades compatibles; impedir stock negativo cuando corresponda; movimientos en transacción; cantidades de salida negativas, entradas positivas, ajustes con signo; transferencias como par salida/entrada; servicios sin movimientos. `inventory_movements` no tiene stock precargado: se calcula sumando `cantidad_base` por producto/bodega. `costo_unitario_base` es opcional. No se incluyen impuestos especiales, lotes, variantes, embalajes físicos, trazabilidad de DTE ni movimientos reales iniciales.

**Nota:** las unidades CAJA/SACO/ROLLO tienen conversiones específicas por producto. El campo `purchase_unit_id` / `sales_unit_id` es una unidad predeterminada, no un límite para otras unidades. Los precios del seeder son ficticios, netos y en CLP. El seed no emite documentos ni modifica `details`.

## Datos de ejemplo

`FerreteriaSeeder` crea exactamente **100 productos y 10 servicios**, 11 categorías, 10 unidades y precios ficticios de público y mayorista. También agrega conversiones para sacos, cajas y rollos. No crea movimientos de stock. Es idempotente por código y reutiliza los registros existentes; no elimina productos ajenos.
