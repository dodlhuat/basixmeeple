<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncRequest extends FormRequest
{
    /**
     * Entities writable through the generic sync queue. `collections` and
     * `collection_user` are deliberately excluded — their REST endpoints
     * (CollectionController/CollectionMemberController) have side effects
     * (invite mails, owner invariants) that a blind last-write-wins upsert
     * would bypass, so those stay online-only.
     */
    public const ENTITIES = [
        'games',
        'expansions',
        'categories',
        'collection_games',
        'play_sessions',
        'session_players',
        'loans',
        'wishlist_items',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operations' => ['present', 'array'],
            'operations.*.entity' => ['required', Rule::in(self::ENTITIES)],
            'operations.*.entity_id' => ['required', 'uuid'],
            'operations.*.operation' => ['required', Rule::in(['create', 'update', 'delete'])],
            'operations.*.payload' => ['nullable', 'array'],
            'operations.*.queued_at' => ['required', 'date'],
        ];
    }
}
