<?php
namespace App\Jobs;
use App\Models\User;
use App\Models\UserExport;
use App\Notifications\ExportReadyNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
class ExportUserData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 3;
    public function __construct(public int $exportId) { $this->afterCommit(); }
    public function handle(): void
    {
        $export = UserExport::findOrFail($this->exportId); $user = User::with(['wallets.currency', 'wallets.transactions', 'trades.fromCurrency', 'trades.toCurrency', 'kycSubmissions', 'beneficiaries.currency', 'moneyMovements.wallet.currency', 'complianceCases'])->findOrFail($export->user_id);
        $data = ['user' => $user->only(['id', 'name', 'email', 'email_verified_at', 'status', 'kyc_status', 'risk_level', 'created_at']), 'wallets' => $user->wallets, 'trades' => $user->trades, 'money_movements' => $user->moneyMovements, 'beneficiaries' => $user->beneficiaries, 'kyc_submissions' => $user->kycSubmissions, 'compliance_cases' => $user->complianceCases, 'exported_at' => now()->toIso8601String()];
        $path = 'exports/'.$user->id.'/'.$export->reference.'.json'; Storage::disk('local')->put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); $export->update(['status' => 'ready', 'path' => $path, 'expires_at' => now()->addDay()]); $user->notify(new ExportReadyNotification($export->reference));
    }
    public function failed(\Throwable $error): void { UserExport::whereKey($this->exportId)->update(['status' => 'failed', 'error' => mb_substr($error->getMessage(), 0, 1000)]); }
}
