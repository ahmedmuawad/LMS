<?php

declare(strict_types=1);

namespace App\Modules\Services\Models;

use App\Core\Support\Concerns\HasTranslations;
use App\Core\Support\Money;
use App\Modules\Content\Models\Media;
use App\Modules\Lms\Models\Taxonomy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * الخدمة تُباع بوقت لا بنسخة: استشارة، جلسة تقوية، مراجعة ملف.
 * فالمخزون هنا ساعات المقدّم، وحجزان في وقت واحد خطأ لا خيار.
 */
final class Service extends Model
{
    use HasTranslations;
    use SoftDeletes;

    public const TYPES = [
        'appointment' => 'موعد محجوز',
        'delivery' => 'تسليم بمدة',
        'subscription' => 'اشتراك متجدّد',
    ];

    public const PRICE_TYPES = [
        'fixed' => 'سعر ثابت',
        'hourly' => 'بالساعة',
        'quote' => 'بعرض سعر',
    ];

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['title', 'excerpt', 'description'];

    protected function casts(): array
    {
        return [
            'title' => 'array', 'excerpt' => 'array', 'description' => 'array',
            'requirements' => 'array', 'deliverables' => 'array', 'seo' => 'array',
        ];
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class, 'category_id');
    }

    public function providers(): HasMany
    {
        return $this->hasMany(ServiceProvider::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function price(): Money
    {
        return Money::fromMinor((int) $this->price_minor, $this->currency);
    }

    public function needsQuote(): bool
    {
        return $this->price_type === 'quote';
    }

    public function isBookable(): bool
    {
        return $this->status === 'published' && $this->type === 'appointment';
    }

    /** أقرب لحظة يجوز الحجز فيها — تحترم مهلة التحضير. */
    public function earliestBookableAt(): Carbon
    {
        return now()->addHours((int) $this->lead_hours);
    }
}
