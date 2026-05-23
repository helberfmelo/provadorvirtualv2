<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailSetting extends Model
{
    use HasFactory;

    public const DEFAULT_SCOPE = 'saas';

    protected $guarded = [];

    protected $hidden = [
        'smtp_password',
    ];

    protected function casts(): array
    {
        return [
            'smtp_password' => 'encrypted',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(
            ['scope' => self::DEFAULT_SCOPE],
            [
                'mailer' => config('mail.default', 'smtp'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'username' => config('mail.mailers.smtp.username'),
                'encryption' => config('mail.mailers.smtp.scheme') ?: config('mail.mailers.smtp.encryption'),
                'from_address' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'is_active' => false,
            ],
        );
    }

    public function hasSmtpPassword(): bool
    {
        return filled($this->smtp_password);
    }
}
