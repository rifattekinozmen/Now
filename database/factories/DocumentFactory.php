<?php

namespace Database\Factories;

use App\Enums\DocumentCategory;
use App\Enums\DocumentFileType;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uploaded_by' => User::factory(),
            'title' => fake()->words(3, true),
            'file_path' => 'documents/'.fake()->uuid().'.pdf',
            'file_type' => DocumentFileType::Pdf,
            'file_size' => fake()->numberBetween(10000, 5000000),
            'category' => fake()->randomElement(DocumentCategory::cases()),
            'expires_at' => null,
            'notes' => null,
            'meta' => null,
        ];
    }

    public function expiringSoon(int $days = 15): static
    {
        return $this->state(['expires_at' => now()->addDays($days)->format('Y-m-d')]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDays(5)->format('Y-m-d')]);
    }

    public function contract(): static
    {
        return $this->state(['category' => DocumentCategory::Contract]);
    }
}
