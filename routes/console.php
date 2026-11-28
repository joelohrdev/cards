<?php

use App\Actions\Collection\CaptureValueSnapshot;
use App\Actions\Collection\RecheckCardFromPhotos;
use App\Enums\UserRole;
use App\Models\CollectionItem;
use App\Models\EbayAccount;
use App\Models\User;
use App\Services\CardIdentification\CardIdentifier;
use App\Services\Ebay\EbayException;
use App\Services\Ebay\OrderSync;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('cards:snapshot-values', function (CaptureValueSnapshot $captureValueSnapshot) {
    $captureValueSnapshot->handle();

    $this->info('Captured the collection value snapshot.');
})->purpose('Record the collection\'s total value for the value-over-time chart');

Artisan::command('cards:create-user {email} {name} {--password=}', function (string $email, string $name) {
    $option = $this->option('password');
    $password = is_string($option) && $option !== '' ? $option : Str::password(16);

    $user = User::query()->firstOrNew(['email' => $email]);
    $user->forceFill([
        'name' => $name,
        'password' => Hash::make($password),
        'email_verified_at' => $user->email_verified_at ?? now(),
    ]);

    // Without an admin nobody can invite the other users, so the first account becomes one.
    if (! User::query()->where('role', UserRole::Admin)->exists()) {
        $user->role = UserRole::Admin;
    }

    $user->save();

    $this->info("Account ready for {$email}".($user->isAdmin() ? ' (admin).' : '.'));

    if (! $this->option('password')) {
        $this->line("Temporary password: {$password}");
    }
})->purpose('Create (or reset the password for) a collector account');

Schedule::command('cards:snapshot-values')->dailyAt('23:30')->withoutOverlapping();

Artisan::command('cards:sync-ebay-orders', function (OrderSync $orderSync) {
    EbayAccount::query()->lazyById()->each(function (EbayAccount $account) use ($orderSync): void {
        try {
            $recorded = $orderSync->sync($account);
            $this->info("eBay account {$account->id}: recorded {$recorded} new sales.");
        } catch (EbayException $exception) {
            report($exception);
            $this->error("eBay account {$account->id}: {$exception->getMessage()}");
        }
    });
})->purpose('Record sales for paid eBay orders of listed cards');

Schedule::command('cards:sync-ebay-orders')->everyFifteenMinutes()->withoutOverlapping();

Artisan::command('cards:recheck-photos {--apply : Move the copies; without it, only list what would change} {--card=* : Only copies of these checklist card IDs} {--item=* : Only these copy IDs} {--from= : Start at this copy ID, to pick up a run that stopped}', function (RecheckCardFromPhotos $recheck, CardIdentifier $identifier) {
    if (! $identifier->isConfigured()) {
        $this->error('Photo identification isn’t set up. Add ANTHROPIC_API_KEY to the .env file.');

        return 1;
    }

    $apply = (bool) $this->option('apply');
    $cardIds = $this->option('card');
    $itemIds = $this->option('item');
    $fromId = $this->option('from');
    $copies = CollectionItem::query()
        ->has('images')
        ->with(['card.cardSet.manufacturer', 'parallel'])
        ->when($cardIds !== [], fn ($items) => $items->whereIn('card_id', $cardIds))
        ->when($itemIds !== [], fn ($items) => $items->whereKey($itemIds))
        ->when(is_numeric($fromId), fn ($items) => $items->where('id', '>=', (int) $fromId));

    $this->info(($apply ? 'Rechecking' : 'Dry run: rechecking').' '.$copies->count().' copies with photos.');
    $counts = [];

    $copies->lazyById()->each(function (CollectionItem $item) use ($recheck, $apply, &$counts): void {
        $linkedTo = RecheckCardFromPhotos::cardLabel($item->card);
        $result = $recheck->handle($item, $apply);
        $counts[$result['outcome']] = ($counts[$result['outcome']] ?? 0) + 1;

        match ($result['outcome']) {
            'would_move' => $this->line("Copy {$item->id}: {$linkedTo} → <comment>{$result['target']}</comment>"),
            'moved' => $this->line("Copy {$item->id}: {$linkedTo} → <info>{$result['target']}</info>"),
            'unsure' => $this->line("Copy {$item->id}: {$linkedTo} — photos unclear (read: ".($result['reading'] ?: 'nothing').'), left alone'),
            'unreadable' => $this->warn("Copy {$item->id}: {$linkedTo} — couldn’t read the photos, left alone"),
            default => null,
        };
    });

    $this->newLine();
    $this->table(['Result', 'Copies'], collect([
        'correct' => 'Already on the right card',
        'would_move' => 'Would move',
        'moved' => 'Moved',
        'unsure' => 'Photos unclear, left alone',
        'unreadable' => 'Couldn’t read, left alone',
    ])->filter(fn (string $label, string $outcome): bool => isset($counts[$outcome]))
        ->map(fn (string $label, string $outcome): array => [$label, $counts[$outcome]])
        ->values()
        ->all());

    if (! $apply && isset($counts['would_move'])) {
        $this->info('Run again with --apply to move them.');
    }

    return 0;
})->purpose('Read each copy\'s photos again with AI and move copies linked to the wrong checklist card');
