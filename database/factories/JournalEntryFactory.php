<?php

namespace Database\Factories;

use App\Models\JournalEntry;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'entry_date' => now()->toDateString(),
            'reference' => null,
            'memo' => null,
            'source_type' => null,
            'source_key' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (JournalEntry $entry): void {
            User::query()->whereKey($entry->user_id)->update(['tenant_id' => $entry->tenant_id]);
        });
    }
}
