<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadGameCoverRequest extends FormRequest
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
            'cover' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ];
    }
}
