<?php

namespace App\Helpers;

use App\Helpers\Function\IconHelper;

class MenuHelper
{
    public static function getMainNavItems(): array
    {        
        return [
            [
                'icon' => 'home',
                'name' => 'Inicio',
                'path' => route('dashboard', absolute: false),
            ],
         
        ];
    
    }


    public static function getManagementItems(): array
    {
        return [
            [
                'icon' => 'users',
                'name' => 'Clientes',
                'subItems' => [
                    [
                        'name' => 'Listado',
                        'path' => route('customers.index', absolute: false),
                        'permission' => 'customers.index',
                        'pro' => false
                    ],
                    [
                        'name' => 'Crear Cliente',
                        'path' => route('customers.create', absolute: false),
                        'permission' => 'customers.create',
                        'pro' => false
                    ],
                ],
            ],
            [
                'icon' => 'users',
                'name' => 'Proveedores',
                'subItems' => [
                    [
                        'name' => 'Listado',
                        'path' => route('suppliers.index', absolute: false),
                        'permission' => 'suppliers.index',
                        'pro' => false
                    ],
                    [
                        'name' => 'Crear Cliente',
                        'path' => route('suppliers.create', absolute: false),
                        'permission' => 'suppliers.create',
                        'pro' => false
                    ],
                ],
            ],  
           
            [
                'icon' => 'file',
                'name' => 'Ventas',
                'subItems' => [
                    [
                        'name' => 'Listado',
                        'path' => route('dtes.customers.index', absolute: false),
                        'permission' => 'dtes.customers.index',
                        'pro' => false
                    ],
                    [
                        'name' => 'Meson de Ventas',
                        'path' => route('sales-counters.show', absolute: false),
                        'permission' => 'sales-counters.show',
                        'pro' => false
                    ],
                ],
            ],   
            [
                'icon' => 'file',
                'name' => 'Caja',
                'subItems' => [
                    [
                        'name' => 'Listado',
                        'path' => route('cashs.customer.index', absolute: false),
                        'permission' => 'cashs.customer.index',
                        'pro' => false
                    ], 
                    [
                        'name' => 'Pagos',
                        'path' => route('payments.customers.index', absolute: false),
                        'permission' => 'payments.customers.index',
                        'pro' => false
                    ],   
                    [
                        'name' => 'Reporte de Caja',
                        'path' => route('cashReports.show', absolute: false),
                        'permission' => 'cashReports.show',
                        'pro' => false
                    ],                    
                ],
            ], 
            [
                'icon' => 'file',
                'name' => 'Despacho',
                'subItems' => [
                    [
                        'name' => 'Listado',
                        'path' => route('unpaids.index', absolute: false),
                        'permission' => 'unpaids.index',
                        'pro' => false
                    ],  
                                      
                ],
            ], 
            [
                'icon' => 'file',
                'name' => 'Bodega',
                'subItems' => [
                    [
                        'name' => 'Listado',
                        'path' => route('unpaids.index', absolute: false),
                        'permission' => 'unpaids.index',
                        'pro' => false
                    ],  
                                      
                ],
            ],         
           
        ];
    }
    

    public static function getAdministrationItems(): array
    {
        return [
            [
                'icon' => 'users',
                'name' => 'Usuarios',
                'subItems' => [
                    [
                        'name' => 'Listado',
                        'path' => route('users.index', absolute: false),
                        'permission' => 'users.index',
                        'pro' => false
                    ],
                    [
                        'name' => 'Crear Usuario',
                        'path' => route('users.create', absolute: false),
                        'permission' => 'users.create',
                        'pro' => false
                    ],
                ],
            ],        
           
        ];
    }

    public static function getRolesPermissionsItems(): array
    {
        return [
         
            [
                'icon' => 'access',
                'name' => 'Roles',
                'subItems' => [
                    [                        
                        'name' => 'listado',
                        'path' => route('roles.index', absolute: false),
                        'permission' => 'roles.index',
                        'pro'  => false
                    ],
                    [                        
                        'name' => 'Crear Roles',
                        'path' => route('roles.create', absolute: false),
                        'permission' => 'roles.create',
                        'pro'  => false
                    ],
                    
                ],
            ],
            [
                'icon' => 'access',
                'name' => 'Permisos',
                'subItems' => [
                    [                        
                        'name' => 'Listado',
                        'path' => route('permissions.index', absolute: false),
                        'permission' => 'permissions.index',
                        'pro'  => false
                    ],
                    [                        
                        'name' => 'Crear Permiso',
                        'path' => route('permissions.create', absolute: false),
                        'permission' => 'permissions.create',
                        'pro'  => false
                    ],
                    
                ],
            ]
        ];
    }    

    public static function getSupportItems(): array
    {
        return [            
            [
                'icon' => 'support',
                'name' => 'Errores',
                'path' => route('errors.index', absolute: false),
                'permission' => 'errors.index',
                'pro' => false
            ],
        ];
    }


    public static function getMenuGroups(): array
    {
        $user = auth('web')->user();

        if (!$user) {
            return [];
        }

        $groups = [
            [
                'title' => 'Menu',
                'items' => self::getMainNavItems(),
            ],         
            [
                'title' => 'Gestion',
                'items' => self::getManagementItems(),
            ],
            [
                'title' => 'Administration',
                'items' => self::getAdministrationItems(),
            ],
            [
                'title' => 'Roles y Permisos',
                'items' => self::getRolesPermissionsItems(),
            ],
            [
                'title' => 'Soporte',
                'items' => self::getSupportItems(),
            ],
        ];

        foreach ($groups as &$group) {
            $items = [];

            foreach ($group['items'] as $item) {

                // Verificar permiso del elemento principal
                if (
                    isset($item['permission']) &&
                    !$user->can($item['permission'])
                ) {
                    continue;
                }

                // Filtrar elementos del submenú
                if (isset($item['subItems'])) {

                    $item['subItems'] = array_values(
                        array_filter(
                            $item['subItems'],
                            fn (array $subItem) =>
                                !isset($subItem['permission']) ||
                                $user->can($subItem['permission'])
                        )
                    );

                    // Ocultar menú padre sin opciones visibles
                    if (empty($item['subItems'])) {
                        continue;
                    }
                }

                $items[] = $item;
            }

            $group['items'] = $items;
        }

        unset($group);

        // Ocultar grupos que quedaron sin elementos
        return array_values(
            array_filter(
                $groups,
                fn (array $group) => !empty($group['items'])
            )
        );
    }

    public static function isActive($path): bool
    {
        return request()->is(ltrim($path, '/'));
    }
    
    public static function getIconSvg($iconName): string
    {
        return IconHelper::get($iconName);
    }
}
