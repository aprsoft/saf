<?php

use App\Helpers\MenuHelper;
use App\Livewire\Companies\EditCompany;
use App\Models\Company;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function configurationUser(): User
{
    $role = Role::findOrCreate('Soporte', 'web');
    $role->givePermissionTo(Permission::findOrCreate('configuration.edit', 'web'));
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function configurationCompany(): Company
{
    return Company::query()->create([
        'rut_emisor' => '76795561-8',
        'razon_social' => 'Empresa de prueba',
        'giro_emisor' => 'Comercio',
        'direccion_origen' => 'Calle Uno',
        'acteco' => '479100',
        'apikey' => 'clave-ficticia',
        'url' => 'https://example.test/dte',
        'url_document_received' => 'https://example.test/received',
        'url_document' => 'https://example.test/document',
        'codigo_sii_sucursal' => '1',
        'comuna_origen' => 'Santiago',
        'modo_cobro' => 'CAJA_CENTRAL',
    ]);
}

it('requires authentication and permission to open configuration', function () {
    $this->get(route('configuration.edit'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())
        ->get(route('configuration.edit'))->assertForbidden();
    Livewire::test(EditCompany::class)->assertForbidden();
    expect(collect(MenuHelper::getMenuGroups())->pluck('items')->flatten(1)->pluck('name'))
        ->not->toContain('Configuración');
});

it('shows the configuration page and support menu to authorized users', function () {
    $this->actingAs(configurationUser())->get(route('configuration.edit'))
        ->assertOk()->assertSee('Configuración de empresa')
        ->assertSee('No hay empresas registradas');
    expect(collect(MenuHelper::getMenuGroups())->firstWhere('title', 'Soporte')['items'])
        ->toContainEqual(collect(MenuHelper::getSupportItems())->firstWhere('name', 'Configuración'));
});

it('updates only the selected company and preserves its hidden API key', function () {
    $other = configurationCompany();
    $company = configurationCompany();
    Livewire::actingAs(configurationUser())->test(EditCompany::class)
        ->assertSet('companyId', null)
        ->call('selectCompany', $company->id)
        ->assertSet('apikey', '')
        ->assertDontSee('clave-ficticia')
        ->set('fields.razon_social', 'Empresa actualizada')
        ->set('fields.modo_cobro', 'CAJA_INTEGRADA')
        ->set('fields.id', $other->id)
        ->call('save')->assertHasNoErrors()
        ->assertSee('Configuración guardada correctamente.');
    expect($company->refresh()->razon_social)->toBe('Empresa actualizada');
    expect($company->modo_cobro)->toBe('CAJA_INTEGRADA');
    expect($company->apikey)->toBe('clave-ficticia');
    expect($other->refresh()->razon_social)->toBe('Empresa de prueba');
});

it('replaces the API key and clears it from component state', function () {
    $company = configurationCompany();
    Livewire::actingAs(configurationUser())->test(EditCompany::class)
        ->call('selectCompany', $company->id)
        ->set('apikey', 'nueva-clave-ficticia')
        ->call('save')->assertHasNoErrors()->assertSet('apikey', '');
    expect($company->refresh()->apikey)->toBe('nueva-clave-ficticia');
});

it('saves the checkout mode without the removed point of sale field', function (string $initialMode, string $selectedMode) {
    $company = configurationCompany();
    $company->update(['modo_cobro' => $initialMode]);

    Livewire::actingAs(configurationUser())->test(EditCompany::class)
        ->call('selectCompany', $company->id)
        ->set('fields.modo_cobro', $selectedMode)
        ->call('save')->assertHasNoErrors()
        ->call('selectCompany', $company->id)
        ->assertSet('fields.modo_cobro', $selectedMode);

    expect($company->refresh()->modo_cobro)->toBe($selectedMode);
})->with([
    'integrated checkout' => ['CAJA_CENTRAL', 'CAJA_INTEGRADA'],
    'central checkout' => ['CAJA_INTEGRADA', 'CAJA_CENTRAL'],
]);

it('rejects invalid company settings without saving', function (string $field, mixed $value) {
    $company = configurationCompany();
    $original = $company->refresh()->getAttributes();
    Livewire::actingAs(configurationUser())->test(EditCompany::class)
        ->call('selectCompany', $company->id)
        ->set('fields.'.$field, $value)
        ->call('save')->assertHasErrors('fields.'.$field);
    expect($company->refresh()->getAttributes())->toBe($original);
})->with([
    'required name' => ['razon_social', ''],
    'long rut' => ['rut_emisor', '12345678901'],
    'invalid service URL' => ['url', 'javascript:alert(1)'],
    'invalid received URL' => ['url_document_received', 'invalid'],
    'invalid document URL' => ['url_document', 'invalid'],
    'invalid checkout mode' => ['modo_cobro', 'OTRO'],
]);

it('rechecks permission before saving', function () {
    $company = configurationCompany();
    $user = configurationUser();
    $component = Livewire::actingAs($user)->test(EditCompany::class)
        ->call('selectCompany', $company->id)
        ->set('fields.razon_social', 'No autorizado');
    $user->syncRoles([]);
    $component->call('save')->assertForbidden();
    expect($company->refresh()->razon_social)->toBe('Empresa de prueba');
});
