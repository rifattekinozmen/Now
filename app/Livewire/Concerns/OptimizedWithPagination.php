<?php

namespace App\Livewire\Concerns;

use Livewire\WithPagination;

/**
 * Optimized pagination trait for Livewire components.
 * Reduces database queries and improves performance.
 */
trait OptimizedWithPagination
{
    use WithPagination;

    /**
     * Default pagination per page - reduced for faster loads
     */
    protected int $perPage = 15; // was 20

    /**
     * Get paginated items with query optimization
     */
    protected function getPaginatedItems($query, int $perPage = null, array $with = [], array $select = null)
    {
        $perPage = $perPage ?? $this->perPage;

        // Add eager loading to prevent N+1 queries
        foreach ($with as $relation) {
            $query->with($relation);
        }

        // Select specific columns if provided
        if ($select) {
            $query->select($select);
        }

        return $query->paginate($perPage);
    }

    /**
     * Reset pagination when filters change
     */
    protected function resetPaginationOnFilter(): void
    {
        $this->resetPage();
    }
}
