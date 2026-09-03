<?php

declare(strict_types=1);

namespace App\Core\Admin;

use App\Core\Admin\Columns\Column;
use App\Core\Admin\Fields\Field;
use App\Core\Admin\Fields\Section;
use App\Core\Admin\Filters\Filter;
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
        }

        return $data;
    }

    public function canCreate(): bool
    {
        return $this->form() !== [];
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
        $query = $this->query();

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
