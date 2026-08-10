<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncGameCategoriesRequest extends FormRequest
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
            'category_ids' => ['present', 'array'],
            'category_ids.*' => ['uuid', 'exists:categories,id'],
        ];
    }
}
