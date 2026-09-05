<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Modules\Center\Actions\EnrolStudent;
use App\Modules\Center\Models\Attendance;
use App\Modules\Center\Models\Group;
use App\Modules\Center\Models\Invoice;
use App\Modules\Center\Models\Student;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * الواجهة البرمجية العامة.
 *
 * `api_access` مفتاحٌ في الباقات بلا مسارٍ ولا مفتاح ولا توثيق.
 * والمشترك الذي يريد ربط منصّته بنظام مدرسته أو ببرنامج محاسبته
 * لا يجد باباً.
 *
 * ## ما تُخرجه وما لا تُخرجه
 *
 * تُخرج ما يحتاجه تكاملٌ حقيقي: الكورسات، الطلبة، التسجيلات،
 * المجموعات، الحضور، الفواتير. ولا تُخرج كلمات مرور ولا مفاتيح
 * ولا بيانات دفعٍ — تلك لا يحتاجها تكامل، وإخراجها يجعل مفتاحاً
 * مسرَّباً كارثة.
 *
 * وكل حقلٍ يُذكر صراحةً: `toArray()` على الموديل يُخرج ما يُضاف
 * إليه لاحقاً بلا أن ينتبه أحد.
 */
final class ApiController
{
    /** أقصى صفوف في الصفحة — يمنع طلباً واحداً يسحب القاعدة كلّها */
    private const MAX_PER_PAGE = 100;

    public function courses(Request $request): JsonResponse
    {
        $rows = Course::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return $this->page($rows, fn (Course $c): array => [
            'id' => $c->getKey(),
            'slug' => $c->slug,
            'title' => $c->title,
            'status' => $c->status,
            'price_minor' => (int) $c->price_minor,
            'currency' => $c->currency,
            'students_count' => (int) $c->students_count,
            'published_at' => $c->published_at?->toIso8601String(),
        ]);
    }

    public function students(Request $request): JsonResponse
    {
        $rows = Student::query()
            ->with(['user', 'grade'])
            ->when($request->filled('code'), fn ($q) => $q->where('code', $request->query('code')))
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return $this->page($rows, fn (Student $s): array => [
            'id' => $s->getKey(),
            'code' => $s->code,
            'name' => $s->user?->name,
            'phone' => $s->user?->phone,
            'grade' => $s->grade?->name,
            'status' => $s->status,
            'joined_at' => $s->joined_at,
        ]);
    }

    public function groups(Request $request): JsonResponse
    {
        $rows = Group::query()
            ->with(['subject', 'grade'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return $this->page($rows, fn (Group $g): array => [
            'id' => $g->getKey(),
            'name' => $g->name,
            'subject' => $g->subject?->name,
            'grade' => $g->grade?->name,
            'capacity' => (int) $g->capacity,
            'enrolled' => (int) $g->enrolled_count,
            'price_minor' => (int) $g->price_minor,
            'status' => $g->status,
        ]);
    }

    public function enrollments(Request $request): JsonResponse
    {
        $rows = Enrollment::query()
            ->with(['user', 'course'])
            ->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->integer('course_id')))
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return $this->page($rows, fn (Enrollment $e): array => [
            'id' => $e->getKey(),
            'course_id' => $e->course_id,
            'course' => $e->course?->title,
            'student' => $e->user?->name,
            'email' => $e->user?->email,
            'progress' => (int) $e->progress_percent,
            'status' => $e->status,
            'expires_at' => $e->expires_at?->toIso8601String(),
        ]);
    }

    public function attendance(Request $request): JsonResponse
    {
        $rows = Attendance::query()
            ->with(['student.user', 'session.group'])
            ->when($request->filled('from'), fn ($q) => $q->whereHas('session',
                fn ($s) => $s->whereDate('date', '>=', $request->query('from'))))
            ->when($request->filled('to'), fn ($q) => $q->whereHas('session',
                fn ($s) => $s->whereDate('date', '<=', $request->query('to'))))
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return $this->page($rows, fn (Attendance $a): array => [
            'id' => $a->getKey(),
            'student_code' => $a->student?->code,
            'student' => $a->student?->user?->name,
            'group' => $a->session?->group?->name,
            'date' => $a->session?->date?->toDateString(),
            'status' => $a->status,
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $rows = Invoice::query()
            ->with(['student.user'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return $this->page($rows, fn (Invoice $i): array => [
            'id' => $i->getKey(),
            'number' => $i->number,
            'student_code' => $i->student?->code,
            'student' => $i->student?->user?->name,
            'period' => $i->period,
            'total_minor' => (int) $i->total_minor,
            'paid_minor' => (int) $i->paid_minor,
            'currency' => $i->currency,
            'status' => $i->status,
            'due_on' => $i->due_on,
        ]);
    }

    /**
     * تسجيل طالب في مجموعة — النقطة الكاتبة الوحيدة.
     *
     * الكتابة تُفتح على أضيق باب: التسجيل هو ما يحتاجه تكاملٌ
     * حقيقي (نظام المدرسة يسجّل، والمنصة تتابع). وما عداه يُقرأ.
     */
    public function enrol(Request $request): JsonResponse
    {
        $input = $request->validate([
            'student_code' => ['required', 'string', 'max:32'],
            'group_id' => ['required', 'integer', 'exists:center_groups,id'],
        ]);

        $student = Student::where('code', $input['student_code'])->first();

        if ($student === null) {
            return response()->json(['message' => __('لا طالب بهذا الكود.')], 404);
        }

        $group = Group::findOrFail($input['group_id']);

        try {
            $enrolment = app(EnrolStudent::class)->handle($student, $group);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $enrolment->getKey(),
            'student_code' => $student->code,
            'group_id' => $group->getKey(),
            'price_minor' => (int) $enrolment->price_minor,
            'status' => $enrolment->status,
        ], 201);
    }

    /** معلومات المفتاح نفسه — يستعملها التكامل ليعرف ما يملك */
    public function me(Request $request): JsonResponse
    {
        $token = $request->attributes->get('api_token');

        return response()->json([
            'tenant' => tenant('slug'),
            'name' => site_name(),
            'token' => $token?->name,
            'scopes' => $token?->scopes ?? [],
            'expires_at' => $token?->expires_at?->toIso8601String(),
        ]);
    }

    private function perPage(Request $request): int
    {
        return min(self::MAX_PER_PAGE, max(1, (int) $request->query('per_page', 25)));
    }

    /** @param callable(mixed): array<string, mixed> $map */
    private function page(mixed $paginator, callable $map): JsonResponse
    {
        return response()->json([
            'data' => collect($paginator->items())->map($map)->values(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
