<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TransactionNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(private readonly array $transaction, private readonly string $event) { $this->afterCommit(); }
    public function via(object $notifiable): array { return ['mail', 'database']; }
    public function toMail(object $notifiable): MailMessage { return (new MailMessage)->subject('Atlas Wallet: '.str_replace('_', ' ', ucfirst($this->event)))->line('A wallet operation has completed.')->line('Amount: '.$this->transaction['amount'])->line('Reference: '.$this->transaction['reference'])->action('View your wallet', url('/')); }
    public function toArray(object $notifiable): array { return ['event' => $this->event, ...$this->transaction]; }
}
