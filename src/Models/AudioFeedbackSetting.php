<?php

namespace Blemli\AudioFeedback\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array{muted?: bool, volume?: int, overrides?: array<string, string>} | null $settings
 */
class AudioFeedbackSetting extends Model
{
    protected $table = 'audiofeedback_settings';

    protected $guarded = [];

    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * @return array{muted?: bool, volume?: int, overrides?: array<string, string>} | null
     */
    public static function for(mixed $userId): ?array
    {
        return static::query()
            ->where('user_id', (string) $userId)
            ->first()
            ?->settings;
    }
}
