<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| توزيع الصلاحيات على الأدوار — لكل مشترك على حدة.
|
| التوزيع كان في `config/roles.php` وحده: واحدٌ لكل المنصّة، لا يراه
| المشترك ولا يعدّله. فمركزٌ يريد موظّف الاستقبال يحصّل الأقساط ولا
| يرى الأرباح، ومدرسةٌ تريد المدرّس يرى درجات مجموعته ولا يصدّر
| البيانات — كلاهما يطلبها منّا.
|
| والأسماء تبقى ثوابت في `Ability`: المرونة في التوزيع لا في
| التعريف. فتعديلُ صفٍّ في القاعدة لا يخترع صلاحيةً لا يحرسها الكود.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_abilities', function (Blueprint $table): void {
            $table->id();
            $table->string('role', 32);

            /*
             | القائمة كاملةً في صفٍّ واحد لا صفٌّ لكل صلاحية.
             |
             | القراءة تقع في كل طلب وفي كل حراسة؛ وصفٌّ واحد يُقرأ
             | مرّةً، وخمسون صفّاً تُقرأ خمسين.
             */
            $table->json('abilities');

            $table->timestamps();

            $table->unique('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_abilities');
    }
};
