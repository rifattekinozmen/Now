<?php

use App\Models\DeliveryNumber;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

test('user can import pins from csv via livewire upload', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $csv = "pin_code,sas_no\nCSV-LW-1,SAS-9\n";
    $file = UploadedFile::fake()->createWithContent('pins.csv', $csv);

    Livewire::test('pages::admin.delivery-numbers-index')
        ->set('pinImportFile', $file)
        ->call('importPinsCsv')
        ->assertHasNoErrors();

    expect(
        DeliveryNumber::query()
            ->where('pin_code', 'CSV-LW-1')
            ->where('sas_no', 'SAS-9')
            ->exists()
    )->toBeTrue();
});
