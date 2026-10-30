<?php

namespace App\Http\Middleware;

use App\Enums\ApprovalDecision;
use App\Enums\ListingStatus;
use App\Models\ListingApproval;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'approvalsWaiting' => fn (): int => $request->user() === null ? 0 : ListingApproval::query()
                ->where('user_id', $request->user()->id)
                ->where('decision', ApprovalDecision::Pending->value)
                ->whereHas('listing', fn ($listings) => $listings->where('status', ListingStatus::PendingApproval->value))
                ->count(),
        ];
    }
}
