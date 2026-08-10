<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpansionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('game')->isEditableBy($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'bgg_id' => ['nullable', 'integer', 'unique:expansions,bgg_id'],
            'cover_url' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
