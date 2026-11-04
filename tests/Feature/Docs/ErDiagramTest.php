<?php

namespace Tests\Feature\Docs;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The ER diagram in docs/ER.md must match the migrations. When this fails, run
 * php artisan docs:er and commit the result.
 */
class ErDiagramTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_er_diagram_matches_the_schema(): void
    {
        $this->artisan('docs:er', ['--check' => true])->assertSuccessful();
    }
}
