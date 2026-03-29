<?php

namespace Database\Factories;

use App\Models\ChartAccount;
use App\Models\FiscalOpeningBalance;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalOpeningBalance>
 */
class FiscalOpeningBalanceFactory extends Factory
{
    protected $model = FiscalOpeningBalance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();

        return [
            'tenant_id' => $tenant->id,
            'chart_account_id' => ChartAccount::factory()->create(['tenant_id' => $tenant->id])->id,
            'fiscal_year' => (int) date('Y'),
            'opening_debit' => '0.00',
            'opening_credit' => '0.00',
        ];
    }
}
