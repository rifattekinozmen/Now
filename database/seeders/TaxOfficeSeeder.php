<?php

namespace Database\Seeders;

use App\Models\TaxOffice;
use Illuminate\Database\Seeder;

class TaxOfficeSeeder extends Seeder
{
    public function run(): void
    {
        $offices = [
            // İstanbul
            ['code' => 'IST001', 'name' => 'Büyük Mükellefler Vergi Dairesi', 'city' => 'İstanbul', 'district' => 'Fatih'],
            ['code' => 'IST002', 'name' => 'Boğaziçi Kurumlar Vergi Dairesi', 'city' => 'İstanbul', 'district' => 'Beşiktaş'],
            ['code' => 'IST003', 'name' => 'Levent Vergi Dairesi', 'city' => 'İstanbul', 'district' => 'Beşiktaş'],
            ['code' => 'IST004', 'name' => 'Şişli Vergi Dairesi', 'city' => 'İstanbul', 'district' => 'Şişli'],
            ['code' => 'IST005', 'name' => 'Kadıköy Vergi Dairesi', 'city' => 'İstanbul', 'district' => 'Kadıköy'],
            ['code' => 'IST006', 'name' => 'Üsküdar Vergi Dairesi', 'city' => 'İstanbul', 'district' => 'Üsküdar'],
            ['code' => 'IST007', 'name' => 'Bakırköy Vergi Dairesi', 'city' => 'İstanbul', 'district' => 'Bakırköy'],
            ['code' => 'IST008', 'name' => 'Pendik Vergi Dairesi', 'city' => 'İstanbul', 'district' => 'Pendik'],
            // Ankara
            ['code' => 'ANK001', 'name' => 'Büyük Mükellefler Vergi Dairesi', 'city' => 'Ankara', 'district' => 'Çankaya'],
            ['code' => 'ANK002', 'name' => 'Kızılbey Vergi Dairesi', 'city' => 'Ankara', 'district' => 'Altındağ'],
            ['code' => 'ANK003', 'name' => 'Mithatpaşa Vergi Dairesi', 'city' => 'Ankara', 'district' => 'Çankaya'],
            ['code' => 'ANK004', 'name' => 'Yenimahalle Vergi Dairesi', 'city' => 'Ankara', 'district' => 'Yenimahalle'],
            // İzmir
            ['code' => 'IZM001', 'name' => 'Konak Vergi Dairesi', 'city' => 'İzmir', 'district' => 'Konak'],
            ['code' => 'IZM002', 'name' => 'Karşıyaka Vergi Dairesi', 'city' => 'İzmir', 'district' => 'Karşıyaka'],
            ['code' => 'IZM003', 'name' => 'Bornova Vergi Dairesi', 'city' => 'İzmir', 'district' => 'Bornova'],
            // Bursa
            ['code' => 'BRS001', 'name' => 'Bursa Vergi Dairesi Başkanlığı', 'city' => 'Bursa', 'district' => 'Osmangazi'],
            ['code' => 'BRS002', 'name' => 'Nilüfer Vergi Dairesi', 'city' => 'Bursa', 'district' => 'Nilüfer'],
            // Adana
            ['code' => 'ADN001', 'name' => 'Adana Vergi Dairesi Başkanlığı', 'city' => 'Adana', 'district' => 'Seyhan'],
            ['code' => 'ADN002', 'name' => 'Seyhan Vergi Dairesi', 'city' => 'Adana', 'district' => 'Seyhan'],
            // Mersin
            ['code' => 'MRS001', 'name' => 'Mersin Vergi Dairesi Başkanlığı', 'city' => 'Mersin', 'district' => 'Yenişehir'],
            // Gaziantep
            ['code' => 'GAZ001', 'name' => 'Şahinbey Vergi Dairesi', 'city' => 'Gaziantep', 'district' => 'Şahinbey'],
            // Kocaeli
            ['code' => 'KOC001', 'name' => 'İzmit Vergi Dairesi', 'city' => 'Kocaeli', 'district' => 'İzmit'],
            // Hatay
            ['code' => 'HTY001', 'name' => 'İskenderun Vergi Dairesi', 'city' => 'Hatay', 'district' => 'İskenderun'],
        ];

        foreach ($offices as $data) {
            TaxOffice::firstOrCreate(
                ['code' => $data['code']],
                array_merge($data, ['is_active' => true])
            );
        }
    }
}
