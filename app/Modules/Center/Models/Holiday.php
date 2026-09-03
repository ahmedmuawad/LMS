<?php

declare(strict_types=1);

namespace App\Modules\Center\Models;

use App\Core\Support\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

final class Holiday extends Model
{
    use HasTranslations;

    protected $table = 'center_holidays';

    protected $guarded = [];

    /** @var list<string> */
    protected array $translatable = ['name'];

    protected function casts(): array
    {
        return ['name' => 'array', 'starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * العطلة التي يقع فيها هذا اليوم، إن وُجدت.
     *
     * لا نسمّيها on(): الاسم محجوز في Eloquent لاختيار الاتصال،
     * وتجاوزه بتوقيع مختلف يكسر بناء أي استعلام على النموذج.
     */
    public static function covering(Carbon $date, ?int $branchId = null): ?self
    {
        return self::query()
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->first();
    }
}
