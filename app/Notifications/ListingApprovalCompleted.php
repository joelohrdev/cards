<?php

namespace App\Notifications;

use App\Models\EbayListing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ListingApprovalCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public EbayListing $listing, public bool $published) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject(($this->published ? 'Listed on eBay: ' : 'Approved but not listed: ').$this->listing->title);

        $message = $this->published
            ? $message->line('Everyone approved, and the card is now live on eBay.')
            : $message->line('Everyone approved, but eBay didn’t accept the listing:')->line((string) $this->listing->error);

        return $message->action('Open the listing', route('ebay.items.listing', $this->listing->collection_item_id));
    }
}
