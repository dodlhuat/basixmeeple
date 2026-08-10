<?php

namespace App\Http\Requests;

use App\Enums\PlaySessionOutcome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdatePlaySessionRequest extends FormRequest
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
                'sometimes',
                'uuid',
                Rule::exists('collection_games', 'game_id')->where('collection_id', $collection->id),
            ],
            'played_at' => ['sometimes', 'required', 'date'],
            'duration_min' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'outcome' => ['sometimes', 'nullable', new Enum(PlaySessionOutcome::class)],
            'notes' => ['sometimes', 'nullable', 'string'],
            'players' => ['sometimes', 'array'],
            'players.*.user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'players.*.player_name' => ['required', 'string', 'max:255'],
            'players.*.is_winner' => ['sometimes', 'boolean'],
            'players.*.score' => ['nullable', 'integer'],
        ];
    }
}
