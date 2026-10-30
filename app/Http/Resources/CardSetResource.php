<?php

namespace App\Http\Resources;

use App\Models\CardSet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CardSet
 */
class CardSetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'name' => $this->name,
            'year' => $this->year,
            'season' => $this->season,
            'sport' => $this->sport->value,
            'sport_label' => $this->sport->label(),
            'manufacturer' => $this->manufacturer?->name,
            'card_count' => $this->card_count,
        ];
    }
}
