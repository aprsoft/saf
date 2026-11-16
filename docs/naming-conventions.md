# Convenciones de nombres y carpetas — SAF

Referencia: rama `develop`. Esta guía es una convención de proyecto, no una imposición de Laravel.

## PHP y PSR-4

- Conservar el autoload `App\\` → `app/` de Composer.
- Utilizar `PascalCase` para directorios que formen parte de un namespace PHP.
- Agrupar controladores por dominio: `Http/Controllers/Customers`, `Documents`, `Payments`, `Reports`.
- Mantener los modelos Eloquent en `app/Models` y las Policies en `app/Policies`.
- Agrupar nuevos componentes Livewire y servicios de aplicación por dominio únicamente cuando se revisen todas sus referencias.
- Para controladores en subdirectorios importar `App\\Http\\Controllers\\Controller` cuando hereden de la clase base.

## Vistas, URL y base de datos

- Carpetas de vistas: inglés, minúsculas y plural, por ejemplo `customers` y `documents`.
- Archivos Blade: `kebab-case` cuando el nombre tenga varias palabras.
- Rutas nombradas: minúsculas con puntos, como `customers.index`.
- URL: segmentos en `kebab-case`.
- Tablas: `snake_case` plural; columnas: `snake_case`.

## Seguridad al renombrar

1. Comprobar el código real y los cambios locales frente a `origin/develop`.
2. Localizar todas las referencias: `use`, `namespace`, controladores, clases, componentes Livewire, vistas, rutas y pruebas.
3. Actualizar todas las referencias en la misma modificación lógica.
4. Mantener los nombres públicos de las rutas cuando no forme parte del requerimiento cambiarlos.
5. Ejecutar `composer dump-autoload`, `php artisan route:list`, `php artisan view:cache`, `php artisan optimize:clear` y `php artisan test` antes de dar por verificada una migración.
6. No alterar tablas, migraciones ni lógica comercial únicamente por motivos de nomenclatura.

## Migración inicial

Se reorganizaron los controladores Customer, Document, Payment y CashReport en directorios por dominio. La normalización restante de Livewire, vistas, servicios y rutas queda sujeta a la revisión completa de dependencias y pruebas de integración.
