<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLoanRequest extends FormRequest
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
            'borrower_name' => ['sometimes', 'required', 'string', 'max:255'],
            'borrower_user_id' => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
            'loaned_at' => ['sometimes', 'required', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'returned_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
