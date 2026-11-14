<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\{Category, Unit, ProductService, ProductUnit, PriceList, ProductPrice, Warehouse};

class FerreteriaSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $categories = [];
            foreach (self::categories() as [$code, $name, $parent]) {
                $categories[$code] = Category::updateOrCreate(
                    ['codigo' => $code],
                    ['nombre' => $name, 'parent_id' => $parent ? $categories[$parent]->id : null, 'activo' => true]
                );
            }

            $units = [];
            foreach ([
                ['UN', 'Unidad', false], ['KG', 'Kilogramo', true],
                ['G', 'Gramo', true], ['M', 'Metro', true],
                ['L', 'Litro', true], ['M3', 'Metro cúbico', true],
                ['CAJA', 'Caja', false], ['SACO', 'Saco', false],
                ['ROLLO', 'Rollo', false], ['H', 'Hora', true],
            ] as [$symbol, $name, $decimal]) {
                $units[$symbol] = Unit::updateOrCreate(
                    ['simbolo' => $symbol],
                    ['nombre' => $name, 'permite_decimales' => $decimal]
                );
            }

            $public = PriceList::updateOrCreate(['nombre' => 'Venta público'], ['tipo' => 'VENTA', 'incluye_iva' => false]);
            $wholesale = PriceList::updateOrCreate(['nombre' => 'Venta mayorista'], ['tipo' => 'VENTA', 'incluye_iva' => false]);
            PriceList::updateOrCreate(['nombre' => 'Compra proveedores'], ['tipo' => 'COMPRA', 'incluye_iva' => false]);
            Warehouse::updateOrCreate(['codigo' => 'PRINCIPAL'], ['nombre' => 'Bodega principal']);

            foreach (self::items() as [$code, $name, $type, $category, $base, $price, $alternates]) {
                $item = ProductService::withTrashed()->updateOrCreate(['codigo' => $code], [
                    'nombre' => $name, 'tipo' => $type,
                    'category_id' => $categories[$category]->id,
                    'stock_unit_id' => $units[$base]->id,
                    'purchase_unit_id' => $units[$base]->id,
                    'sales_unit_id' => $units[$base]->id,
                    'controla_stock' => $type === 'PRODUCTO',
                    'afecto_iva' => true, 'activo' => true, 'deleted_at' => null,
                ]);
                $prices = array_merge([[$base, 1, $price]], $alternates);
                foreach ($prices as [$symbol, $factor, $netPrice]) {
                    ProductUnit::updateOrCreate(
                        ['product_service_id' => $item->id, 'unit_id' => $units[$symbol]->id],
                        ['factor_conversion' => $factor]
                    );
                    foreach ([[$public, 1], [$wholesale, 0.9]] as [$list, $multiplier]) {
                        ProductPrice::updateOrCreate(
                            ['product_service_id' => $item->id, 'unit_id' => $units[$symbol]->id, 'price_list_id' => $list->id],
                            ['precio' => round($netPrice * $multiplier)]
                        );
                    }
                }
            }
        });
    }

    private static function categories(): array
    {
        return [
            ['ARIDOS', 'Áridos', null],
            ['CEMENTOS', 'Cementos y morteros', null],
            ['ELECTRICIDAD', 'Electricidad', null],
            ['FIJACIONES', 'Fijaciones', null],
            ['PINTURAS', 'Pinturas y accesorios', null],
            ['GASFITERIA', 'Gasfitería', null],
            ['HERRAMIENTAS', 'Herramientas manuales', null],
            ['MADERAS', 'Maderas y tableros', null],
            ['JARDIN', 'Jardín y riego', null],
            ['SEGURIDAD', 'Seguridad y protección', null],
            ['SERVICIOS', 'Servicios', null],
        ];
    }

    private static function items(): array
    {
        // Datos ficticios para desarrollo: NO son precios de mercado.
        // [codigo, nombre, tipo, categoria, unidad_base, precio_neto, unidades_alternativas]
        return [
            // Áridos
            ['FER-01-001', 'Arena fina', 'PRODUCTO', 'ARIDOS', 'KG', 675, [['SACO', 25, 15525]]],
            ['FER-01-002', 'Arena gruesa', 'PRODUCTO', 'ARIDOS', 'KG', 850, [['SACO', 25, 19550]]],
            ['FER-01-003', 'Gravilla 3/8', 'PRODUCTO', 'ARIDOS', 'KG', 1025, [['SACO', 25, 23575]]],
            ['FER-01-004', 'Grava 3/4', 'PRODUCTO', 'ARIDOS', 'KG', 1200, [['SACO', 25, 27600]]],
            ['FER-01-005', 'Ripio seleccionado', 'PRODUCTO', 'ARIDOS', 'KG', 1375, [['SACO', 25, 31625]]],
            ['FER-01-006', 'Bolón de río', 'PRODUCTO', 'ARIDOS', 'KG', 1550, [['SACO', 25, 35650]]],
            ['FER-01-007', 'Maicillo', 'PRODUCTO', 'ARIDOS', 'KG', 1725, [['SACO', 25, 39675]]],
            ['FER-01-008', 'Base estabilizada', 'PRODUCTO', 'ARIDOS', 'KG', 1900, [['SACO', 25, 43700]]],
            ['FER-01-009', 'Polvo de roca', 'PRODUCTO', 'ARIDOS', 'KG', 2075, [['SACO', 25, 47725]]],
            ['FER-01-010', 'Tierra vegetal', 'PRODUCTO', 'ARIDOS', 'KG', 2250, [['SACO', 25, 51750]]],
            // Cementos y morteros
            ['FER-02-001', 'Cemento gris 25 kg', 'PRODUCTO', 'CEMENTOS', 'UN', 925, []],
            ['FER-02-002', 'Cemento alta resistencia 25 kg', 'PRODUCTO', 'CEMENTOS', 'UN', 1100, []],
            ['FER-02-003', 'Mortero pega 25 kg', 'PRODUCTO', 'CEMENTOS', 'UN', 1275, []],
            ['FER-02-004', 'Mortero estuco 25 kg', 'PRODUCTO', 'CEMENTOS', 'UN', 1450, []],
            ['FER-02-005', 'Hormigón preparado 25 kg', 'PRODUCTO', 'CEMENTOS', 'UN', 1625, []],
            ['FER-02-006', 'Yeso construcción 20 kg', 'PRODUCTO', 'CEMENTOS', 'UN', 1800, []],
            ['FER-02-007', 'Cal hidratada 20 kg', 'PRODUCTO', 'CEMENTOS', 'UN', 1975, []],
            ['FER-02-008', 'Adhesivo cerámico 25 kg', 'PRODUCTO', 'CEMENTOS', 'UN', 2150, []],
            ['FER-02-009', 'Fragüe gris 1 kg', 'PRODUCTO', 'CEMENTOS', 'UN', 2325, []],
            ['FER-02-010', 'Impermeabilizante cementicio 20 kg', 'PRODUCTO', 'CEMENTOS', 'UN', 2500, []],
            // Electricidad
            ['FER-03-001', 'Cable THHN 1,5 mm² rojo', 'PRODUCTO', 'ELECTRICIDAD', 'M', 1175, [['ROLLO', 100, 103400]]],
            ['FER-03-002', 'Cable THHN 2,5 mm² azul', 'PRODUCTO', 'ELECTRICIDAD', 'M', 1350, [['ROLLO', 100, 118800]]],
            ['FER-03-003', 'Cable THHN 4 mm² negro', 'PRODUCTO', 'ELECTRICIDAD', 'M', 1525, [['ROLLO', 100, 134200]]],
            ['FER-03-004', 'Cable flexible 3x1,5 mm²', 'PRODUCTO', 'ELECTRICIDAD', 'M', 1700, [['ROLLO', 100, 149600]]],
            ['FER-03-005', 'Conduit PVC 20 mm', 'PRODUCTO', 'ELECTRICIDAD', 'M', 1875, []],
            ['FER-03-006', 'Conduit PVC 25 mm', 'PRODUCTO', 'ELECTRICIDAD', 'M', 2050, []],
            ['FER-03-007', 'Caja de derivación PVC', 'PRODUCTO', 'ELECTRICIDAD', 'UN', 2225, []],
            ['FER-03-008', 'Interruptor simple 10 A', 'PRODUCTO', 'ELECTRICIDAD', 'UN', 2400, []],
            ['FER-03-009', 'Enchufe doble 10 A', 'PRODUCTO', 'ELECTRICIDAD', 'UN', 2575, []],
            ['FER-03-010', 'Automático monopolar 16 A', 'PRODUCTO', 'ELECTRICIDAD', 'UN', 2750, []],
            // Fijaciones
            ['FER-04-001', 'Tornillo madera 2 pulgadas', 'PRODUCTO', 'FIJACIONES', 'UN', 1425, [['CAJA', 100, 121125]]],
            ['FER-04-002', 'Tornillo madera 3 pulgadas', 'PRODUCTO', 'FIJACIONES', 'UN', 1600, [['CAJA', 100, 136000]]],
            ['FER-04-003', 'Tornillo volcanita 1 pulgada', 'PRODUCTO', 'FIJACIONES', 'UN', 1775, [['CAJA', 100, 150875]]],
            ['FER-04-004', 'Tornillo autoperforante 1 pulgada', 'PRODUCTO', 'FIJACIONES', 'UN', 1950, [['CAJA', 100, 165750]]],
            ['FER-04-005', 'Clavo corriente 2 pulgadas', 'PRODUCTO', 'FIJACIONES', 'KG', 2125, []],
            ['FER-04-006', 'Clavo corriente 3 pulgadas', 'PRODUCTO', 'FIJACIONES', 'KG', 2300, []],
            ['FER-04-007', 'Tarugo nylon 6 mm', 'PRODUCTO', 'FIJACIONES', 'UN', 2475, [['CAJA', 100, 210375]]],
            ['FER-04-008', 'Tarugo nylon 8 mm', 'PRODUCTO', 'FIJACIONES', 'UN', 2650, [['CAJA', 100, 225250]]],
            ['FER-04-009', 'Perno hexagonal M8x40', 'PRODUCTO', 'FIJACIONES', 'UN', 2825, [['CAJA', 100, 240125]]],
            ['FER-04-010', 'Tuerca hexagonal M8', 'PRODUCTO', 'FIJACIONES', 'UN', 3000, [['CAJA', 100, 255000]]],
            // Pinturas y accesorios
            ['FER-05-001', 'Látex blanco 1 L', 'PRODUCTO', 'PINTURAS', 'UN', 1675, []],
            ['FER-05-002', 'Látex blanco 4 L', 'PRODUCTO', 'PINTURAS', 'UN', 1850, []],
            ['FER-05-003', 'Esmalte al agua blanco 1 L', 'PRODUCTO', 'PINTURAS', 'UN', 2025, []],
            ['FER-05-004', 'Esmalte sintético negro 1 L', 'PRODUCTO', 'PINTURAS', 'UN', 2200, []],
            ['FER-05-005', 'Anticorrosivo gris 1 L', 'PRODUCTO', 'PINTURAS', 'UN', 2375, []],
            ['FER-05-006', 'Barniz marino 1 L', 'PRODUCTO', 'PINTURAS', 'UN', 2550, []],
            ['FER-05-007', 'Diluyente sintético 1 L', 'PRODUCTO', 'PINTURAS', 'UN', 2725, []],
            ['FER-05-008', 'Brocha 2 pulgadas', 'PRODUCTO', 'PINTURAS', 'UN', 2900, []],
            ['FER-05-009', 'Rodillo pintura 18 cm', 'PRODUCTO', 'PINTURAS', 'UN', 3075, []],
            ['FER-05-010', 'Bandeja para rodillo', 'PRODUCTO', 'PINTURAS', 'UN', 3250, []],
            // Gasfitería
            ['FER-06-001', 'Tubo PVC sanitario 40 mm', 'PRODUCTO', 'GASFITERIA', 'M', 1925, []],
            ['FER-06-002', 'Tubo PVC sanitario 110 mm', 'PRODUCTO', 'GASFITERIA', 'M', 2100, []],
            ['FER-06-003', 'Codo PVC 40 mm 90°', 'PRODUCTO', 'GASFITERIA', 'UN', 2275, []],
            ['FER-06-004', 'Codo PVC 110 mm 90°', 'PRODUCTO', 'GASFITERIA', 'UN', 2450, []],
            ['FER-06-005', 'Tee PVC 40 mm', 'PRODUCTO', 'GASFITERIA', 'UN', 2625, []],
            ['FER-06-006', 'Tubo PPR 20 mm', 'PRODUCTO', 'GASFITERIA', 'M', 2800, []],
            ['FER-06-007', 'Codo PPR 20 mm', 'PRODUCTO', 'GASFITERIA', 'UN', 2975, []],
            ['FER-06-008', 'Llave de paso 1/2 pulgada', 'PRODUCTO', 'GASFITERIA', 'UN', 3150, []],
            ['FER-06-009', 'Flexible agua 1/2 pulgada 40 cm', 'PRODUCTO', 'GASFITERIA', 'UN', 3325, []],
            ['FER-06-010', 'Cinta teflón 12 mm', 'PRODUCTO', 'GASFITERIA', 'UN', 3500, []],
            // Herramientas manuales
            ['FER-07-001', 'Martillo carpintero 16 oz', 'PRODUCTO', 'HERRAMIENTAS', 'UN', 2175, []],
            ['FER-07-002', 'Alicate universal 8 pulgadas', 'PRODUCTO', 'HERRAMIENTAS', 'UN', 2350, []],
            ['FER-07-003', 'Alicate de corte 6 pulgadas', 'PRODUCTO', 'HERRAMIENTAS', 'UN', 2525, []],
            ['FER-07-004', 'Destornillador paleta 6 mm', 'PRODUCTO', 'HERRAMIENTAS', 'UN', 2700, []],
            ['FER-07-005', 'Destornillador cruz PH2', 'PRODUCTO', 'HERRAMIENTAS', 'UN', 2875, []],
            ['FER-07-006', 'Llave ajustable 10 pulgadas', 'PRODUCTO', 'HERRAMIENTAS', 'UN', 3050, []],
            ['FER-07-007', 'Huincha medir 5 m', 'PRODUCTO', 'HERRAMIENTAS', 'UN', 3225, []],
            ['FER-07-008', 'Nivel burbuja 60 cm', 'PRODUCTO', 'HERRAMIENTAS', 'UN', 3400, []],
            ['FER-07-009', 'Serrucho carpintero 20 pulgadas', 'PRODUCTO', 'HERRAMIENTAS', 'UN', 3575, []],
            ['FER-07-010', 'Pala punta mango largo', 'PRODUCTO', 'HERRAMIENTAS', 'UN', 3750, []],
            // Maderas y tableros
            ['FER-08-001', 'Pino dimensionado 2x2', 'PRODUCTO', 'MADERAS', 'M', 2425, []],
            ['FER-08-002', 'Pino dimensionado 2x3', 'PRODUCTO', 'MADERAS', 'M', 2600, []],
            ['FER-08-003', 'Pino dimensionado 2x4', 'PRODUCTO', 'MADERAS', 'M', 2775, []],
            ['FER-08-004', 'Pino cepillado 1x4', 'PRODUCTO', 'MADERAS', 'M', 2950, []],
            ['FER-08-005', 'Pino cepillado 1x6', 'PRODUCTO', 'MADERAS', 'M', 3125, []],
            ['FER-08-006', 'Terciado estructural 9 mm', 'PRODUCTO', 'MADERAS', 'UN', 3300, []],
            ['FER-08-007', 'Terciado estructural 12 mm', 'PRODUCTO', 'MADERAS', 'UN', 3475, []],
            ['FER-08-008', 'OSB 9,5 mm', 'PRODUCTO', 'MADERAS', 'UN', 3650, []],
            ['FER-08-009', 'OSB 11,1 mm', 'PRODUCTO', 'MADERAS', 'UN', 3825, []],
            ['FER-08-010', 'MDF 15 mm', 'PRODUCTO', 'MADERAS', 'UN', 4000, []],
            // Jardín y riego
            ['FER-09-001', 'Manguera jardín 1/2 pulgada', 'PRODUCTO', 'JARDIN', 'M', 2675, [['ROLLO', 100, 235400]]],
            ['FER-09-002', 'Manguera jardín 3/4 pulgada', 'PRODUCTO', 'JARDIN', 'M', 2850, [['ROLLO', 100, 250800]]],
            ['FER-09-003', 'Conector rápido 1/2 pulgada', 'PRODUCTO', 'JARDIN', 'UN', 3025, []],
            ['FER-09-004', 'Pistola de riego regulable', 'PRODUCTO', 'JARDIN', 'UN', 3200, []],
            ['FER-09-005', 'Aspersor circular', 'PRODUCTO', 'JARDIN', 'UN', 3375, []],
            ['FER-09-006', 'Programador riego básico', 'PRODUCTO', 'JARDIN', 'UN', 3550, []],
            ['FER-09-007', 'Tierra de hojas 20 L', 'PRODUCTO', 'JARDIN', 'UN', 3725, []],
            ['FER-09-008', 'Compost 20 L', 'PRODUCTO', 'JARDIN', 'UN', 3900, []],
            ['FER-09-009', 'Guantes jardinería par', 'PRODUCTO', 'JARDIN', 'UN', 4075, []],
            ['FER-09-010', 'Rastrillo metálico 14 dientes', 'PRODUCTO', 'JARDIN', 'UN', 4250, []],
            // Seguridad y protección
            ['FER-10-001', 'Guante nitrilo par', 'PRODUCTO', 'SEGURIDAD', 'UN', 2925, []],
            ['FER-10-002', 'Guante cabritilla par', 'PRODUCTO', 'SEGURIDAD', 'UN', 3100, []],
            ['FER-10-003', 'Lente seguridad transparente', 'PRODUCTO', 'SEGURIDAD', 'UN', 3275, []],
            ['FER-10-004', 'Casco seguridad blanco', 'PRODUCTO', 'SEGURIDAD', 'UN', 3450, []],
            ['FER-10-005', 'Mascarilla antipolvo', 'PRODUCTO', 'SEGURIDAD', 'UN', 3625, []],
            ['FER-10-006', 'Protector auditivo copa', 'PRODUCTO', 'SEGURIDAD', 'UN', 3800, []],
            ['FER-10-007', 'Chaleco reflectante', 'PRODUCTO', 'SEGURIDAD', 'UN', 3975, []],
            ['FER-10-008', 'Cinta peligro 100 m', 'PRODUCTO', 'SEGURIDAD', 'UN', 4150, []],
            ['FER-10-009', 'Candado 40 mm', 'PRODUCTO', 'SEGURIDAD', 'UN', 4325, []],
            ['FER-10-010', 'Extintor polvo químico 2 kg', 'PRODUCTO', 'SEGURIDAD', 'UN', 4500, []],
            ['SRV-001', 'Corte de madera a medida', 'SERVICIO', 'SERVICIOS', 'UN', 3700, []],
            ['SRV-002', 'Corte de cable por metro', 'SERVICIO', 'SERVICIOS', 'UN', 4900, []],
            ['SRV-003', 'Duplicado de llave estándar', 'SERVICIO', 'SERVICIOS', 'UN', 6100, []],
            ['SRV-004', 'Mezcla de pintura personalizada', 'SERVICIO', 'SERVICIOS', 'UN', 7300, []],
            ['SRV-005', 'Despacho local de materiales', 'SERVICIO', 'SERVICIOS', 'UN', 8500, []],
            ['SRV-006', 'Arriendo diario de taladro', 'SERVICIO', 'SERVICIOS', 'UN', 9700, []],
            ['SRV-007', 'Arriendo diario de rotomartillo', 'SERVICIO', 'SERVICIOS', 'UN', 10900, []],
            ['SRV-008', 'Instalación de cerradura', 'SERVICIO', 'SERVICIOS', 'H', 12100, []],
            ['SRV-009', 'Armado de mueble básico', 'SERVICIO', 'SERVICIOS', 'H', 13300, []],
            ['SRV-010', 'Asesoría técnica de materiales', 'SERVICIO', 'SERVICIOS', 'H', 14500, []],
        ];
    }
}
