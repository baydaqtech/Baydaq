<?php

namespace App\Actions\Projects;

use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SyncProjectItems
{
    /**
     * Make a project's list (milestones, team, files) match the submitted rows:
     * rows with an id are updated, rows without are created, missing rows are removed.
     *
     * @param  HasMany<Model, Project>  $relation
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $fields
     */
    public function handle(HasMany $relation, array $rows, array $fields): void
    {
        DB::transaction(function () use ($relation, $rows, $fields): void {
            $keep = array_filter(array_column($rows, 'id'));
            $relation->getQuery()->clone()->whereNotIn('id', $keep)->delete();

            foreach ($rows as $row) {
                $values = Arr::only($row, $fields);

                if (! empty($row['id'])) {
                    $relation->getQuery()->clone()->whereKey($row['id'])->firstOrFail()->update($values);
                } else {
                    $relation->create($values);
                }
            }
        });
    }
}
