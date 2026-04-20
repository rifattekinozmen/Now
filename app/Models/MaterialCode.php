<?php

namespace App\Models;

use Database\Factories\MaterialCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'code',
    'name',
    'category',
    'handling_type',
    'is_adr',
    'unit',
    'is_active',
])]
class MaterialCode extends Model
{
    /** @use HasFactory<MaterialCodeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_adr' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<MaterialCode>  $query
     * @return Builder<MaterialCode>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            'raw_material' => __('Raw Material'),
            'cement' => __('Cement'),
            'packaged' => __('Packaged'),
            'fertilizer' => __('Fertilizer'),
            'mine' => __('Mine'),
            default => __('Other'),
        };
    }
}
