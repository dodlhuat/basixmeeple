<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachGameRequest extends FormRequest
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
            'location' => ['nullable', 'string', 'max:255'],
            'condition' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
