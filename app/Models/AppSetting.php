<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AppSetting extends Model
{
    private const CACHE_KEY = 'app.settings.singleton';

    protected $fillable = [
        'issuer_name',
        'issuer_rfc',
        'issuer_business_name',
        'quote_brand_name',
        'brand_logo_path',
    ];

    public static function safeCurrent(): self
    {
        try {
            if (!Schema::hasTable('app_settings')) {
                return new self(self::defaultAttributes());
            }

            /** @var self $settings */
            $settings = Cache::rememberForever(self::CACHE_KEY, fn (): self => self::query()->firstOrCreate([], self::defaultAttributes()));

            return $settings;
        } catch (Throwable) {
            return new self(self::defaultAttributes());
        }
    }

    public static function refreshCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->brand_logo_path) {
            return null;
        }

        return asset('storage/'.$this->brand_logo_path);
    }

    public function logoAbsolutePath(): ?string
    {
        if (!$this->brand_logo_path) {
            return null;
        }

        $absolutePath = Storage::disk('public')->path($this->brand_logo_path);

        return is_file($absolutePath) ? $absolutePath : null;
    }

    /**
     * @return array<string, string>
     */
    private static function defaultAttributes(): array
    {
        return [
            'issuer_name' => (string) config('quotes.issuer_name', ''),
            'issuer_rfc' => (string) config('quotes.issuer_rfc', ''),
            'issuer_business_name' => (string) config('quotes.issuer_business_name', ''),
            'quote_brand_name' => (string) config('quotes.quote_brand_name', ''),
            'brand_logo_path' => '',
        ];
    }
}
