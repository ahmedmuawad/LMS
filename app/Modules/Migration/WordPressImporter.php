<?php

declare(strict_types=1);

namespace App\Modules\Migration;

use App\Models\User;
use App\Modules\Lms\Models\Course;
use App\Modules\Lms\Models\CourseItem;
use App\Modules\Lms\Models\CourseSection;
use App\Modules\Lms\Models\Enrollment;
use App\Modules\Lms\Models\Lesson;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;

/**
 * استيراد من ووردبريس/WPLMS.
 *
 * وهو سبب المشروع: مدرسةٌ قائمة على ووردبريس بمئات الطلبة وسنوات
 * من المحتوى لا تنتقل إلى منصّةٍ تطلب منها أن تبدأ من الصفر.
 *
 * ## القراءة من القاعدة مباشرةً لا من ملفّ تصدير
 *
 * ملفّ WXR يحمل المقالات ولا يحمل الطلبة ولا تقدّمهم ولا درجاتهم —
 * وهي أثمن ما في المدرسة. والقراءة المباشرة تصل إلى كل شيء.
 *
 * ## والتشغيل الجافّ أولاً
 *
 * الاستيراد يكتب في قاعدةٍ عليها بيانات، فيُعرض ما سيقع قبل أن
 * يقع: كم كورساً وكم طالباً وكم تسجيلاً — ثم يقرّر صاحبها.
 *
 * ## وكلمات المرور تُنقَل كما هي
 *
 * هاش ووردبريس (phpass) لا يُفكّ، ولا يُطلب من مئتي طالب تغيير
 * كلمة مرورهم في يوم واحد. فتُنقل موسومةً `legacy_hash`، ويُعاد
 * تجزئتها بمعيارنا عند أول دخول ناجح — فينتقل الجميع بلا أن يشعر
 * أحد.
 */
final class WordPressImporter
{
    /** @var array<string, int> */
    private array $counts = [
        'courses' => 0, 'lessons' => 0, 'users' => 0,
        'enrollments' => 0, 'skipped' => 0,
    ];

    private ?PDO $db = null;

    private string $prefix = 'wp_';

    /**
     * @param  array{host:string, port:int, database:string, username:string, password:string, prefix:string}  $connection
     *
     * @throws RuntimeException
     */
    public function connect(array $connection): void
    {
        $this->prefix = $connection['prefix'] ?: 'wp_';

        try {
            $this->db = new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                    $connection['host'], $connection['port'], $connection['database']),
                $connection['username'],
                $connection['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10],
            );
        } catch (\PDOException $e) {
            throw new RuntimeException(__('تعذّر الاتصال بقاعدة ووردبريس: :error', [
                'error' => $e->getMessage(),
            ]));
        }
    }

    /**
     * ما سيقع لو نُفِّذ — بلا كتابة.
     *
     * @return array<string, int>
     */
    public function preview(): array
    {
        return [
            'courses' => $this->count("post_type = 'course' AND post_status = 'publish'"),
            'lessons' => $this->count("post_type IN ('lesson', 'unit') AND post_status = 'publish'"),
            'quizzes' => $this->count("post_type = 'quiz' AND post_status = 'publish'"),
            'users' => (int) $this->db->query("SELECT COUNT(*) FROM {$this->prefix}users")->fetchColumn(),
            'posts' => $this->count("post_type = 'post' AND post_status = 'publish'"),
        ];
    }

    /**
     * ينفّذ الاستيراد.
     *
     * @return array<string, int>
     */
    public function run(): array
    {
        $this->importUsers();
        $this->importCourses();

        return $this->counts;
    }

    /**
     * المستخدمون — بكلمات مرورهم كما هي.
     *
     * والمكرّر يُتخطّى لا يُستبدَل: من له حساب عندنا بالفعل قد يكون
     * غيّر بريده أو اسمه، واستيراد ووردبريس فوقه يمحو ما صحّحه.
     */
    private function importUsers(): void
    {
        $rows = $this->db->query(
            "SELECT ID, user_login, user_email, user_pass, display_name, user_registered
             FROM {$this->prefix}users ORDER BY ID"
        );

        foreach ($rows as $row) {
            $email = trim((string) $row['user_email']);

            if ($email === '' || User::where('email', $email)->exists()) {
                $this->counts['skipped']++;

                continue;
            }

            User::create([
                'name' => $row['display_name'] ?: $row['user_login'],
                'email' => $email,
                'password' => $row['user_pass'],
                'legacy_hash' => true,
                'role' => 'student',
                'status' => 'active',
                'email_verified_at' => now(),
                'wp_user_id' => (int) $row['ID'],
                'created_at' => $row['user_registered'] ?: now(),
            ]);

            $this->counts['users']++;
        }
    }

    private function importCourses(): void
    {
        $rows = $this->db->query(
            "SELECT ID, post_title, post_content, post_excerpt, post_name, post_date
             FROM {$this->prefix}posts
             WHERE post_type = 'course' AND post_status = 'publish' ORDER BY ID"
        );

        foreach ($rows as $row) {
            if (Course::where('wp_post_id', (int) $row['ID'])->exists()) {
                $this->counts['skipped']++;

                continue;
            }

            $course = Course::create([
                'slug' => $this->uniqueSlug($row['post_name'] ?: $row['post_title']),
                'title' => ['ar' => $row['post_title']],
                'excerpt' => ['ar' => Str::limit(strip_tags((string) $row['post_excerpt']), 300)],
                'description' => ['ar' => (string) $row['post_content']],
                'status' => 'published',
                'visibility' => 'public',
                'currency' => (string) (tenant('currency') ?? 'EGP'),
                'price_minor' => $this->meta((int) $row['ID'], 'vibe_course_price'),
                'wp_post_id' => (int) $row['ID'],
                'published_at' => $row['post_date'] ?: now(),
            ]);

            $this->counts['courses']++;

            $this->importCurriculum($course, (int) $row['ID']);
            $this->importEnrollments($course, (int) $row['ID']);
        }
    }

    /**
     * منهج الكورس.
     *
     * WPLMS يحفظ ترتيب الوحدات في `vibe_course_curriculum` مصفوفةً
     * مسلسَلة (serialized) تخلط الوحدات بعناوين الأقسام. وقراءتها
     * بـ`unserialize` على نصٍّ من قاعدةٍ خارجية خطرٌ معروف، فتُقرأ
     * بمُفكِّكٍ مقيَّد لا يبني كائنات.
     */
    private function importCurriculum(Course $course, int $wpCourseId): void
    {
        $raw = $this->metaRaw($wpCourseId, 'vibe_course_curriculum');

        $ids = $raw === null ? [] : $this->safeUnserialize($raw);

        if ($ids === []) {
            return;
        }

        $section = CourseSection::create([
            'course_id' => $course->getKey(),
            'title' => ['ar' => __('المحتوى')],
            'position' => 1,
        ]);

        $position = 0;

        foreach ($ids as $value) {
            // العناوين تأتي نصّاً بادئته `sec_` والوحدات أرقاماً
            if (! is_numeric($value)) {
                continue;
            }

            $post = $this->post((int) $value);

            if ($post === null) {
                continue;
            }

            $lesson = Lesson::create([
                'title' => ['ar' => $post['post_title']],
                'content' => ['ar' => (string) $post['post_content']],
                'type' => 'text',
                'wp_post_id' => (int) $post['ID'],
            ]);

            CourseItem::create([
                'course_id' => $course->getKey(),
                'section_id' => $section->getKey(),
                'position' => ++$position,
                'itemable_type' => Lesson::class,
                'itemable_id' => $lesson->getKey(),
            ]);

            $this->counts['lessons']++;
        }
    }

    /**
     * التسجيلات — من `usermeta` لا من جدول خاص.
     *
     * WPLMS يكتب `course_XX_status` في `usermeta` لكل طالب. وهي
     * الطريقة الوحيدة لمعرفة من كان مسجّلاً — وبدونها يفقد الطلبة
     * كورساتهم المدفوعة.
     */
    private function importEnrollments(Course $course, int $wpCourseId): void
    {
        $statement = $this->db->prepare(
            "SELECT user_id FROM {$this->prefix}usermeta WHERE meta_key = ?"
        );
        $statement->execute(["course_{$wpCourseId}_status"]);

        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $wpUserId) {
            $user = User::where('wp_user_id', (int) $wpUserId)->first();

            if ($user === null) {
                continue;
            }

            Enrollment::firstOrCreate(
                ['user_id' => $user->getKey(), 'course_id' => $course->getKey()],
                ['status' => 'active', 'progress_percent' => 0],
            );

            $this->counts['enrollments']++;
        }
    }

    /**
     * فكٌّ مقيَّد للنصّ المسلسَل.
     *
     * `unserialize` على نصٍّ من قاعدةٍ لا نملكها يسمح ببناء كائنات
     * تُنفّذ شيئاً عند إتلافها (POP chain). و`allowed_classes: false`
     * تمنع بناء أي كائن — والمصفوفات وحدها ما نحتاج.
     *
     * @return list<mixed>
     */
    private function safeUnserialize(string $raw): array
    {
        $value = @unserialize($raw, ['allowed_classes' => false]);

        return is_array($value) ? array_values($value) : [];
    }

    private function count(string $where): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM {$this->prefix}posts WHERE {$where}")->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    private function post(int $id): ?array
    {
        $statement = $this->db->prepare(
            "SELECT ID, post_title, post_content FROM {$this->prefix}posts WHERE ID = ? AND post_status = 'publish'"
        );
        $statement->execute([$id]);

        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function metaRaw(int $postId, string $key): ?string
    {
        $statement = $this->db->prepare(
            "SELECT meta_value FROM {$this->prefix}postmeta WHERE post_id = ? AND meta_key = ? LIMIT 1"
        );
        $statement->execute([$postId, $key]);

        $value = $statement->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    /** السعر بالقروش: ووردبريس يحفظه بالجنيه */
    private function meta(int $postId, string $key): int
    {
        $value = $this->metaRaw($postId, $key);

        return (int) round(((float) $value) * 100);
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'course';
        $slug = $base;
        $n = 1;

        while (Course::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }
}
