<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGameRequest extends FormRequest
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
            'bgg_id' => ['nullable', 'integer', 'unique:games,bgg_id'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'min_players' => ['nullable', 'integer', 'min:1'],
            'max_players' => ['nullable', 'integer', 'min:1', 'gte:min_players'],
            'play_time_min' => ['nullable', 'integer', 'min:1'],
            'play_time_max' => ['nullable', 'integer', 'gte:play_time_min'],
            'min_age' => ['nullable', 'integer', 'min:0'],
            'weight_complexity' => ['nullable', 'numeric', 'between:1,5'],
            'description' => ['nullable', 'string'],
            'cover_url' => ['nullable', 'string', 'max:2048'],
            'rulebook_path' => ['nullable', 'string', 'max:2048'],
            'edition' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:10'],
            'condition_notes' => ['nullable', 'string'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'condition' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
