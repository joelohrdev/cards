<?php

namespace Database\Seeders;

use App\Actions\Catalog\ResolveCard;
use App\Actions\Catalog\ResolveCardSet;
use App\Actions\Collection\CaptureValueSnapshot;
use App\Actions\Collection\SaveCollectionItem;
use App\Enums\LocationType;
use App\Enums\Sport;
use App\Models\User;
use App\Models\ValueSnapshot;
use App\Support\ChecklistParser;
use Illuminate\Database\Seeder;

/**
 * A small, realistic mixed vintage/modern collection for local development.
 */
class DemoCollectionSeeder extends Seeder
{
    public function run(User $user, ResolveCardSet $resolveCardSet, ResolveCard $resolveCard, SaveCollectionItem $saveCollectionItem): void
    {
        $shelf = $user->storageLocations()->create(['name' => 'Closet shelf', 'type' => LocationType::Shelf]);
        $vintageBox = $user->storageLocations()->create(['name' => 'Vintage box', 'type' => LocationType::Box, 'parent_id' => $shelf->id, 'code' => 'V1']);
        $slabCase = $user->storageLocations()->create(['name' => 'Slab case', 'type' => LocationType::Case, 'parent_id' => $shelf->id, 'code' => 'S1']);
        $binder = $user->storageLocations()->create(['name' => 'Rookie binder', 'type' => LocationType::Binder]);

        $sets = [
            [Sport::Baseball, 1952, 'Topps', 'Base', ['311 Mickey Mantle - New York Yankees SP', '261 Willie Mays - New York Giants', '1 Andy Pafko - Brooklyn Dodgers']],
            [Sport::Baseball, 1989, 'Upper Deck', 'Base', ['1 Ken Griffey Jr. - Seattle Mariners RC', '25 Randy Johnson - Montreal Expos RC', '273 Jim Abbott - California Angels RC', '357 Dale Murphy - Atlanta Braves']],
            [Sport::Basketball, 1986, 'Fleer', 'Base', ['57 Michael Jordan - Chicago Bulls RC', '9 Larry Bird - Boston Celtics', '68 Magic Johnson - Los Angeles Lakers']],
            [Sport::Football, 2000, 'Playoff', 'Contenders', ['144 Tom Brady - New England Patriots RC AU']],
            [Sport::Baseball, 2018, 'Topps', 'Update', ['US1 Shohei Ohtani - Los Angeles Angels RC', 'US250 Ronald Acuña Jr. - Atlanta Braves RC', 'US300 Juan Soto - Washington Nationals RC']],
            [Sport::Basketball, 2019, 'Panini', 'Prizm', ['248 Zion Williamson - New Orleans Pelicans RC', '249 Ja Morant - Memphis Grizzlies RC']],
        ];

        $cards = [];

        foreach ($sets as [$sport, $year, $brand, $name, $checklist]) {
            $set = $resolveCardSet->handle($sport, $year, $brand, $name);

            foreach ($checklist as $line) {
                $card = (new ChecklistParser)->parse($line)[0];
                $cards[$card['number'].' '.$year] = $resolveCard->handle($set, $card);
            }
        }

        $prizm = $cards['248 2019']->cardSet;
        $silver = $prizm->parallels()->firstOrCreate(['name' => 'Silver']);
        $prizm->parallels()->firstOrCreate(['name' => 'Gold'], ['print_run' => 10]);

        $items = [
            ['311 1952', ['is_graded' => true, 'grader' => 'PSA', 'grade' => '3', 'cert_number' => '10004321', 'purchase_price' => 18500, 'estimated_value' => 24000, 'storage_location_id' => $slabCase->id, 'tags' => ['Grails', 'Yankees']]],
            ['261 1952', ['raw_condition' => 'vg', 'purchase_price' => 900, 'estimated_value' => 1100, 'storage_location_id' => $vintageBox->id, 'tags' => ['Grails']]],
            ['1 1989', ['is_graded' => true, 'grader' => 'PSA', 'grade' => '9', 'purchase_price' => 120, 'estimated_value' => 160, 'storage_location_id' => $slabCase->id, 'tags' => ['Mariners PC']]],
            ['1 1989', ['raw_condition' => 'nm-mt', 'purchase_price' => 25, 'estimated_value' => 40, 'storage_location_id' => $binder->id, 'tags' => ['Grading candidates', 'Mariners PC']]],
            ['25 1989', ['raw_condition' => 'nm', 'purchase_price' => 5, 'estimated_value' => 12, 'storage_location_id' => $binder->id]],
            ['57 1986', ['is_graded' => true, 'grader' => 'BGS', 'grade' => '8.5', 'purchase_price' => 6000, 'estimated_value' => 7200, 'storage_location_id' => $slabCase->id, 'tags' => ['Grails']]],
            ['9 1986', ['raw_condition' => 'ex-mt', 'purchase_price' => 40, 'estimated_value' => 55, 'storage_location_id' => $vintageBox->id]],
            ['144 2000', ['is_graded' => true, 'grader' => 'PSA', 'grade' => '8', 'purchase_price' => 28000, 'estimated_value' => 35000, 'storage_location_id' => $slabCase->id, 'tags' => ['Grails']]],
            ['US1 2018', ['raw_condition' => 'gem-mint', 'purchase_price' => 30, 'estimated_value' => 75, 'storage_location_id' => $binder->id, 'tags' => ['Grading candidates']]],
            ['US250 2018', ['raw_condition' => 'nm-mt', 'purchase_price' => 8, 'estimated_value' => 20, 'storage_location_id' => $binder->id, 'tags' => ['Flip']]],
            ['248 2019', ['parallel_id' => $silver->id, 'raw_condition' => 'mint', 'purchase_price' => 150, 'estimated_value' => 210, 'tags' => ['Flip']]],
            ['249 2019', ['raw_condition' => 'nm', 'purchase_price' => 12, 'estimated_value' => 9]],
        ];

        foreach ($items as $index => [$key, $attributes]) {
            $saveCollectionItem->handle($user, [
                'card_id' => $cards[$key]->id,
                'purchase_date' => now()->subMonths(30 - $index * 2)->toDateString(),
                ...$attributes,
            ]);
        }

        $user->wantListEntries()->create(['card_id' => $cards['68 1986']->id, 'max_price' => 15000, 'priority' => 3]);
        $user->wantListEntries()->create(['card_id' => $cards['US300 2018']->id, 'max_price' => 4000]);

        $current = app(CaptureValueSnapshot::class)->handle();

        foreach (range(1, 12) as $monthsAgo) {
            ValueSnapshot::query()->create([
                'captured_on' => today()->subMonths($monthsAgo),
                'item_count' => max(1, $current->item_count - intdiv($monthsAgo, 2)),
                'total_value' => (int) round($current->total_value * (1 - $monthsAgo * 0.03)),
                'total_cost' => (int) round($current->total_cost * (1 - $monthsAgo * 0.025)),
            ]);
        }
    }
}
