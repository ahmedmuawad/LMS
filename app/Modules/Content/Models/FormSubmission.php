<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FormSubmission extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
