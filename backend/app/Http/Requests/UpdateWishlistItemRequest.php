<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWishlistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('collection')->canBeEditedBy($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'bgg_id' => ['sometimes', 'nullable', 'integer'],
            'priority' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'price_estimate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
