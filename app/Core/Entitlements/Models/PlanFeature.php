<?php

declare(strict_types=1);

namespace App\Core\Entitlements\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

final class PlanFeature extends Model
{
    // جدول مركزي — يُقرأ من قاعدة المركز حتى داخل سياق مشترك
    use CentralConnection;

    protected $guarded = [];
}
