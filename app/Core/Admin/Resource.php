<?php

declare(strict_types=1);

namespace App\Core\Admin;

use App\Core\Access\Roles;
use App\Core\Admin\Columns\Column;
use App\Core\Admin\Fields\ChoicesField;
use App\Core\Admin\Fields\Field;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Filters\Filter;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * نواة الإدارة المدفوعة بالـ Schema (ADR-004).
 *
 * نعرّف المورد مرة واحدة في PHP، فتُولَّد شاشته بمكوّنات نظام
 * التصميم أُسُس — سرعة توليد بلا أي شبه بلوحة جاهزة، وتحكّم كامل
 * في كل بكسل لأن المصيِّر مكوّناتنا نحن.
 */
abstract class Resource
{
    /** @return class-string<Model> */
    abstract public function model(): string;

    /**
     * الصلاحية اللازمة لرؤية القائمة.
     *
     * مجرّدة عمداً بلا قيمة افتراضية: مورد جديد لا يُعلن حراسته لا
     * يُصرَّف أصلاً، فيستحيل أن يُضاف مورد مفتوح للجميع بالسهو.
     */
    abstract public function viewAbility(): string;

    /** الصلاحية اللازمة للإضافة والتعديل والحذف — الافتراضي هي نفسها. */
    public function manageAbility(): string
    {
        return $this->viewAbility();
    }

    /**
     * ميزة الباقة التي يتطلّبها هذا المورد كلّه.
     *
     * القفل كان في القائمة الجانبية وحدها، فيُفتح المسار بكتابته.
     * وهنا يُفحص في المتحكّم نفسه: قفلٌ أمنيّ لا بصريّ.
     */
    public function feature(): ?string
    {
        return null;
    }

    /**
     * مفتاح الحدّ الذي تستهلكه إضافةُ صفٍّ من هذا المورد.
     *
     * `null` تعني بلا حدّ — وهو الافتراضي عمداً، عكس `viewAbility`.
     * فالصلاحية إن نُسيت انفتح المورد للجميع، أمّا الحدّ إن نُسي
     * فأسوأ ما يقع أن يُسمح بما كان يُمنع: خطأٌ يُصحَّح بسطر، لا
     * ثغرةٌ تُستغَلّ.
     */
    public function quotaKey(Request $request): ?string
    {
        return null;
    }

    /**
     * حصر الاستعلام بما يملكه هذا المستخدم.
     *
     * الافتراضي «لا صفوف» للدور المحصور لا «كل الصفوف»: المورد الذي
     * ينسى حصر نطاقه يُخفي البيانات ولا يكشفها.
     */
    public function scopeFor(Builder $query, ?User $user): Builder
    {
        return app(Roles::class)->isScoped($user) ? $query->whereRaw('1 = 0') : $query;
    }

    /** @return list<Column> */
    abstract public function columns(): array;

    /** @return list<Filter> */
    public function filters(): array
    {
        return [];
    }

    /** @return list<Section> أقسام النموذج */
    public function form(): array
    {
        return [];
    }

    /** @return list<Field> كل حقول النموذج مسطّحة */
    public function fields(string $context = 'create'): array
    {
        $fields = [];

        foreach ($this->form() as $section) {
            foreach ($section->getFields() as $field) {
                if ($field->showsOn($context)) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    /** @return array<string, list<string>> قواعد التحقق مشتقّة من الحقول نفسها */
    public function validationRules(string $context, mixed $record = null): array
    {
        $rules = [];

        foreach ($this->fields($context) as $field) {
            $rules[$field->name] = $this->contextualise($field->validationRules($context), $record);
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function fillable(array $input, string $context): array
    {
        $data = [];

        foreach ($this->fields($context) as $field) {
            // مفتاح غائب: لا نلمس القيمة القائمة إطلاقاً
            if (! array_key_exists($field->name, $input)) {
                continue;
            }

            $value = $input[$field->name];

            // حقل يُترك فارغاً عمداً (كلمة المرور) لا يمحو القيمة القائمة
            if ($field->shouldSkipWhenEmpty() && ($value === null || $value === '')) {
                continue;
            }

            $data[$field->name] = $field->fill($value);

            /*
             | حقل الخيارات يملأ عمودين: الخيارات والصواب.
             |
             | فصلهما حقلين مستقلّين كان يسمح بحفظ إجابة صحيحة تشير
             | إلى خيار حُذف — فيصير السؤال بلا إجابة صحيحة أبداً.
             */
            if ($field instanceof ChoicesField) {
                $data['correct'] = ChoicesField::correctFrom($value);
            }
        }

        return $data;
    }

    public function canCreate(): bool
    {
        return $this->form() !== [];
    }

    /**
     * الرابط الذي يفتحه الصف الأول في الجدول.
     * الافتراضي شاشة التحرير؛ ومورد له شاشة تفصيلية خاصة يتجاوزه.
     */
    public function recordUrl(Model $record, string $key): ?string
    {
        return $this->canCreate()
            ? url('/admin/'.$key.'/'.$record->getKey().'/edit')
            : null;
    }

    /** @param  list<string>  $rules */
    private function contextualise(array $rules, mixed $record): array
    {
        return array_map(
            fn (string $rule): string => $record !== null && str_starts_with($rule, 'unique:')
                ? $rule.','.$record->getKey()
                : $rule,
            $rules,
        );
    }

    public function label(): string
    {
        return class_basename(static::class);
    }

    public function singularLabel(): string
    {
        return $this->label();
    }

    public function icon(): string
    {
        return '▦';
    }

    /** الهيكل الذي تُصيَّر داخله شاشات هذا المورد. */
    public function layout(): string
    {
        return 'layouts.admin';
    }

    public function perPage(): int
    {
        return 25;
    }

    public function defaultSort(): array
    {
        return ['id', 'desc'];
    }

    /** رسالة الحالة الفارغة — إلزامية لكل قائمة (وثيقة 13). */
    public function emptyState(): array
    {
        return [
            'title' => __('لا توجد سجلات بعد'),
            'body' => __('ستظهر السجلات هنا فور إضافة أولها.'),
        ];
    }

    public function query(): Builder
    {
        return $this->model()::query();
    }

    /**
     * الاستعلام كما يراه هذا المستخدم — وهو ما تستعمله كل الشاشات.
     *
     * يقبل أي مُصادَق: اللوحة العليا تُصادِق بنموذج آخر، ولا حصر
     * نطاق هناك لأن حارسها الخاص هو سلطتها.
     */
    public function queryFor(?Authenticatable $user): Builder
    {
        return $this->scopeFor($this->query(), $user instanceof User ? $user : null);
    }

    /** @return list<string> */
    public function searchableColumns(): array
    {
        return array_values(array_map(
            fn (Column $c): string => $c->name,
            array_filter($this->columns(), fn (Column $c): bool => $c->isSearchable()),
        ));
    }

    public function paginate(Request $request): LengthAwarePaginator
    {
        $query = $this->queryFor($request->user());

        $this->applySearch($query, trim((string) $request->query('q', '')));
        $this->applyFilters($query, $request);
        $this->applySort($query, $request);

        return $query
            ->paginate($this->perPage())
            ->withQueryString();
    }

    private function applySearch(Builder $query, string $term): void
    {
        $columns = $this->searchableColumns();

        if ($term === '' || $columns === []) {
            return;
        }

        $query->where(function (Builder $q) use ($columns, $term): void {
            foreach ($columns as $column) {
                $q->orWhere($column, 'like', '%'.$term.'%');
            }
        });
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        foreach ($this->filters() as $filter) {
            $filter->apply($query, $request->query($filter->name));
        }
    }

    /**
     * الترتيب مقصور على الأعمدة المعلَنة قابلةً للفرز،
     * فلا يصل اسم عمود من المستخدم إلى الاستعلام مباشرة.
     */
    private function applySort(Builder $query, Request $request): void
    {
        [$defaultColumn, $defaultDirection] = $this->defaultSort();

        $sortable = array_map(
            fn (Column $c): string => $c->name,
            array_filter($this->columns(), fn (Column $c): bool => $c->isSortable()),
        );

        $column = (string) $request->query('sort', $defaultColumn);
        $direction = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        if (! in_array($column, $sortable, true)) {
            [$column, $direction] = [$defaultColumn, $defaultDirection];
        }

        $query->orderBy($column, $direction);
    }
}
