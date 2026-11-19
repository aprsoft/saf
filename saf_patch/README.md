# SAF — Vista DTE clientes (feature/sales-counter-v2)

Archivos preparados a partir de la rama remota `feature/sales-counter-v2` del repositorio `aprsoft/saf`.

## Instalación local

```bash
cd ~/Proyectos/saf
git status --short
git branch --show-current
# Revisar previamente diferencias locales en los cuatro archivos.
cp -v /RUTA_EXTRAIDA/app/Http/Controllers/DocumentController.php app/Http/Controllers/DocumentController.php
cp -v /RUTA_EXTRAIDA/app/Livewire/Dtes/Customers/ShowDte.php app/Livewire/Dtes/Customers/ShowDte.php
cp -v /RUTA_EXTRAIDA/resources/views/dtes/customers/show.blade.php resources/views/dtes/customers/show.blade.php
cp -v /RUTA_EXTRAIDA/resources/views/livewire/dtes/customers/show-dte.blade.php resources/views/livewire/dtes/customers/show-dte.blade.php
php artisan view:cache
php artisan route:list --name=dtes.customer.show
php artisan test
```

No copiar sin revisar cambios locales: los archivos de esta entrega son versiones completas basadas en GitHub, no un merge automático.

## Límites conocidos

- PDF e email reutilizan `livewire:pdf.pdf-actions`. PDF sigue siendo condicionado por la autorización y la validez del XML/timbre.
- Botones de nota de crédito y guía están deshabilitados: todavía no se validó una ruta/servicio de emisión compatible.
- El giro se obtiene, si existe, desde `GiroRecep`, `giro_cliente` o `giro`. Si en tu esquema el nombre real es otro, cambiar el acceso en la vista.
- Para guías/notas se enseña referencia solo si `parent_id` existe y el tipo corresponde. Confirma el campo real de código tributario en `typedocuments`.
- Las clases PHP pasaron análisis de sintaxis (`php -l`). No se ejecutaron pruebas Laravel ni se inspeccionaron los cambios locales.
