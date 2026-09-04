<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
class KycStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(private readonly string $status, private readonly ?string $notes = null) { $this->afterCommit(); }
    public function via(object $notifiable): array { return ['mail', 'database']; }
    public function toMail(object $notifiable): MailMessage { $mail = (new MailMessage)->subject('Identity verification '.$this->status)->line('Your identity verification was '.$this->status.'.'); if ($this->notes) $mail->line('Review note: '.$this->notes); return $mail->action('Open Atlas Wallet', url('/')); }
    public function toArray(object $notifiable): array { return ['event' => 'kyc.'.$this->status, 'status' => $this->status, 'notes' => $this->notes]; }
}
