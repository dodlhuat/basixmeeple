<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWishlistItemRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'bgg_id' => ['nullable', 'integer'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:5'],
            'price_estimate' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
