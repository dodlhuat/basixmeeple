<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGameRequest extends FormRequest
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
            'bgg_id' => ['sometimes', 'nullable', 'integer', Rule::unique('games', 'bgg_id')->ignore($this->route('game'))],
            'publisher' => ['sometimes', 'nullable', 'string', 'max:255'],
            'min_players' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_players' => ['sometimes', 'nullable', 'integer', 'min:1', 'gte:min_players'],
            'play_time_min' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'play_time_max' => ['sometimes', 'nullable', 'integer', 'gte:play_time_min'],
            'min_age' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'weight_complexity' => ['sometimes', 'nullable', 'numeric', 'between:1,5'],
            'description' => ['sometimes', 'nullable', 'string'],
            'cover_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'rulebook_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'edition' => ['sometimes', 'nullable', 'string', 'max:255'],
            'language' => ['sometimes', 'nullable', 'string', 'max:10'],
            'condition_notes' => ['sometimes', 'nullable', 'string'],
            'purchase_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
