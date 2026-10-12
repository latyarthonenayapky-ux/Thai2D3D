<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    public const LOCALE_ENGLISH = 'en';

    public const LOCALE_BURMESE = 'my';

    public const KEYS = [
        'locale',
        'clock_format',
        'currency_label',
        'sale_typing_normalization',
        'review_alerts',
        'idle_minutes',
    ];

    protected $fillable = [
        'user_id',
        'key',
        'value',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function supportedLocales(): array
    {
        return [
            self::LOCALE_ENGLISH => 'English',
            self::LOCALE_BURMESE => 'မြန်မာ',
        ];
    }

    public static function isSupportedLocale(string $locale): bool
    {
        return array_key_exists($locale, self::supportedLocales());
    }
}
