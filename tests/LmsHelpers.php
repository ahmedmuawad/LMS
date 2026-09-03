<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\CourseItem;
use App\Modules\Lms\Models\CourseSection;
use App\Modules\Lms\Models\Lesson;
use App\Modules\Lms\Models\Question;
use App\Modules\Lms\Models\Quiz;

/*
 | مصانع بيانات الـ LMS — تُستدعى دائماً داخل سياق مشترك.
 */

/** كورس بثلاثة دروس داخل قسم واحد. */
function seedCourse(array $overrides = []): Course
{
    $course = Course::create([
        'slug' => 'laravel-'.uniqid(),
        'title' => ['ar' => 'أساسيات لارافيل', 'en' => 'Laravel Basics'],
        'excerpt' => ['ar' => 'من الصفر إلى أول تطبيق'],
        'status' => 'published',
        'visibility' => 'public',
        'enrollment_type' => 'free',
        'price_minor' => 0,
        ...$overrides,
    ]);

    $section = CourseSection::create([
        'course_id' => $course->id,
        'title' => ['ar' => 'المقدمة'],
        'position' => 0,
    ]);

    foreach (range(1, 3) as $i) {
        $lesson = Lesson::create([
            'title' => ['ar' => "الدرس {$i}"],
            'type' => 'video',
            'duration_seconds' => 600,
        ]);

        CourseItem::create([
            'course_id' => $course->id,
            'section_id' => $section->id,
            'itemable_type' => Lesson::class,
            'itemable_id' => $lesson->id,
            'position' => $i,
        ]);
    }

    return $course->refresh();
}

function seedStudent(string $email = 'student@example.test'): User
{
    return User::create([
        'name' => 'طالب مجتهد', 'email' => $email,
        'password' => 'secret-password', 'role' => 'student', 'status' => 'active',
    ]);
}

/** اختبار بثلاثة أسئلة: اختيار واحد ومتعدّد ومقالي. */
function seedQuiz(Course $course, array $overrides = []): Quiz
{
    $quiz = Quiz::create([
        'title' => ['ar' => 'اختبار الوحدة الأولى'],
        'type' => 'static',
        'passing_percentage' => 60,
        'max_attempts' => 2,
        'shuffle_questions' => false,
        'shuffle_answers' => false,
        ...$overrides,
    ]);

    $single = Question::create([
        'body' => ['ar' => 'ما أمر إنشاء متحكّم؟'],
        'type' => 'single_choice',
        'options' => ['a' => 'make:controller', 'b' => 'new:controller'],
        'correct' => ['a'],
        'marks' => 2,
        'negative_marks' => 1,
    ]);

    $multiple = Question::create([
        'body' => ['ar' => 'أيٌّ مما يلي من طبقات لارافيل؟'],
        'type' => 'multiple_choice',
        'options' => ['a' => 'Route', 'b' => 'Eloquent', 'c' => 'Photoshop'],
        'correct' => ['a', 'b'],
        'marks' => 2,
    ]);

    $essay = Question::create([
        'body' => ['ar' => 'اشرح دور الـ Middleware.'],
        'type' => 'essay',
        'marks' => 6,
    ]);

    foreach ([$single, $multiple, $essay] as $position => $question) {
        $quiz->questions()->attach($question->id, ['position' => $position]);
    }

    CourseItem::create([
        'course_id' => $course->id,
        'itemable_type' => Quiz::class,
        'itemable_id' => $quiz->id,
        'position' => 99,
    ]);

    return $quiz;
}
