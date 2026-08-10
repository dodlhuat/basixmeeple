<?php

namespace App\Services\Bgg;

final readonly class BggGameData
{
    /**
     * @param  list<string>  $categories
     */
    public function __construct(
        public int $bggId,
        public string $title,
        public ?string $publisher,
        public ?int $minPlayers,
        public ?int $maxPlayers,
        public ?int $playTimeMin,
        public ?int $playTimeMax,
        public ?int $minAge,
        public ?float $weightComplexity,
        public ?string $description,
        public ?string $coverUrl,
        public array $categories,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toGameAttributes(): array
    {
        return [
            'title' => $this->title,
            'bgg_id' => $this->bggId,
            'publisher' => $this->publisher,
            'min_players' => $this->minPlayers,
            'max_players' => $this->maxPlayers,
            'play_time_min' => $this->playTimeMin,
            'play_time_max' => $this->playTimeMax,
            'min_age' => $this->minAge,
            'weight_complexity' => $this->weightComplexity,
            'description' => $this->description,
            'cover_url' => $this->coverUrl,
        ];
    }
}
