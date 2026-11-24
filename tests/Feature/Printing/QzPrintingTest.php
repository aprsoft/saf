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
    DB::table('typedocuments')->insertOrIgnore([
        'id' => 17, 'name' => 'BOLETA', 'transacciontype' => 'INGRESO', 'codigo' => 39,
    ]);

    $document = Document::query()->create([
        'user_id' => $user->id,
        'typedocument_id' => 17,
        'fecha' => today()->toDateString(),
        'estado_documento' => 'VIGENTE',
        'condicion_documento' => 'PAGADO',
        'folio' => 123,
        'ted' => '<TED><DD><CAF>test</CAF></DD><FRMT>test</FRMT></TED>',
    ]);

    DB::table('dte_emission_attempts')->insert([
        'document_id' => $document->id,
        'idempotency_key' => 'test-'.$document->id,
        'estado' => $state,
        'folio' => 123,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $document;
}

function qzRequest(int $recordId, string $template = 'document.dte', string $format = 'standard'): array
{
    return ['document_id' => $recordId, 'template' => $template, 'format' => $format];
}

test('impresión requiere autenticación', function (): void {
    $this->postJson(route('printing.qz.prepare'), qzRequest(1))->assertUnauthorized();
});

test('impresión exige parámetros completos y formato configurado', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('printing.qz.prepare'), ['document_id' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors(['template', 'format']);

    $this->postJson(route('printing.qz.prepare'), qzRequest(1, 'document.dte', 'desconocido'))
        ->assertUnprocessable()->assertJsonValidationErrors('format');
});

test('impresión rechaza DTE sin emisión confirmada', function (string $state): void {
    $user = qzTestUser();
    $document = qzTestDocument($user, $state);

    $this->actingAs($user)
        ->postJson(route('printing.qz.prepare'), qzRequest($document->id))
        ->assertConflict();
})->with(['ENVIANDO', 'RECHAZADA', 'INCIERTA']);

test('impresión aplica permisos de la clase PDF', function (): void {
    $user = User::factory()->create();
    $document = qzTestDocument($user, 'CONFIRMADA');

    $this->actingAs($user)
        ->postJson(route('printing.qz.prepare'), qzRequest($document->id))
        ->assertForbidden();
});

test('plantillas no registradas no pueden imprimirse', function (): void {
    $user = qzTestUser();

    $this->actingAs($user)
        ->postJson(route('printing.qz.prepare'), qzRequest(1, 'pdf.arbitrario'))
        ->assertNotFound();
});

test('PDF de DTE confirmado se prepara para QZ', function (): void {
    $user = qzTestUser();
    $document = qzTestDocument($user, 'CONFIRMADA');
    Pdf::fake();

    $this->actingAs($user)
        ->postJson(route('printing.qz.prepare'), qzRequest($document->id))
        ->assertOk()
        ->assertJsonPath('params.printer.name', 'printerStandard')
        ->assertJsonPath('params.data.0.format', 'pdf');

    Pdf::assertViewIs('pdf.documents.dte');
});

test('impresión universal prepara PDF de usuario sin exigir DTE', function (): void {
    $actor = User::factory()->create();
    $target = User::factory()->create();

    Permission::findOrCreate('users.index', 'web');
    $actor->givePermissionTo('users.index');
    Pdf::fake();

    $response = $this->actingAs($actor)
        ->postJson(route('printing.qz.prepare'), qzRequest($target->id, 'user.profile'))
        ->assertOk()
        ->assertJsonPath('params.printer.name', 'printerStandard');

    expect($response->json('ticket'))->toBeString();
    Pdf::assertViewIs('pdf.users.profile');
});

test('firma rechaza comandos arbitrarios y mensajes vencidos', function (): void {
    $this->actingAs(User::factory()->create());

    $this->postJson(route('printing.qz.sign'), [
        'message' => json_encode([
            'call' => 'file.write', 'params' => [], 'timestamp' => now()->getTimestampMs(),
        ]),
    ])->assertForbidden();

    $this->postJson(route('printing.qz.sign'), [
        'message' => json_encode([
            'call' => 'printers.find', 'params' => [], 'timestamp' => now()->subMinutes(2)->getTimestampMs(),
        ]),
    ])->assertUnprocessable();
});

test('firma solo autoriza el trabajo exacto para su usuario y una vez', function (): void {
    $user = qzTestUser();
    $document = qzTestDocument($user, 'CONFIRMADA');
    Pdf::fake();

    $key = openssl_pkey_new(['private_key_bits' => 2048]);
    openssl_pkey_export($key, $pem);
    $path = tempnam(sys_get_temp_dir(), 'qz-test-');
    file_put_contents($path, $pem);
    config(['printing.private_key' => $path]);

    try {
        $job = $this->actingAs($user)
            ->postJson(route('printing.qz.prepare'), qzRequest($document->id))
            ->assertOk()
            ->json();

        $message = json_encode([
            'call' => 'print',
            'params' => $job['params'],
            'timestamp' => now()->getTimestampMs(),
        ]);
        $payload = ['message' => $message, 'ticket' => $job['ticket']];

        $this->actingAs(User::factory()->create())
            ->postJson(route('printing.qz.sign'), $payload)
            ->assertForbidden();

        $changed = $job['params'];
        $changed['printer'] = ['file' => '/tmp/attack'];
        $this->actingAs($user)
            ->postJson(route('printing.qz.sign'), [
                'ticket' => $job['ticket'],
                'message' => json_encode([
                    'call' => 'print',
                    'params' => $changed,
                    'timestamp' => now()->getTimestampMs(),
                ]),
            ])->assertForbidden();

        $signed = $this->postJson(route('printing.qz.sign'), $payload)->assertOk();
        expect(openssl_verify(
            hash('sha256', $message),
            base64_decode($signed->getContent()),
            openssl_pkey_get_details($key)['key'],
            OPENSSL_ALGO_SHA512
        ))->toBe(1);

        $this->postJson(route('printing.qz.sign'), $payload)->assertConflict();
    } finally {
        unlink($path);
    }
});

test('configuración permite desactivar la impresión', function (): void {
    config(['printing.enabled' => false]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('printing.qz.prepare'), qzRequest(1))
        ->assertConflict();
});
