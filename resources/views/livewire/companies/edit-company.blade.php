<div class="space-y-4">
    @if (session('success'))
        <div role="status" class="alert alert-success">{{ session('success') }}</div>
    @endif

    <x-card title="Configuración de empresa" shadow>
        <p class="mb-4 text-sm text-base-content/60">Selecciona la empresa que deseas configurar.</p>
        <label for="configuration-company" class="label">Empresa</label>
        <select id="configuration-company" class="select w-full" wire:change="selectCompany($event.target.value)"
                wire:loading.attr="disabled">
            <option value="" disabled @selected($companyId === null)>Selecciona una empresa</option>
            @foreach ($companies as $company)
                <option value="{{ $company->id }}" wire:key="company-{{ $company->id }}" @selected($companyId === $company->id)>
                    {{ $company->razon_social }} — {{ $company->rut_emisor }}
                </option>
            @endforeach
        </select>
        @if ($companies->isEmpty())
            <p class="mt-3 text-sm text-base-content/60">No hay empresas registradas para configurar.</p>
        @endif
    </x-card>

    @if ($companyId !== null)
        <x-form wire:submit="save" wire:key="company-form-{{ $companyId }}">
            <x-card title="Datos de la empresa" shadow>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-input label="RUT emisor" wire:model="fields.rut_emisor" maxlength="10" />
                    <x-input label="Razón social" wire:model="fields.razon_social" />
                    <x-input label="Giro emisor" wire:model="fields.giro_emisor" />
                    <x-input label="Actividad económica (Acteco)" wire:model="fields.acteco" />
                    <x-input label="Dirección de origen" wire:model="fields.direccion_origen" />
                    <x-input label="Comuna de origen" wire:model="fields.comuna_origen" />
                    <x-input label="Código SII sucursal" wire:model="fields.codigo_sii_sucursal" />
                    <x-input label="Contacto" wire:model="fields.contacto" />
                </div>
            </x-card>

            <x-card title="Operación comercial" shadow>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-select label="Modo de cobro" wire:model="fields.modo_cobro"
                              :options="[['id' => 'CAJA_CENTRAL', 'name' => 'Mesón y caja separados'], ['id' => 'CAJA_INTEGRADA', 'name' => 'Caja integrada']]" />
                </div>
            </x-card>

            <x-card title="Conexión DTE" shadow>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-input label="URL del servicio" type="url" wire:model="fields.url" />
                    <x-input label="URL de documentos recibidos" type="url" wire:model="fields.url_document_received" />
                    <x-input label="URL de documentos" type="url" wire:model="fields.url_document" />
                    <x-input label="Nueva API key" type="password" wire:model="apikey" autocomplete="new-password"
                             hint="Déjala vacía para conservar la clave actual." />
                </div>
            </x-card>

            <x-slot:actions>
                <x-button label="Guardar configuración" type="submit" class="btn-primary" spinner="save" wire:loading.attr="disabled" />
            </x-slot:actions>
        </x-form>
    @endif
</div>
