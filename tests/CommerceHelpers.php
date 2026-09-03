<?php

declare(strict_types=1);

use App\Modules\Commerce\Models\Coupon;
use App\Modules\Commerce\Models\Product;
use App\Modules\Lms\Models\Course;

/*
 | مصانع بيانات التجارة — تُستدعى دائماً داخل سياق مشترك.
 */

function seedProduct(array $overrides = []): Product
{
    return Product::create([
        'type' => 'digital',
        'slug' => 'product-'.uniqid(),
        'title' => ['ar' => 'منتج رقمي', 'en' => 'Digital product'],
        'currency' => (string) (tenant('currency') ?? 'EGP'),
        'price_minor' => 20000,
        'status' => 'published',
        ...$overrides,
    ]);
}

/** منتج الكورس يُنشئه المراقب تلقائياً عند النشر. */
function productForCourse(Course $course): Product
{
    return Product::where('purchasable_type', Course::class)
        ->where('purchasable_id', $course->getKey())
        ->firstOrFail();
}

function seedCoupon(array $overrides = []): Coupon
{
    return Coupon::create([
        'code' => 'SAVE'.random_int(100, 999),
        'type' => 'percent',
        'value' => 20,
        'usage_limit_per_user' => 1,
        'is_active' => true,
        ...$overrides,
    ]);
}
