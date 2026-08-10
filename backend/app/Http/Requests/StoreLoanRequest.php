<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanRequest extends FormRequest
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
        $collection = $this->route('collection');

        return [
            'game_id' => [
                'required',
                'uuid',
                Rule::exists('collection_games', 'game_id')->where('collection_id', $collection->id),
            ],
            'borrower_name' => ['required', 'string', 'max:255'],
            'borrower_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'loaned_at' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
