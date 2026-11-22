<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientDocument>
 */
class ClientDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->words(3, true);
        $content = "<!doctype html><html><head><title>{$title}</title></head><body><h1>{$title}</h1></body></html>";

        return [
            'client_id' => Client::factory(),
            'title' => $title,
            'note' => null,
            'file_name' => str($title)->slug().'.html',
            'size' => strlen($content),
            'content' => $content,
        ];
    }
}
