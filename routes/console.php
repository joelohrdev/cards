<?php

use App\Actions\Collection\CaptureValueSnapshot;
use App\Models\EbayAccount;
use App\Models\User;
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
    ])->save();

    $this->info("Account ready for {$email}.");

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
