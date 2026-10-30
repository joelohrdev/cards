<?php

namespace App\Http\Resources;

use App\Models\Card;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Card
 */
class CardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'name' => $this->name,
            'subset' => $this->subset,
            'is_rookie' => $this->is_rookie,
            'is_autograph' => $this->is_autograph,
            'is_memorabilia' => $this->is_memorabilia,
            'is_short_print' => $this->is_short_print,
            'print_run' => $this->print_run,
            'team' => $this->whenLoaded('team', fn () => $this->team?->name),
            'players' => $this->whenLoaded('players', fn () => $this->players->map(fn (Player $player): array => [
                'id' => $player->id,
                'name' => $player->name,
            ])->values()),
            'set' => $this->whenLoaded('cardSet', fn () => CardSetResource::make($this->cardSet)->resolve()),
        ];
    }
}
