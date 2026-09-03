<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Actions;

use App\Modules\Commerce\Models\Product;
use App\Modules\Lms\Models\Course;

/**
 * كل كورس منشور له منتج يقابله.
 *
 * الكورس شيء تعليمي، والمنتج شيء تجاري. فصلهما يجعل الحزمة
 * والاشتراك والخدمة تُباع بنفس السلة، ويُبقي التقارير موحّدة.
 */
final class SyncCourseProduct
{
    public function handle(Course $course): ?Product
    {
        if ($course->status !== 'published' || $course->enrollment_type === 'invite') {
            $this->unpublish($course);

            return null;
        }

        $product = Product::withTrashed()
            ->where('purchasable_type', Course::class)
            ->where('purchasable_id', $course->getKey())
            ->first();

        $attributes = [
            'type' => 'course',
            'purchasable_type' => Course::class,
            'purchasable_id' => $course->getKey(),
            'slug' => 'course-'.$course->slug,
            'title' => $course->getTranslations('title'),
            'short_description' => $course->getTranslations('excerpt'),
            'cover_path' => $course->cover_path,
            'currency' => $course->currency ?? (string) (tenant('currency') ?? 'EGP'),
            'price_minor' => (int) $course->price_minor,
            'sale_price_minor' => null,
            'is_taxable' => true,
            'manage_stock' => $course->max_students !== null,
            'stock_qty' => $course->max_students === null
                ? 0
                : max(0, (int) $course->max_students - (int) $course->students_count),
            'requires_shipping' => false,
            'status' => 'published',
        ];

        if ($product === null) {
            return Product::create($attributes);
        }

        $product->restore();
        $product->forceFill($attributes)->save();

        return $product;
    }

    /** الكورس الذي لم يعد يُباع: منتجه يُخفى ولا يُحذف، فالطلبات القديمة تشير إليه. */
    private function unpublish(Course $course): void
    {
        Product::where('purchasable_type', Course::class)
            ->where('purchasable_id', $course->getKey())
            ->update(['status' => 'archived']);
    }
}
