<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
class ExportReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(private readonly string $reference) { $this->afterCommit(); }
    public function via(object $notifiable): array { return ['mail', 'database']; }
    public function toMail(object $notifiable): MailMessage { return (new MailMessage)->subject('Your Atlas Wallet export is ready')->line('Your requested account export is ready for secure download for 24 hours.')->action('Open data exports', url('/')); }
    public function toArray(object $notifiable): array { return ['event' => 'export.ready', 'reference' => $this->reference]; }
}
