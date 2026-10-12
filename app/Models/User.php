<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'expires_at',
        'status',
        'admin_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
            'expires_at' => 'datetime:Asia/Yangon',
        ];
    }

    public function agentRoundSettings(): HasMany
    {
        return $this->hasMany(AgentRoundSetting::class, 'operator_id');
    }

    public function businessSettings(): HasOne
    {
        return $this->hasOne(AdminBusinessSetting::class, 'admin_id');
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(UserPreference::class);
    }

    /** @var array<string, string>|null */
    private ?array $preferenceCache = null;

    public function preference(string $key, ?string $default = null): ?string
    {
        $this->preferenceCache ??= $this->preferences()->get()->pluck('value', 'key')->all();

        return $this->preferenceCache[$key] ?? $default;
    }

    public function rememberPreference(string $key, string $value): void
    {
        UserPreference::query()->updateOrCreate(
            ['user_id' => $this->id, 'key' => $key],
            ['value' => $value],
        );

        $this->preferenceCache[$key] = $value;
    }

    public function locale(): string
    {
        $locale = $this->preference('locale', config('app.locale', UserPreference::LOCALE_ENGLISH));

        return $locale === UserPreference::LOCALE_BURMESE
            ? UserPreference::LOCALE_BURMESE
            : UserPreference::LOCALE_ENGLISH;
    }

    public function clockFormat(): string
    {
        return $this->preference('clock_format', '24') === '12' ? '12' : '24';
    }

    public function currencyLabel(): string
    {
        return strtoupper($this->preference('currency_label', (string) config('app.currency', 'MMK')));
    }

    public function prefers(string $key, string $default = 'off'): bool
    {
        return $this->preference($key, $default) === 'on';
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(self::class, 'admin_id');
    }

    public function operators(): HasMany
    {
        return $this->hasMany(self::class, 'admin_id')->where('role', 'operator');
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'temp_admin'], true);
    }

    public function isTempAdmin(): bool
    {
        return $this->role === 'temp_admin';
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isOperator(): bool
    {
        return $this->role === 'operator';
    }

    /**
     * Anyone allowed to hold Agent sessions and record sales for a business.
     */
    public function isHandler(): bool
    {
        return $this->isAdmin() || $this->isOperator();
    }
}
