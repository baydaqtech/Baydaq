<?php

namespace Database\Factories;

use App\Models\ClientDocument;
use App\Models\ClientDocumentComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientDocumentComment>
 */
class ClientDocumentCommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_document_id' => ClientDocument::factory(),
            'by_team' => false,
            'body' => fake()->sentence(),
        ];
    }
}
