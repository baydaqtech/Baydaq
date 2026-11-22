<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A standalone HTML page for a client: a title, an optional note, and the .html file (up to 20 MB).
 */
class ShareClientDocumentRequest extends FormRequest
{
    /** Largest page accepted, in kilobytes. */
    public const MAX_KB = 20480;

    /**
     * Access is checked by the route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'note' => ['nullable', 'string', 'max:500'],
            'file' => ['required', 'file', 'extensions:html,htm', 'max:'.self::MAX_KB],
        ];
    }
}
