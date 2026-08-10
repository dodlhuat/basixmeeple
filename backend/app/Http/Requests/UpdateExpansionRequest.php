<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpansionRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'bgg_id' => ['sometimes', 'nullable', 'integer', Rule::unique('expansions', 'bgg_id')->ignore($this->route('expansion'))],
            'cover_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }
}
