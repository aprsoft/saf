<?php

namespace App\Livewire\Companies;

use App\Models\Company;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class EditCompany extends Component
{
    #[Locked]
    public ?int $companyId = null;

    /** @var array<string, string|null> */
    public array $fields = [];

    public string $apikey = '';

    public function mount(): void
    {
        Gate::authorize('configuration.edit');
    }

    public function selectCompany(int $companyId): void
    {
        Gate::authorize('configuration.edit');

        $company = Company::query()->findOrFail($companyId);
        $this->companyId = $company->id;
        $this->fields = $company->only(array_keys($this->fieldRules()));
        $this->apikey = '';
        $this->resetValidation();
    }

    /** @return array<string, array<int, mixed>> */
    private function fieldRules(): array
    {
        return [
            'rut_emisor' => ['required', 'string', 'max:10'],
            'razon_social' => ['required', 'string', 'max:255'],
            'giro_emisor' => ['required', 'string', 'max:255'],
            'direccion_origen' => ['required', 'string', 'max:255'],
            'acteco' => ['required', 'string', 'max:255'],
            'codigo_sii_sucursal' => ['required', 'string', 'max:255'],
            'comuna_origen' => ['required', 'string', 'max:255'],
            'contacto' => ['nullable', 'string', 'max:255'],
            'url' => ['required', 'url:http,https', 'max:255'],
            'url_document_received' => ['required', 'url:http,https', 'max:255'],
            'url_document' => ['required', 'url:http,https', 'max:255'],
            'modo_cobro' => ['required', Rule::in(['CAJA_CENTRAL', 'CAJA_INTEGRADA'])],
        ];
    }

    public function save(): void
    {
        Gate::authorize('configuration.edit');
        $company = Company::query()->findOrFail($this->companyId);

        $rules = ['apikey' => ['nullable', 'string', 'max:255']];
        foreach ($this->fieldRules() as $field => $fieldRules) {
            $rules['fields.'.$field] = $fieldRules;
        }

        $validated = $this->validate($rules);
        $attributes = array_intersect_key($validated['fields'], $this->fieldRules());
        if ($this->apikey !== '') {
            $attributes['apikey'] = $validated['apikey'];
        }

        $company->update($attributes);
        $this->apikey = '';
        session()->flash('success', 'Configuración guardada correctamente.');
    }

    public function render(): View
    {
        Gate::authorize('configuration.edit');

        return view('livewire.companies.edit-company', [
            'companies' => Company::query()->orderBy('razon_social')->get(['id', 'razon_social', 'rut_emisor']),
        ]);
    }
}
