<?php

namespace App\Actions\Grading;

use App\Enums\Grader;
use App\Enums\GradingStatus;
use App\Models\CollectionItem;
use App\Models\GradingSubmission;
use App\Models\User;
use App\Support\ExistingNames;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Puts cards that were just added to the collection into grading, as chosen on the Add a card
 * form: into an open submission, or a new one (going to send, or already sent).
 */
class PlaceNewItemsInGrading
{
    public function __construct(
        private AddItemsToGradingSubmission $addItems,
        private SyncGradingItemStatuses $syncStatuses,
        private ExistingNames $existingNames,
    ) {}

    /**
     * @param  Collection<int, CollectionItem>  $items
     * @param  array{plan: string, submission_id?: int|null, grader?: string|null, service_level?: string|null, status?: string|null, submitted_on?: string|null}  $grading
     */
    public function handle(User $user, Collection $items, array $grading): GradingSubmission
    {
        return DB::transaction(function () use ($user, $items, $grading): GradingSubmission {
            $submission = filled($grading['submission_id'] ?? null)
                ? GradingSubmission::query()->open()->findOrFail($grading['submission_id'])
                : $this->startSubmission($user, $grading);

            $this->addItems->handle($submission, $items);
            $this->syncStatuses->handle($submission);

            return $submission;
        });
    }

    /**
     * @param  array{plan: string, grader?: string|null, service_level?: string|null, status?: string|null, submitted_on?: string|null}  $grading
     */
    private function startSubmission(User $user, array $grading): GradingSubmission
    {
        $sent = $grading['plan'] === 'sent';

        $submission = $user->gradingSubmissions()->make([
            'grader' => Grader::from($grading['grader'] ?? ''),
            'service_level' => $this->existingNames->spelling('service_level', $grading['service_level'] ?? null),
            'status' => $sent ? ($grading['status'] ?? GradingStatus::Submitted->value) : GradingStatus::Preparing->value,
            'submitted_on' => $sent ? ($grading['submitted_on'] ?? null) : null,
        ]);
        $submission->stampMilestoneDates();
        $submission->save();

        return $submission;
    }
}
