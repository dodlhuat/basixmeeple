<?php

namespace App\Http\Requests;

use App\Enums\PlaySessionOutcome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StorePlaySessionRequest extends FormRequest
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
            'played_at' => ['required', 'date'],
            'duration_min' => ['nullable', 'integer', 'min:1'],
            'outcome' => ['nullable', new Enum(PlaySessionOutcome::class)],
            'notes' => ['nullable', 'string'],
            'players' => ['present', 'array'],
            'players.*.user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'players.*.player_name' => ['required', 'string', 'max:255'],
            'players.*.is_winner' => ['sometimes', 'boolean'],
            'players.*.score' => ['nullable', 'integer'],
        ];
    }
}
