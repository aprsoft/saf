<?php

namespace App\Http\Controllers;

use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Gate;

class RoleController
{

    
    public function index()
    {
        Gate::authorize('viewAny', Role::class);

        return view('roles.index', [
            'title' => 'Roles',
        ]);
    }
 
    public function create()
    {
        Gate::authorize('create', Role::class);

        return view('roles.create', [
            'title' => 'Crear Rol',
        ]);
    }
  
    public function show(string $id)
    {
        $role = Role::query()
            ->where('guard_name', 'web')
            ->with([
                'permissions' => fn ($query) => $query
                    ->where('guard_name', 'web')
                    ->orderBy('name'),
            ])
            ->findOrFail($id);

        Gate::authorize('view', $role);

        return view('roles.show', [
            'title' => 'Ver Rol',
            'role' => $role,
        ]);
    }
 
    public function edit(string $id)
    {
        $role = Role::query()
            ->where('guard_name', 'web')
            ->findOrFail($id);

        Gate::authorize('update', $role);

        return view('roles.edit', [
            'title' => 'Editar Rol',
            'role' => $role,
        ]);
    }  
}