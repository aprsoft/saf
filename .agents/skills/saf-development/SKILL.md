---
name: saf-development
description: >-
  Analizar, implementar, modificar o revisar funcionalidades del sistema comercial SAF
  (ventas, mesón, caja, documentos, DTE, pagos, clientes, productos, inventario,
  empresas y sucursales). Usar cuando una tarea afecte reglas de negocio o
  arquitectura propias de SAF.
---

# Desarrollo del sistema comercial SAF

## Propósito

SAF es una plataforma comercial adaptable a cualquier rubro. Su arquitectura objetivo es multiempresa y multisucursal, **aunque el aislamiento completo no está implementado todavía**. Existe un solo motor comercial: caja integrada o puestos de mesón/caja separados son organizaciones operativas distintas del mismo proceso, no aplicaciones ni motores independientes. Despacho puede ser un puesto adicional.

## Flujo eficiente por tarea

1. Leer `AGENTS.md` y respetar las reglas de Laravel Boost. Determinar qué archivos intervienen **en la tarea solicitada**; no recorrer todo el repositorio indiscriminadamente.
2. Activar las skills específicas relevantes (Laravel, Livewire, Tailwind, Spatie, testing). Consultar Laravel Boost MCP para esquema, rutas o documentación **solo si afecta la respuesta**.
3. Identificar el comportamiento existente, las invariantes que deben conservarse y las diferencias entre lo implementado y lo planificado.
4. Proponer el cambio mínimo que reutiliza el motor comercial. Para modificaciones de riesgo (datos, DTE, permisos, multiempresa), explicar consecuencias antes de ejecutar.
5. Implementar con convenciones locales, validación del lado servidor y pruebas enfocadas.
6. Resumir archivos cambiados, pruebas ejecutadas o no ejecutadas, riesgos y decisiones pendientes.

## Puntos de entrada comprobados en `feature/sales-counter-v2` (22-11-2026)

| Área | Archivos de referencia |
| --- | --- |
| Documentos, ítems y precios | `app/Services/Sales/DocumentItemService.php` |
| Descuentos y totales | `app/Services/Sales/GlobalDiscountService.php`, `app/Models/Document.php` |
| Cobro compartido | `app/Services/Sales/CheckoutService.php` |
| Mesón / cotizaciones / notas | `app/Livewire/SalesCounters/`, `app/Http/Controllers/SalesCounterController.php` |
| Pago | `app/Livewire/Payments/Customer/CreatePayment.php` |
| Tipo de puesto | `app/Models/Company.php`, migración `2026_11_21_130000_add_checkout_mode_to_companies_table.php` |
| Emisión DTE | `app/Services/Dte/Post/`, tabla `dte_emission_attempts` |
| Tests relacionados | `tests/Feature/Sales/`, `tests/Feature/Payments/`, `tests/Unit/Sales/` |

Estos son **puntos de partida, no un mapa exhaustivo ni garantía de ejecución correcta**. Vuelve a verificar si el repositorio cambió.

## Invariantes comerciales

- Un único conjunto de reglas para documentos, ítems, descuentos, pagos y DTE. No duplicar servicios por modalidad de puesto.
- La interfaz y las transiciones operativas pueden diferir: en caja integrada se cobra en el mismo puesto; en puestos separados se envía el pedido a caja, que cobra. Mantener el mismo núcleo comercial.
- No tomar `Company::firstOrFail()` como patrón multiempresa definitivo. Antes de agregar sucursales, definir alcance y autorización de empresa/sucursal; filtrar operaciones por ese contexto.
- No asumir que toda venta implica salida inmediata de inventario ni que todo pago está confirmado antes de recibir respuesta de la API.
- Cualquier intento DTE previo o resultado incierto se concilia antes de permitir otro envío. No perder la idempotencia, el folio ni la trazabilidad.
- No modificar tablas ni ejecutar operaciones destructivas por iniciativa propia. Solicitar autorización para operaciones de riesgo.

## Criterios de verificación

- Cambios de ventas: verificar cálculos, descuentos, estados de documento y ambos arreglos de puesto.
- Cambios de cobro/DTE: verificar autorización, duplicados, rechazo de API, respuesta incierta, persistencia y ausencia de cobros falsos.
- Cambios de empresa/sucursal: verificar aislamiento cruzado, permisos y asociación correcta de documentos, pagos, existencias y parámetros tributarios.
- Ejecutar pruebas enfocadas como `php artisan test --compact tests/Feature/Sales/ModalidadMesonTest.php` cuando corresponda y sea seguro. Para PHP modificado, aplicar el formato indicado por `AGENTS.md`.
- Si una prueba o verificación no pudo ejecutarse, informarlo sin afirmar que pasó.
