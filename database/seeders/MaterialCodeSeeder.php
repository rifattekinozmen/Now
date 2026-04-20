<?php

namespace Database\Seeders;

use App\Models\MaterialCode;
use Illuminate\Database\Seeder;

class MaterialCodeSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            // Hammaddeler
            ['code' => 'CLN-0100', 'name' => 'Klinker (Gri)', 'category' => 'raw_material', 'handling_type' => 'bulk', 'is_adr' => false, 'unit' => 'ton'],
            ['code' => 'CLN-0600', 'name' => 'Klinker (Beyaz)', 'category' => 'raw_material', 'handling_type' => 'bulk', 'is_adr' => false, 'unit' => 'ton'],
            ['code' => 'FUL-0100', 'name' => 'Petrokok (MS)', 'category' => 'raw_material', 'handling_type' => 'bulk', 'is_adr' => false, 'unit' => 'ton'],
            ['code' => 'RWM-0160', 'name' => 'Cüruf', 'category' => 'raw_material', 'handling_type' => 'bulk', 'is_adr' => false, 'unit' => 'ton'],
            ['code' => 'RWM-0601', 'name' => 'Öğütülmüş Cüruf', 'category' => 'raw_material', 'handling_type' => 'bulk', 'is_adr' => false, 'unit' => 'ton'],
            ['code' => 'RDF-0102', 'name' => 'Hazır Atık (RDF)', 'category' => 'raw_material', 'handling_type' => 'adr', 'is_adr' => true, 'unit' => 'ton'],
            ['code' => 'ARM-0103', 'name' => 'Uçucu Kül', 'category' => 'raw_material', 'handling_type' => 'bulk', 'is_adr' => false, 'unit' => 'ton'],
            // Çimento (Dökme)
            ['code' => 'CEM-0101-DOK', 'name' => 'CEM I 42,5R - Dökme', 'category' => 'cement', 'handling_type' => 'bulk', 'is_adr' => false, 'unit' => 'ton'],
            ['code' => 'CEM-0201-DOK', 'name' => 'CEM II/A-S 42,5R (Starcem) - Dökme', 'category' => 'cement', 'handling_type' => 'bulk', 'is_adr' => false, 'unit' => 'ton'],
            ['code' => 'CEM-0620-DOK', 'name' => 'CEM II/A-LL 52,5R (SüperBeyaz+) - Dökme', 'category' => 'cement', 'handling_type' => 'bulk', 'is_adr' => false, 'unit' => 'ton'],
            ['code' => 'CEM-0633-DOK', 'name' => 'CEM II/B-LL 42,5R (ProBeyaz) - Dökme', 'category' => 'cement', 'handling_type' => 'bulk', 'is_adr' => false, 'unit' => 'ton'],
            // Çimento (Torbalı / BigBag)
            ['code' => 'CEM-0550-T50', 'name' => 'CEM VI (S-LL) 32,5R - 50kg Torbalı', 'category' => 'cement', 'handling_type' => 'bagged', 'is_adr' => false, 'unit' => 'ton'],
            ['code' => 'CEM-010-BIB-1000', 'name' => 'CEM I 42,5R - Big-Bag 1t', 'category' => 'cement', 'handling_type' => 'bigbag', 'is_adr' => false, 'unit' => 'ton'],
            ['code' => 'CEM-0302-BIB-1500', 'name' => 'CEM III/A 42,5N - Big-Bag 1.5t', 'category' => 'cement', 'handling_type' => 'bigbag', 'is_adr' => false, 'unit' => 'ton'],
            // Maden / Gübre
            ['code' => 'IRO-FE-LMP', 'name' => 'Demir Cevheri (Parça)', 'category' => 'mine', 'handling_type' => 'bulk', 'is_adr' => false, 'unit' => 'ton'],
            ['code' => 'IRO-FE-PEL', 'name' => 'Demir Cevheri (Pelet)', 'category' => 'mine', 'handling_type' => 'bulk', 'is_adr' => false, 'unit' => 'ton'],
            ['code' => 'URE-GRN-46', 'name' => 'Üre %46 Azotlu', 'category' => 'fertilizer', 'handling_type' => 'bulk', 'is_adr' => false, 'unit' => 'ton'],
            ['code' => 'DAP-1846-0', 'name' => 'DAP (18-46-0) Taban Gübresi', 'category' => 'fertilizer', 'handling_type' => 'bulk', 'is_adr' => false, 'unit' => 'ton'],
        ];

        foreach ($codes as $data) {
            MaterialCode::firstOrCreate(['code' => $data['code']], array_merge($data, ['is_active' => true]));
        }
    }
}
