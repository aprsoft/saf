<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Permission\Models\Permission;

function qzTestUser(): User
{
    $user = User::factory()->create();
    Permission::findOrCreate('documents.view', 'web');
    $user->givePermissionTo('documents.view');

    return $user;
}

function qzTestDocument(User $user, string $state): Document
{
    DB::table('typedocuments')->insertOrIgnore(['id' => 17, 'name' => 'BOLETA', 'transacciontype' => 'INGRESO', 'codigo' => 39]);
    $document = Document::query()->create([
        'user_id' => $user->id, 'typedocument_id' => 17, 'fecha' => today()->toDateString(),
        'estado_documento' => 'VIGENTE', 'condicion_documento' => 'PAGADO',
        'folio' => 123, 'ted' => '<TED><DD><CAF>test</CAF></DD><FRMT>test</FRMT></TED>',
    ]);
    DB::table('dte_emission_attempts')->insert([
        'document_id' => $document->id, 'idempotency_key' => 'test-'.$document->id,
        'estado' => $state, 'folio' => 123, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $document;
}

test('impresión exige autenticación', function (): void {
    $this->postJson(route('printing.qz.prepare'), ['printer' => 'Epson'])->assertUnauthorized();
});

test('impresión rechaza DTE sin aceptación confirmada', function (string $state): void {
    $user = qzTestUser();
    $document = qzTestDocument($user, $state);
    $this->actingAs($user)->postJson(route('printing.qz.prepare'), [
        'printer' => 'Epson', 'document_id' => $document->id,
    ])->assertConflict();
})->with(['ENVIANDO', 'RECHAZADA', 'INCIERTA']);

test('impresión aplica la autorización del documento', function (): void {
    $user = User::factory()->create();
    Permission::findOrCreate('documents.view', 'web');
    $document = qzTestDocument($user, 'CONFIRMADA');
    $this->actingAs($user)->postJson(route('printing.qz.prepare'), [
        'printer' => 'Epson', 'document_id' => $document->id,
    ])->assertForbidden();
});

test('impresión rechaza destinos que no son nombres de impresora', function (): void {
    $this->actingAs(User::factory()->create())->postJson(route('printing.qz.prepare'), [
        'printer' => '/tmp/archivo',
    ])->assertUnprocessable()->assertJsonValidationErrors('printer');
});

test('firma rechaza comandos arbitrarios y mensajes vencidos', function (): void {
    $this->actingAs(User::factory()->create());
    $this->postJson(route('printing.qz.sign'), ['message' => json_encode([
        'call' => 'file.write', 'params' => [], 'timestamp' => now()->getTimestampMs(),
    ])])->assertForbidden();
    $this->postJson(route('printing.qz.sign'), ['message' => json_encode([
        'call' => 'printers.find', 'params' => [], 'timestamp' => now()->subMinutes(2)->getTimestampMs(),
    ])])->assertUnprocessable();
});

test('firma solo autoriza el contenido preparado para el usuario y una vez', function (): void {
    $user = User::factory()->create();
    $key = openssl_pkey_new(['private_key_bits' => 2048]);
    openssl_pkey_export($key, $pem);
    $path = tempnam(sys_get_temp_dir(), 'qz-test-');
    file_put_contents($path, $pem);
    config(['printing.private_key' => $path]);

    try {
        $job = $this->actingAs($user)->postJson(route('printing.qz.prepare'), ['printer' => 'Epson'])
            ->assertOk()->json();
        $message = json_encode(['call' => 'print', 'params' => $job['params'], 'timestamp' => now()->getTimestampMs()]);
        $payload = ['message' => $message, 'ticket' => $job['ticket']];

        $this->actingAs(User::factory()->create())->postJson(route('printing.qz.sign'), $payload)->assertForbidden();
        $altered = $job['params'];
        $altered['printer'] = ['file' => '/tmp/attack'];
        $this->actingAs($user)->postJson(route('printing.qz.sign'), [
            'ticket' => $job['ticket'], 'message' => json_encode(['call' => 'print', 'params' => $altered, 'timestamp' => now()->getTimestampMs()]),
        ])->assertForbidden();
        $response = $this->postJson(route('printing.qz.sign'), $payload)->assertOk();
        expect(openssl_verify(hash('sha256', $message), base64_decode($response->getContent()), openssl_pkey_get_details($key)['key'], OPENSSL_ALGO_SHA512))->toBe(1);
        $this->postJson(route('printing.qz.sign'), $payload)->assertConflict();
    } finally {
        unlink($path);
    }
});

test('configuración global permite desactivar impresión sin cambiar empresas', function (): void {
    config(['printing.enabled' => false]);
    $this->actingAs(User::factory()->create())->postJson(route('printing.qz.prepare'), ['printer' => 'Epson'])->assertConflict();
});

test('prepara el PDF existente solo para un DTE confirmado y autorizado', function (): void {
    $user = qzTestUser();
    $document = qzTestDocument($user, 'CONFIRMADA');
    Pdf::fake();
    $response = $this->actingAs($user)->postJson(route('printing.qz.prepare'), [
        'printer' => 'Epson', 'document_id' => $document->id,
    ])->assertOk()->assertJsonPath('params.data.0.format', 'pdf');
    expect(base64_decode($response->json('params.data.0.data')))->toStartWith('%PDF');
    Pdf::assertViewIs('pdf.documents.dte');
});
