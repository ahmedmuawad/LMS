<?php

declare(strict_types=1);

use App\Core\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

/**
 * مصنع مشترك: مشترك بحد أدنى من الحقول المطلوبة.
 */
function makeTenant(string $plan = 'growth', array $attributes = []): Tenant
{
    return Tenant::create([
        'id' => 'tenant-'.uniqid(),
        'name' => 'أكاديمية الاختبار',
        'slug' => 'test-'.uniqid(),
        'owner_email' => 'owner@example.test',
        'plan_key' => $plan,
        'status' => 'active',
        ...$attributes,
    ]);
}
