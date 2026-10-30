<?php

namespace App\Notifications;

use App\Models\EbayListing;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ListingApprovalDeclined extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public EbayListing $listing, public User $approver, public ?string $note) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Listing declined: {$this->listing->title}")
            ->line("{$this->approver->name} declined listing this card on eBay, so it’s back to a draft.");

        if ($this->note !== null && $this->note !== '') {
            $message->line("Their note: “{$this->note}”");
        }

        return $message->action('Open the listing', route('ebay.items.listing', $this->listing->collection_item_id));
    }
}
