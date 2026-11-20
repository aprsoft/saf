<?php

declare(strict_types=1);

/**
 * SAF: genera archivos PHP/Blade COMPLETOS a partir de los originales verificados
 * de feature/sales-counter-v2 @ c1d8e977, sin acceder a redes ni modificar Git.
 *
 * Uso:
 *   php preparar_archivos.php --repo=/home/ro/Proyectos/saf
 *   php preparar_archivos.php --repo=/home/ro/Proyectos/saf --apply
 *
 * Por defecto genera /tmp/saf-descuento-global-meson-{pid} para revisión.
 * --apply comprueba SHA, crea respaldo y copia reemplazos; sin commit ni push.
 */

function fail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function replaceExactlyOnce(string $src, string $old, string $new, string $path): string
{
    $hits = substr_count($src, $old);
    if ($hits !== 1) {
        fail("{$path}: patrón esperado presente {$hits} veces, se esperaba 1. No se ha aplicado nada.");
    }

    return str_replace($old, $new, $src);
}

function gitBlobSha(string $text): string
{
    return sha1('blob '.strlen($text)."\0".$text);
}

$opts = getopt('', ['repo:', 'apply']);
$repo = rtrim((string) ($opts['repo'] ?? ''), '/');
$apply = array_key_exists('apply', $opts);
if ($repo === '' || ! is_file("{$repo}/artisan") || ! is_file("{$repo}/composer.json")) {
    fail('Indica la raíz de SAF mediante --repo=/home/ro/Proyectos/saf.');
}
$repo = realpath($repo) ?: fail('Repositorio inaccesible.');
$scriptDir = __DIR__;

// Verificación de rama sin invocar comandos Git desde el contenedor Lerd.
$gitHead = is_file("{$repo}/.git/HEAD")
    ? trim((string) file_get_contents("{$repo}/.git/HEAD"))
    : '';
if ($gitHead !== 'ref: refs/heads/feature/sales-counter-v2') {
    fail('El directorio no está en la rama feature/sales-counter-v2 (o usa un worktree no soportado).');
}

// Hashes de blobs publicados en GitHub, rama feature/sales-counter-v2, c1d8e977.
$expected = [
    'app/Livewire/SalesCounters/Forms/SalesCounterForm.php' => '7beec32c45b3fa21034ac62508bed12708599ad2',
    'app/Livewire/SalesCounters/SalesDocumentEditor.php' => '50f8817716d64f8272132f551791747a38de194a',
    'app/Services/Sales/DocumentItemService.php' => '46352ad3c5ef665635d5ca4559ce8642e12aac20',
    'app/Livewire/Payments/Customer/CreatePayment.php' => '60520356b62488e27a11af33cd2cbe1d57ee7917',
    'app/Services/Pdf/Classes/QuotationPdf.php' => '393c9a486b6f61e44e5e548948ae0f73676bbd20',
    'resources/views/livewire/sales-counters/partials/resumen.blade.php' => '3d853ab881e0cc8e14134fc76f73833a8f14c668',
    'resources/views/pdf/documents/quotation.blade.php' => 'd862639524673321d5f633a1312d322ba1b702dc',
];
$originals = [];
foreach ($expected as $path => $hash) {
    $file = "{$repo}/{$path}";
    $content = is_file($file) ? file_get_contents($file) : false;
    if ($content === false) {
        fail("No se puede leer {$path}.");
    }
    $actual = gitBlobSha($content);
    if ($actual !== $hash) {
        fail("{$path}: SHA {$actual} diferente del código revisado ({$hash}). "
            .'Revisar diferencias locales antes de generar reemplazos.');
    }
    $originals[$path] = $content;
}

$files = $originals;

// 1. Form Object: misma terminología que PaymentForm, sin nuevas clases.
$path = 'app/Livewire/SalesCounters/Forms/SalesCounterForm.php';
$src = $files[$path];
$src = replaceExactlyOnce($src,
    "    public int \$diasValidez = 15;\n",
    "    public int \$diasValidez = 15;\n\n"
    ."    public string \$descuentoTipo = 'porcentaje';\n\n"
    ."    public string \$descuentoValor = '0';\n",
    $path);
$src = replaceExactlyOnce($src,
    "            'diasValidez' => ['required', 'integer', 'in:7,15,30,60'],\n",
    "            'diasValidez' => ['required', 'integer', 'in:7,15,30,60'],\n"
    ."            'descuentoTipo' => ['required', 'in:porcentaje,monto'],\n"
    ."            'descuentoValor' => \$this->descuentoTipo === 'porcentaje'\n"
    ."                ? ['required', 'integer', 'between:0,99']\n"
    ."                : ['required', 'integer', 'min:0'],\n",
    $path);
$files[$path] = $src;

// 2. Lógica comercial: única fuente de cálculo de totales y descuentos.
$path = 'app/Services/Sales/DocumentItemService.php';
$src = $files[$path];
$needle = <<<'OLD'
    public function guardarItem(int $tipo, int $usuarioId, ?int $documentoId, array $formulario, int $productoId, ?int $detalleId, array $item): Document
OLD;
$new = <<<'NEW'
    /** Valida y persiste el descuento global solamente en documentos editables del vendedor. */
    public function establecerDescuentoGlobal(int $id, int $tipo, int $usuarioId, array $formulario): void
    {
        abort_unless(in_array($tipo, [self::NOTA, self::COTIZACION], true), 422);

        DB::transaction(function () use ($id, $tipo, $usuarioId, $formulario): void {
            $documento = $this->editable($id, $tipo, $usuarioId, true);
            $this->actualizarDescuentoEnDocumento($documento, $formulario);
            $this->actualizarTotales($documento);
        });
    }

    private function actualizarDescuentoEnDocumento(Document $documento, array $formulario): void
    {
        $tipo = (string) ($formulario['descuentoTipo'] ?? '');
        $valor = filter_var($formulario['descuentoValor'] ?? null, FILTER_VALIDATE_INT);

        if ($valor === false || $valor === null) {
            throw ValidationException::withMessages([
                'form.descuentoValor' => 'Ingrese un descuento global entero válido.',
            ]);
        }

        // Revalida contra los ítems persistidos. No acepta descuentos que
        // anulen el total ni partidas no facturables sin tratamiento especial.
        app(GlobalDiscountService::class)->calcular(
            $documento->details()->vigente()->get(),
            $tipo,
            (int) $valor
        );

        $documento->update([
            'descuento_global_tipo' => $tipo,
            'descuento_global_valor' => (int) $valor,
        ]);
    }

    public function guardarItem(int $tipo, int $usuarioId, ?int $documentoId, array $formulario, int $productoId, ?int $detalleId, array $item): Document
NEW;
$src = replaceExactlyOnce($src, $needle, $new, $path);
$src = replaceExactlyOnce($src,
    <<<'OLD'
            $documento->update($this->cabecera($formulario));
            $this->actualizarTotales($documento);
OLD,
    <<<'NEW'
            $documento->update($this->cabecera($formulario));
            $this->actualizarDescuentoEnDocumento($documento, $formulario);
            $this->actualizarTotales($documento);
NEW,
    $path);
$src = replaceExactlyOnce($src,
    <<<'OLD'
    private function actualizarTotales(Document $documento): void
    {
        $totales = $this->totalizarDetalles(
            $documento->details()->get([
                'estado_detalle',
                'MontoNetoItem',
                'ivaItem',
                'MontoExeItem',
                'MontoItem',
            ])
        );

        $documento->update([
            ...$totales,
            'valor_pagar' => $totales['monto_total'],
        ]);
    }
OLD,
    <<<'NEW'
    private function actualizarTotales(Document $documento): void
    {
        // El descuento global se aplica solo una vez al subtotal ya descontado
        // por ítem; no se modifica MontoItem ni sus importes originales.
        $totales = app(GlobalDiscountService::class)->paraDocumento($documento);

        $documento->update([
            'monto_neto' => $totales['monto_neto'],
            'monto_iva' => $totales['monto_iva'],
            'monto_exento' => $totales['monto_exento'],
            'monto_total' => $totales['monto_total'],
            'valor_pagar' => $totales['monto_total'],
        ]);
    }
NEW,
    $path);
$src = replaceExactlyOnce($src,
    <<<'OLD'
            $documento->update(array_merge(
                $this->cabecera($formulario),
                ['condicion_documento' => $estado]
            ));
OLD,
    <<<'NEW'
            // El envío a caja y el guardado de cotización incluyen cualquier
            // modificación aún no guardada del descuento global.
            $this->actualizarDescuentoEnDocumento($documento, $formulario);
            $this->actualizarTotales($documento);

            $documento->update(array_merge(
                $this->cabecera($formulario),
                ['condicion_documento' => $estado]
            ));
NEW,
    $path);
$files[$path] = $src;

// 3. Editor común a notas y cotizaciones: resúmenes derivados del documento.
$path = 'app/Livewire/SalesCounters/SalesDocumentEditor.php';
$src = $files[$path];
$src = replaceExactlyOnce($src,
    'use App\\Services\\Sales\\DocumentItemService;',
    'use App\\Services\\Sales\\DocumentItemService;' . "\n" . 'use App\\Services\\Sales\\GlobalDiscountService;',
    $path);
$src = replaceExactlyOnce($src,
    <<<'OLD'
    #[Computed]
    public function totales(): array
    {
        return $this->ventas()->totalizarDetalles($this->documento?->details ?? []);
    }
OLD,
    <<<'NEW'
    #[Computed]
    public function totales(): array
    {
        return $this->documento === null
            ? app(GlobalDiscountService::class)->calcular([])
            : app(GlobalDiscountService::class)->paraDocumento($this->documento);
    }
NEW,
    $path);
$src = replaceExactlyOnce($src,
    <<<'OLD'
    public function abrirProducto(int $id): void
OLD,
    <<<'NEW'
    /** Guarda explícitamente el descuento desde el resumen del mesón. */
    public function guardarDescuentoGlobal(): void
    {
        abort_unless($this->documentoId !== null, 409);

        $this->ventas()->establecerDescuentoGlobal(
            $this->documentoId,
            $this->tipoDocumento(),
            (int) auth()->id(),
            $this->form->validate()
        );

        unset($this->documento, $this->totales);
        $this->resetValidation('form.descuentoValor');
        $this->mensaje = 'Descuento global guardado.';
    }

    public function abrirProducto(int $id): void
NEW,
    $path);
$src = replaceExactlyOnce($src,
    <<<'OLD'
        $this->form->nombreReferencia = (string) ($documento->nombre_referencia ?? '');
        $this->mostrarRecuperar = false;
OLD,
    <<<'NEW'
        $this->form->nombreReferencia = (string) ($documento->nombre_referencia ?? '');
        $this->form->descuentoTipo = $documento->descuento_global_tipo ?? 'porcentaje';
        $this->form->descuentoValor = (string) ($documento->descuento_global_valor ?? 0);
        $this->mostrarRecuperar = false;
NEW,
    $path);
$files[$path] = $src;

// 4. Caja: toma por defecto el descuento persistido en mesón y permite editarlo.
$path = 'app/Livewire/Payments/Customer/CreatePayment.php';
$src = $files[$path];
$src = replaceExactlyOnce($src,
    <<<'OLD'
        $this->form->montoRecibido = (string) $document->details()
            ->vigente()
            ->sum('MontoItem');
OLD,
    <<<'NEW'
        // Un descuento del mesón no se pierde ni se aplica por segunda vez.
        $this->form->descuentoTipo = $document->descuento_global_tipo ?? 'porcentaje';
        $this->form->descuentoValor = (string) ($document->descuento_global_valor ?? 0);
        $this->form->montoRecibido = (string) app(GlobalDiscountService::class)
            ->paraDocumento($document)['monto_total'];
NEW,
    $path);
$files[$path] = $src;

// 5. Cotización PDF: mismos totales que mesón/caja.
$path = 'app/Services/Pdf/Classes/QuotationPdf.php';
$src = $files[$path];
$src = replaceExactlyOnce($src,
    'use App\\Services\\Sales\\DocumentItemService;',
    'use App\\Services\\Sales\\DocumentItemService;' . "\n" . 'use App\\Services\\Sales\\GlobalDiscountService;',
    $path);
$src = replaceExactlyOnce($src,
    "            'totals' => app(DocumentItemService::class)->totalizarDetalles(\$cotizacion->details),",
    "            'totals' => app(GlobalDiscountService::class)->paraDocumento(\$cotizacion),",
    $path);
$files[$path] = $src;

// 6. Cotización PDF (presentación): muestra la rebaja antes del desglose neto/IVA.
$path = 'resources/views/pdf/documents/quotation.blade.php';
$src = $files[$path];
$src = replaceExactlyOnce($src,
    "            <tr><td>Neto</td><td class=\"number\">{{ \$money(\$totals['monto_neto']) }}</td></tr>",
    <<<'NEW'
            <tr><td>Subtotal (tras descuentos por ítem)</td><td class="number">{{ $money($totals['subtotal']) }}</td></tr>
            @if ($totals['descuento_global'] > 0)
                <tr>
                    <td>Descuento global ({{ $document->descuento_global_tipo === 'porcentaje' ? $document->descuento_global_valor.'%' : 'monto fijo' }})</td>
                    <td class="number">-{{ $money($totals['descuento_global']) }}</td>
                </tr>
            @endif
            <tr><td>Neto</td><td class="number">{{ $money($totals['monto_neto']) }}</td></tr>
NEW,
    $path);
$files[$path] = $src;

// 7. Vista compartida del mesón: código completo en sustitución, no fragmentos.
$path = 'resources/views/livewire/sales-counters/partials/resumen.blade.php';
$files[$path] = <<<'BLADE'
@php($documento = $this->documento)
@php($totales = $this->totales)
<section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
    <div class="mb-3 flex items-center justify-between gap-2">
        <h2 class="font-semibold">Resumen</h2>
        <span class="text-xs text-gray-500">{{ $documento ? 'N.º '.$documento->id : 'Nuevo documento' }}</span>
    </div>

    @if ($documento)
        <p class="mb-3 text-xs text-gray-500">
            Emisión: {{ $documento->fecha ? \Carbon\Carbon::parse($documento->fecha)->format('d/m/Y') : '—' }}
        </p>
    @endif

    <div class="mb-4 rounded-lg border border-gray-200 p-3 dark:border-gray-600">
        <h3 class="mb-1 text-sm font-semibold">Descuento global</h3>
        <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
            Se aplica después de los descuentos individuales, sin modificar los ítems.
        </p>

        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <div>
                <label for="meson-descuento-tipo" class="mb-1 block text-xs font-medium">Tipo</label>
                <select id="meson-descuento-tipo"
                        wire:model.change="form.descuentoTipo"
                        @disabled(! $documento)
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
                    <option value="porcentaje">Porcentaje (%)</option>
                    <option value="monto">Monto fijo ($)</option>
                </select>
                @error('form.descuentoTipo')
                    <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="meson-descuento-valor" class="mb-1 block text-xs font-medium">
                    {{ $form->descuentoTipo === 'porcentaje' ? 'Porcentaje (%)' : 'Monto ($)' }}
                </label>
                <input id="meson-descuento-valor"
                       type="number" min="0" step="1"
                       @if ($form->descuentoTipo === 'porcentaje') max="99" @endif
                       wire:model.blur="form.descuentoValor"
                       @disabled(! $documento)
                       class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900">
                @error('form.descuentoValor')
                    <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="mt-3 flex justify-end">
            <button type="button"
                    wire:click="guardarDescuentoGlobal"
                    wire:loading.attr="disabled"
                    wire:target="guardarDescuentoGlobal"
                    @disabled(! $documento)
                    class="rounded-lg bg-blue-700 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">
                Aplicar descuento global
            </button>
        </div>
    </div>

    <dl class="space-y-2 text-sm">
        <div class="flex justify-between">
            <dt>Subtotal (con descuentos por ítem)</dt>
            <dd>${{ number_format((int) $totales['subtotal'], 0, ',', '.') }}</dd>
        </div>
        <div class="flex justify-between text-red-600 dark:text-red-400">
            <dt>Descuento global aplicado</dt>
            <dd>-${{ number_format((int) $totales['descuento_global'], 0, ',', '.') }}</dd>
        </div>
        <div class="flex justify-between"><dt>Neto</dt><dd>${{ number_format((int) $totales['monto_neto'], 0, ',', '.') }}</dd></div>
        <div class="flex justify-between"><dt>IVA</dt><dd>${{ number_format((int) $totales['monto_iva'], 0, ',', '.') }}</dd></div>
        <div class="flex justify-between"><dt>Exento</dt><dd>${{ number_format((int) $totales['monto_exento'], 0, ',', '.') }}</dd></div>
        <div class="flex justify-between border-t pt-3 text-lg font-bold dark:border-gray-700">
            <dt>TOTAL</dt>
            <dd>${{ number_format((int) $totales['monto_total'], 0, ',', '.') }}</dd>
        </div>
    </dl>
</section>
BLADE;

// 8. Migración nueva: document total columns were missing from the inspected migrations.
$extra = [
    'database/migrations/2026_11_20_230000_add_sales_totals_to_documents_table.php'
        => file_get_contents("{$scriptDir}/database/migrations/2026_11_20_230000_add_sales_totals_to_documents_table.php"),
    'tests/Feature/Sales/DescuentoGlobalMesonTest.php'
        => file_get_contents("{$scriptDir}/tests/Feature/Sales/DescuentoGlobalMesonTest.php"),
];
foreach ($extra as $path => $content) {
    if (! is_string($content)) {
        fail("Archivo del paquete ausente: {$path}");
    }
    if (file_exists("{$repo}/{$path}")) {
        fail("Ya existe {$path}; no se sobrescribe.");
    }
    $files[$path] = $content;
}

$preview = dirname($repo).'/saf-descuento-global-preview-'.getmypid();
if (! mkdir($preview, 0700) && ! is_dir($preview)) {
    fail("No se pudo crear el directorio de vista previa {$preview}");
}

foreach ($files as $path => $content) {
    $target = "{$preview}/{$path}";
    if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0700, true)) {
        fail("No se pudo crear directorio para {$target}");
    }
    if (file_put_contents($target, $content) === false) {
        fail("No se pudo generar {$target}");
    }
    if (str_ends_with($path, '.php') && ! str_ends_with($path, '.blade.php')) {
        exec('php -l '.escapeshellarg($target).' 2>&1', $lint, $code);
        if ($code !== 0) {
            fail("Error de sintaxis PHP en {$path}: ".implode(' ', $lint));
        }
        unset($lint);
    }
}

if ($apply) {
    // No tocamos nada hasta generar y verificar TODOS los archivos.
    $backup = dirname($repo).'/saf-descuento-global-backup-'.date('Ymd-His').'-'.getmypid();
    if (! mkdir($backup, 0700)) {
        fail("No se pudo crear respaldo {$backup}");
    }
    foreach ($originals as $path => $content) {
        $target = "{$backup}/{$path}";
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0700, true);
        }
        file_put_contents($target, $content);
    }
    foreach ($files as $path => $content) {
        $target = "{$repo}/{$path}";
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        if (file_put_contents($target, $content) === false) {
            fail("Error escribiendo {$target}; revisa respaldo {$backup}.");
        }
    }
    echo "Archivos completos aplicados en {$repo}\n";
    echo "Respaldo de originales: {$backup}\n";
    echo "Verifica: git diff --check && git diff --stat && php artisan migrate\n";
} else {
    echo "Archivos completos generados en: {$preview}\n";
    echo "No se modificó SAF. Revisa archivos y, si estás conforme, ejecuta con --apply.\n";
}

foreach ($files as $path => $content) {
    echo sprintf("  %-87s %5d líneas\n", $path, substr_count($content, "\n") + 1);
}
