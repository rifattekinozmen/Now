<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\BankStatementCsvImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'user_id',
    'original_filename',
    'row_count',
    'rows',
])]
class BankStatementCsvImport extends Model
{
    /** @use HasFactory<BankStatementCsvImportFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rows' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
