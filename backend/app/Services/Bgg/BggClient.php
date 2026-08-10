<?php

namespace App\Services\Bgg;

use App\Exceptions\BggRequestException;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;

class BggClient
{
    /**
     * @return list<array{bgg_id: int, title: string, year_published: int|null}>
     */
    public function search(string $query): array
    {
        $xml = $this->request('search', ['query' => $query, 'type' => 'boardgame']);

        $results = [];

        foreach ($xml->item as $item) {
            $title = $this->primaryName($item);

            if ($title === null) {
                continue;
            }

            $results[] = [
                'bgg_id' => (int) $item['id'],
                'title' => $title,
                'year_published' => isset($item->yearpublished) ? (int) $item->yearpublished['value'] : null,
            ];
        }

        return $results;
    }

    public function find(int $bggId): BggGameData
    {
        $xml = $this->request('thing', ['id' => $bggId, 'stats' => 1]);

        if (! isset($xml->item)) {
            throw BggRequestException::notFound($bggId);
        }

        $item = $xml->item;

        $publisher = null;
        $categories = [];

        foreach ($item->link as $link) {
            $type = (string) $link['type'];

            if ($type === 'boardgamepublisher' && $publisher === null) {
                $publisher = (string) $link['value'];
            } elseif ($type === 'boardgamecategory') {
                $categories[] = (string) $link['value'];
            }
        }

        $weight = isset($item->statistics->ratings->averageweight)
            ? (float) $item->statistics->ratings->averageweight['value']
            : null;

        return new BggGameData(
            bggId: $bggId,
            title: $this->primaryName($item) ?? "BGG #{$bggId}",
            publisher: $publisher,
            minPlayers: isset($item->minplayers) ? (int) $item->minplayers['value'] : null,
            maxPlayers: isset($item->maxplayers) ? (int) $item->maxplayers['value'] : null,
            playTimeMin: isset($item->minplaytime) ? (int) $item->minplaytime['value'] : null,
            playTimeMax: isset($item->maxplaytime) ? (int) $item->maxplaytime['value'] : null,
            minAge: isset($item->minage) ? (int) $item->minage['value'] : null,
            weightComplexity: $weight > 0 ? $weight : null,
            description: isset($item->description)
                ? html_entity_decode(strip_tags((string) $item->description), ENT_QUOTES)
                : null,
            coverUrl: isset($item->image) ? (string) $item->image : (isset($item->thumbnail) ? (string) $item->thumbnail : null),
            categories: $categories,
        );
    }

    private function primaryName(SimpleXMLElement $item): ?string
    {
        foreach ($item->name as $name) {
            if ((string) $name['type'] === 'primary') {
                return (string) $name['value'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function request(string $endpoint, array $query): SimpleXMLElement
    {
        $baseUrl = rtrim(config('services.bgg.base_url'), '/');

        $response = Http::get("{$baseUrl}/{$endpoint}", $query);

        if ($response->failed()) {
            throw BggRequestException::requestFailed();
        }

        $xml = simplexml_load_string($response->body());

        if ($xml === false) {
            throw BggRequestException::requestFailed();
        }

        return $xml;
    }
}
