<?php

namespace App\Modules\Assets\Actions\Inventory;

use App\Modules\Assets\Models\Attachment;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;

class StorePhotos
{
    public function __construct(private CurrentTenant $tenant) {}

    /**
     * Keep photos on the private assets disk under the owner and the document they belong to.
     *
     * @param  list<UploadedFile>  $files
     */
    public function handle(array $files, string $attachableType, int $attachableId, ?string $stage = null, ?Authenticatable $actor = null): void
    {
        $disk = config('baydaq.assets.disk');
        $clientId = $this->tenant->client()->id;

        foreach ($files as $file) {
            (new Attachment)->forceFill([
                'client_id' => $clientId, 'attachable_type' => $attachableType, 'attachable_id' => $attachableId, 'disk' => $disk,
                'path' => $file->store("{$clientId}/{$attachableType}/{$attachableId}", $disk), 'mime' => $file->getMimeType() ?? 'image/jpeg',
                'size' => (int) $file->getSize(), 'stage' => $stage, 'uploaded_by' => $actor?->getAuthIdentifier(),
            ])->save();
        }
    }
}
