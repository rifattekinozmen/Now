<?php

namespace App\Models;

use App\Enums\BankTransactionType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'bank_account_id',
    'transaction_date',
    'amount',
    'currency_code',
    'transaction_type',
    'reference_no',
    'description',
    'matched_payment_id',
    'matched_voucher_id',
    'is_reconciled',
    'meta',
])]
class BankTransaction extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transaction_type' => BankTransactionType::class,
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
            'is_reconciled' => 'boolean',
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

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function matchedPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'matched_payment_id');
    }

    /**
     * @return BelongsTo<Voucher, $this>
     */
    public function matchedVoucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'matched_voucher_id');
    }

    public function scopeCredits(Builder $query): Builder
    {
        return $query->where('transaction_type', BankTransactionType::Credit->value);
    }

    public function scopeDebits(Builder $query): Builder
    {
        return $query->where('transaction_type', BankTransactionType::Debit->value);
    }

    public function scopeUnreconciled(Builder $query): Builder
    {
        return $query->where('is_reconciled', false);
    }
}
