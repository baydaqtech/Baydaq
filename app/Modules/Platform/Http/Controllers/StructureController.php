<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Access\ReachableTree;
use App\Modules\Platform\Enums\SuiteFeature;
use App\Modules\Platform\Http\Controllers\Concerns\AuthorizesSuite;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Http\Resources\OrganizationResource;
use Inertia\Response;

class StructureController extends Controller
{
    use AuthorizesSuite, RendersSuitePages;

    /**
     * The tree the user may see for the structure feature, with every field of each node.
     */
    public function index(ReachableTree $tree): Response
    {
        $organizations = $tree->for($this->access(), SuiteFeature::Structure->key('view'));

        return $this->suitePage('Suite/Structure/Index', [
            'organizations' => OrganizationResource::collection($organizations),
        ]);
    }
}
