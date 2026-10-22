<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAdminPermission(Permission::ProjectsView, Permission::ProjectsManage);
    }

    /**
     * The team with project access, or a user of the client that owns the project.
     */
    public function view(User $user, Project $project): bool
    {
        if ($user->isAdmin()) {
            return $this->viewAny($user);
        }

        return $user->client_id !== null && $user->client_id === $project->client_id;
    }
}
