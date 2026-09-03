<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Access\Roles;
use App\Core\Admin\Resource;
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
    public function __construct(private readonly Roles $roles) {}

    public function index(Request $request, string $resource): View
    {
        $instance = $this->resolve($resource);
        $this->authorise($request, $instance->viewAbility());

        return view('admin.resource-index', [
            'resource' => $instance,
            'records' => $instance->paginate($request),
            'key' => $resource,
        ]);
    }

    public function create(Request $request, string $resource): View
    {
        $instance = $this->resolveCreatable($resource);
        $this->authorise($request, $instance->manageAbility());

        return view('admin.resource-form', [
            'resource' => $instance,
            'record' => null,
            'key' => $resource,
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $instance = $this->resolveCreatable($resource);
        $this->authorise($request, $instance->manageAbility());

        $validated = $request->validate($instance->validationRules('create'));

        $model = $instance->model()::create($instance->fillable($validated, 'create'));

        return redirect(url('/admin/'.$resource.'/'.$model->getKey().'/edit'))
            ->with('status', __('تمت الإضافة بنجاح.'));
    }

    public function edit(Request $request, string $resource, string $id): View
    {
        $instance = $this->resolveCreatable($resource);
        $this->authorise($request, $instance->manageAbility());

        return view('admin.resource-form', [
            'resource' => $instance,
            'record' => $this->findOrFail($instance, $id, $request),
            'key' => $resource,
        ]);
    }

    public function update(Request $request, string $resource, string $id): RedirectResponse
    {
        $instance = $this->resolveCreatable($resource);
        $this->authorise($request, $instance->manageAbility());

        $record = $this->findOrFail($instance, $id, $request);

        $validated = $request->validate($instance->validationRules('edit', $record));

        $record->update($instance->fillable($validated, 'edit'));

        return redirect(url('/admin/'.$resource.'/'.$record->getKey().'/edit'))
            ->with('status', __('تم حفظ التغييرات.'));
    }

    public function destroy(Request $request, string $resource, string $id): RedirectResponse
    {
        $instance = $this->resolveCreatable($resource);
        $this->authorise($request, $instance->manageAbility());

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
