<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\FiscalOpeningBalanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'chart_account_id',
    'fiscal_year',
    'opening_debit',
    'opening_credit',
])]
class FiscalOpeningBalance extends Model
{
    /** @use HasFactory<FiscalOpeningBalanceFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return BelongsTo<ChartAccount, $this>
     */
    public function chartAccount(): BelongsTo
    {
        return $this->belongsTo(ChartAccount::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
