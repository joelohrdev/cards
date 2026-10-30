<?php

namespace App\Notifications;

use App\Models\EbayListing;
use App\Models\User;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ListingApprovalRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public EbayListing $listing, public User $requester) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Approve eBay listing: {$this->listing->title}")
            ->line("{$this->requester->name} wants to list this card on eBay for $".Money::toDollars($this->listing->price).':')
            ->line($this->listing->title)
            ->line('It goes live once everyone has approved it.')
            ->action('Review it', route('ebay.approvals.index'));
    }
}
