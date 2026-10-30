<?php

namespace App\Actions\Grading;

use App\Enums\GradingStatus;
use App\Enums\ItemStatus;
use App\Models\CollectionItem;
use App\Models\GradingSubmission;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Records a returned submission's grades and closes it out.
 *
 * Each card with a grade becomes a graded card (grader, grade, cert). Every card goes back to
 * "Owned". Optionally each card's grading fee plus an even share of the submission's shipping is
 * added to its purchase price, so profit on a later sale reflects what the slab really cost.
 */
class CompleteGradingSubmission
{
    /**
     * @param  array<int, array{grade?: string|null, cert_number?: string|null}>  $results  Keyed by collection item id.
     */
    public function handle(GradingSubmission $submission, array $results, bool $addCostsToBasis, ?CarbonInterface $returnedOn = null): void
    {
        DB::transaction(function () use ($submission, $results, $addCostsToBasis, $returnedOn): void {
            $items = $submission->collectionItems()->orderByPivot('id')->get();
            $shippingShares = self::splitEvenly($submission->shipping_cost, $items->count());

            foreach ($items->values() as $index => $item) {
                /** @var CollectionItem $item */
                $pivot = $item->getRelation('pivot');
                $grade = self::clean($results[$item->id]['grade'] ?? null);
                $certNumber = self::clean($results[$item->id]['cert_number'] ?? null);

                $submission->collectionItems()->updateExistingPivot($item->id, [
                    'resulting_grade' => $grade,
                    'resulting_cert_number' => $certNumber,
                ]);

                $changes = ['status' => ItemStatus::Owned];

                if ($grade !== null) {
                    $changes += [
                        'is_graded' => true,
                        'grader' => $submission->grader,
                        'grade' => $grade,
                        'cert_number' => $certNumber,
                        'raw_condition' => null,
                    ];
                }

                if ($addCostsToBasis) {
                    $added = (int) $pivot->fee + $shippingShares[$index];

                    if ($added > 0) {
                        $changes['purchase_price'] = ($item->purchase_price ?? 0) + $added;
                    }
                }

                $item->update($changes);
            }

            $submission->update([
                'status' => GradingStatus::Completed,
                'returned_on' => $returnedOn ?? $submission->returned_on ?? now(),
            ]);
        });
    }

    /**
     * Split cents across n parts so the parts add back up exactly, e.g. 1000 / 3 → [334, 333, 333].
     *
     * @return list<int>
     */
    public static function splitEvenly(int $cents, int $parts): array
    {
        if ($parts <= 0) {
            return [];
        }

        $base = intdiv($cents, $parts);
        $remainder = $cents % $parts;

        return array_map(fn (int $index): int => $base + ($index < $remainder ? 1 : 0), range(0, $parts - 1));
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
