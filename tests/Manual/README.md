# Prueba manual OpenFactura (desarrollo)

Requiere MySQL dedicado, con empresa, tipos, usuario y al menos cinco clientes individualizados completos y cliente genérico ya cargados. No utiliza RefreshDatabase ni ejecuta migraciones. No ejecutar en producción.

```bash
OPENFACTURA_RUN_LIVE_TESTS=1 SAF_BD_PRUEBA=saf_testing DB_CONNECTION=mysql DB_DATABASE=saf_testing XDEBUG_MODE=off php artisan test tests/Manual/CrearDocumentosPruebaCajaTest.php
```

Cada ejecución crea un lote nuevo de 10 facturas, 10 boletas (5 genéricas), 5 NC de factura y 5 NC de boleta (3 genéricas de origen y 2 individualizadas). Los DTE se envían al endpoint exacto de desarrollo usando Company. Los pagos originales se registran como efectivo de prueba; no representa cobro físico. Las NC anulan totalmente (CodRef 1), se dejan NO APLICADA y el documento original ANULADO. El test no certifica disponibilidad comercial ni aceptación por SII.

Se detiene al primer error; conserva documentos aceptados y marca intentos inciertos para conciliación. No repita un lote incierto ni reinicie los IDs de la BD: el emisor usa saf-dte-ID como clave de idempotencia. Las emisiones externas no se revierten al fallar una prueba.
