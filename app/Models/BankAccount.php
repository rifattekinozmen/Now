<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\BankAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'name',
    'bank_name',
    'account_number',
    'iban',
    'currency_code',
    'opening_balance',
    'opened_at',
    'is_active',
    'notes',
    'meta',
])]
class BankAccount extends Model
{
    /** @use HasFactory<BankAccountFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'opened_at' => 'date',
            'is_active' => 'boolean',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
