<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Access\Roles;
use App\Core\Admin\Resource;
use App\Core\Entitlements\Quota;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * متحكّم واحد يخدم كل الموارد — الشاشات تُولَّد من تعريف المورد.
 */
final class ResourceController
{
    public function __construct(
        private readonly Roles $roles,
        private readonly Quota $quota,
    ) {}

    public function index(Request $request, string $resource): View
    {
        $instance = $this->resolve($resource);
        $this->guard($request, $instance, $instance->viewAbility());

        return view('admin.resource-index', [
            'resource' => $instance,
            'records' => $instance->paginate($request),
            'key' => $resource,
        ]);
    }

    public function create(Request $request, string $resource): View
    {
        $instance = $this->resolveCreatable($resource);
        $this->guard($request, $instance, $instance->manageAbility());

        return view('admin.resource-form', [
            'resource' => $instance,
            'record' => null,
            'key' => $resource,
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $instance = $this->resolveCreatable($resource);
        $this->guard($request, $instance, $instance->manageAbility());

        /*
         | الحدّ يُفحص هنا لا في كل مورد على حدة.
         |
         | هذه هي نقطة الاختناق الوحيدة لإنشاء أي صفٍّ في اللوحة، ففحصٌ
         | واحد هنا يغطّي الموارد الستّة والثلاثين — ومورد جديد ينسى
         | حدّه لن يُنشئ ثغرة، لأن `quotaKey()` تُعلَن حيث تُعرَف.
         |
         | والفحص قبل التحقّق من المدخلات: من بلغ حدّه لا يُطالَب
         | بتصحيح نموذجٍ لن يُقبل على أي حال.
         */
        if (($quota = $instance->quotaKey($request)) !== null) {
            $this->quota->enforce($quota);
        }

        $validated = $request->validate($instance->validationRules('create'));

        $model = $instance->model()::create($instance->fillable($validated, 'create'));

        $instance->syncRelations($model, $validated);

        // الخطوة التالية إن كان للسجلّ واحدة: النموذج ليس نهاية الطريق
        $next = $instance->nextStep($model, $resource);

        return redirect($next['url'] ?? url('/admin/'.$resource.'/'.$model->getKey().'/edit'))
            ->with('status', $next === null
                ? __('تمت الإضافة بنجاح.')
                : __('تمت الإضافة. :hint', ['hint' => $next['hint']]));
    }

    public function edit(Request $request, string $resource, string $id): View
    {
        $instance = $this->resolveCreatable($resource);
        $this->guard($request, $instance, $instance->manageAbility());

        return view('admin.resource-form', [
            'resource' => $instance,
            'record' => $this->findOrFail($instance, $id, $request),
            'key' => $resource,
        ]);
    }

    public function update(Request $request, string $resource, string $id): RedirectResponse
    {
        $instance = $this->resolveCreatable($resource);
        $this->guard($request, $instance, $instance->manageAbility());

        $record = $this->findOrFail($instance, $id, $request);

        $validated = $request->validate($instance->validationRules('edit', $record));

        $record->update($instance->fillable($validated, 'edit'));

        /*
         | العلاقات تُربَط بعد الحفظ.
         |
         | حقول النموذج تُكتب أعمدةً، والعلاقة متعدّدة-إلى-متعدّدة
         | جدولٌ ثالث لا عمود. وموردٌ يحتاجها يُعلنها في `syncRelations`
         | بدل أن يُنشئ لها شاشةً ثانية يفتحها المستخدم ولا يعرف لماذا.
         */
        $instance->syncRelations($record, $validated);

        return redirect(url('/admin/'.$resource.'/'.$record->getKey().'/edit'))
            ->with('status', __('تم حفظ التغييرات.'));
    }

    public function destroy(Request $request, string $resource, string $id): RedirectResponse
    {
        $instance = $this->resolveCreatable($resource);
        $this->guard($request, $instance, $instance->manageAbility());

        $this->findOrFail($instance, $id, $request)->delete();

        return redirect(url('/admin/'.$resource))->with('status', __('تم الحذف.'));
    }

    private function resolve(string $key): Resource
    {
        // القائمة مغلقة: لا يصل اسم صنف من المستخدم إلى الحاوية.
        // السياق يحدّد الخريطة: قاعدة المشترك أم القاعدة المركزية.
        $context = tenancy()->initialized ? 'tenant' : 'central';

        $class = config("admin-resources.{$context}.{$key}")
            ?? throw new NotFoundHttpException("مورد غير معروف في سياق [{$context}]: [{$key}]");

        return app($class);
    }

    private function resolveCreatable(string $key): Resource
    {
        $instance = $this->resolve($key);

        if (! $instance->canCreate()) {
            throw new NotFoundHttpException("المورد [{$key}] لا يعرّف نموذجاً.");
        }

        return $instance;
    }

    /**
     * السجلّ من داخل نطاق المستخدم لا من الجدول كلّه.
     *
     * صفٌّ خارج نطاقه يعود 404 لا 403: الثاني يُخبر المتطفّل أن
     * الصفّ موجود، والأول لا يُخبره بشيء.
     */
    private function findOrFail(Resource $resource, string $id, Request $request): Model
    {
        return $resource->queryFor($request->user())->findOrFail($id);
    }

    /**
     * ميزة المورد تُفحص مع صلاحيته: كلاهما شرطٌ للدخول.
     *
     * جُمعا في دالة واحدة عمداً — فمن يضيف فعلاً جديداً للمتحكّم
     * يستدعي حارساً واحداً، ولا ينسى نصفه.
     */
    private function guard(Request $request, Resource $instance, string $ability): void
    {
        $feature = $instance->feature();

        if ($feature !== null && tenant() !== null && ! tenant()->allows($feature)) {
            abort(402, __('هذه الميزة غير متاحة في باقتك.'));
        }

        $this->authorise($request, $ability);
    }

    private function authorise(Request $request, string $ability): void
    {
        /*
         | اللوحة العليا يحرسها `EnsureSuperAdmin` بمستخدم من نموذج
         | آخر وحارس آخر؛ ومصفوفة أدوار المشترك لا تنطبق عليه، فلو
         | طُبّقت لأغلقت لوحتنا نحن على أنفسنا.
         */
        if (! tenancy()->initialized) {
            return;
        }

        if (! $this->roles->allows($request->user(), $ability)) {
            throw new AccessDeniedHttpException(__('لا تملك صلاحية على هذا المورد.'));
        }
    }
}
