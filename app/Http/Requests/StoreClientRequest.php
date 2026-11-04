<?php

namespace App\Http\Requests;

use App\Enums\RequestType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The public "request a partnership" / "request a service" forms.
 */
class StoreClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $wantsServices = $this->route('type') === RequestType::Service;

        return [
            'name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\s-]{7,30}$/'],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
            'services' => $wantsServices ? ['required', 'array', 'min:1'] : ['exclude'],
            'services.*' => ['string', Rule::exists('services', 'key')->where('is_active', true)],
            // Honeypot: hidden from people, filled in by bots.
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Whether a bot filled in the hidden field.
     */
    public function isSpam(): bool
    {
        return $this->filled('website');
    }
}
