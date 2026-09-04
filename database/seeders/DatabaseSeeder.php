<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        /*
         | بيانات مرجعية لا بيانات تجربة: عملات ودول ومزايا وباقات.
         | هذه تُبذَر في الإنتاج كما تُبذَر محلياً.
         |
         | كان هنا مستخدم `test@example.com` من سقالة لارافيل — حسابٌ
         | بكلمة مرور معروفة يُزرع في كل قاعدة تُبذَر، ومصنعه يستدعي
         | `fake()` غير الموجود خارج التطوير فيُسقط البذر كلّه.
         | حسابات فريق المنصّة تُنشأ بأمر `super-admin:create` وحده.
         */
        $this->call([
            CurrencySeeder::class,
            CountrySeeder::class,
            FeatureSeeder::class,
            PlanSeeder::class,
        ]);
    }
}
