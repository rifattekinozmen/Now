<?php

namespace Database\Factories;

use App\Models\BankStatementCsvImport;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankStatementCsvImport>
 */
class BankStatementCsvImportFactory extends Factory
{
    protected $model = BankStatementCsvImport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'original_filename' => 'statement.csv',
            'row_count' => 1,
            'rows' => [
                [
                    'booked_at' => '2026-03-01',
                    'amount' => '100.00',
                    'description' => 'Test',
                ],
            ],
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (BankStatementCsvImport $import): void {
            User::query()->whereKey($import->user_id)->update(['tenant_id' => $import->tenant_id]);
        });
    }
}
