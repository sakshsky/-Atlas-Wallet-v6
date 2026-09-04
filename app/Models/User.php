<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasApiTokens, HasFactory, MustVerifyEmail, Notifiable;

    protected $fillable = ['name', 'email', 'email_verified_at', 'password', 'role', 'status', 'kyc_status', 'kyc_verified_at', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'risk_level', 'daily_withdrawal_limit'];
    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];
    protected function casts(): array { return ['email_verified_at' => 'datetime', 'kyc_verified_at' => 'datetime', 'password' => 'hashed', 'two_factor_secret' => 'encrypted', 'two_factor_recovery_codes' => 'encrypted:array', 'two_factor_confirmed_at' => 'datetime', 'daily_withdrawal_limit' => 'decimal:18']; }
    public function wallets(): HasMany { return $this->hasMany(Wallet::class); }
    public function kycSubmissions(): HasMany { return $this->hasMany(KycSubmission::class); }
    public function trades(): HasMany { return $this->hasMany(Trade::class); }
    public function exports(): HasMany { return $this->hasMany(UserExport::class); }
    public function beneficiaries(): HasMany { return $this->hasMany(Beneficiary::class); }
    public function moneyMovements(): HasMany { return $this->hasMany(MoneyMovement::class); }
    public function complianceCases(): HasMany { return $this->hasMany(ComplianceCase::class); }
    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isKycApproved(): bool { return $this->kyc_status === 'approved' && $this->kyc_verified_at !== null; }
    public function hasTwoFactorEnabled(): bool { return filled($this->two_factor_secret) && $this->two_factor_confirmed_at !== null; }
}
