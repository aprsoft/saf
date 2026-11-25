---
name: saf-efficient
description: >-
  Controlar longitud de respuestas y gasto de contexto al trabajar con Codex
  en SAF. Usar para análisis de código, correcciones, consultas de Laravel,
  Livewire, base de datos o Git cuando se pida una respuesta breve, rápida,
  económica, profunda o con alcance acotado; también se puede invocar
  expresamente como $saf-efficient.
---

# SAF: trabajo y respuestas eficientes

## Prioridad

Respetar `AGENTS.md`, las políticas de seguridad y las skills técnicas pertinentes.
Esta skill no sustituye `saf-development`, `livewire-development` ni Laravel Boost.
Usar primero los hechos del mensaje y los archivos ya disponibles en contexto.

## Preferencias controladas por el usuario

Leer `PREFERENCIAS.md` (en esta misma carpeta); es breve. El usuario puede
sobrescribir temporalmente cualquier preferencia en el mensaje, por ejemplo:
`PERFIL: PROFUNDO` o `RESPUESTA: MINIMA`.

### Perfil de trabajo

- `AHORRO`: delimitar un archivo o método por vez; leer solo lo indispensable;
  priorizar una respuesta corta. No investigar dependencias ajenas al problema.
- `EQUILIBRADO`: revisar los archivos directamente relacionados, usar MCP y
  pruebas focalizadas cuando ayuden; respuesta breve con fundamento.
- `PROFUNDO`: ampliar la inspección a flujos y dependencias relacionadas,
  contrastar alternativas y riesgos, y presentar diagnóstico estructurado.

El perfil no fija una cuota real de tokens ni limita los controles de seguridad.
Si faltan pruebas indispensables para afirmar algo, no afirmar que se verificó.

### Nivel de respuesta

- `MINIMA`: 1 a 3 frases, comando o cambio concreto si corresponde.
- `BREVE`: resumen en hasta 5 puntos, solo lo necesario para decidir o actuar.
- `DETALLADA`: explicación por etapas, hallazgos con evidencias y alternativas.

## Método para ahorrar contexto

1. Precisar el objetivo y los archivos afectados antes de buscar.
2. Buscar primero rutas, símbolos o fragmentos (`rg -n`, búsquedas de archivo,
   rangos de líneas). Evitar volcados recursivos y lecturas completas masivas.
3. Leer archivos completos solo cuando las dependencias o el riesgo lo exijan.
4. Usar Laravel Boost MCP cuando aporte evidencia decisiva; no hacer consultas
   repetidas ni llamadas MCP por mera formalidad. Reutilizar resultados vigentes.
5. Evitar volver a explicar el marco general de SAF si `AGENTS.md` ya lo define.
6. Para cambios de código, modificar el conjunto mínimo de archivos; ejecutar
   pruebas pertinentes y formateo exigido por `AGENTS.md`.
7. No ejecutar migraciones destructivas, cobros reales, emisión DTE ni operaciones
   de escritura de riesgo sin autorización explícita.
8. No repetir la pregunta ni detallar pasos obvios; informar con precisión qué
   se comprobó y qué no se comprobó.

## Formato de salida por defecto

- **Resultado:** respuesta o diagnóstico directo.
- **Acción:** comando o modificación concreta, si es necesaria.
- **Verificación:** solo pruebas ejecutadas, riesgos reales o incertidumbre material.

Omitir encabezados vacíos. Respetar el nivel de respuesta seleccionado.

## Anulación puntual

Si el usuario indica `SOLO DIAGNOSTICO`, no editar archivos.
Si indica `IMPLEMENTAR`, proceder según permisos y restricciones de la sesión.
Si indica `NO MCP`, no invocar Laravel Boost; explicar si esto impide comprobar
una afirmación esencial. Si pide análisis exhaustivo, priorizar exactitud y
cobertura aunque consuma más recursos.
