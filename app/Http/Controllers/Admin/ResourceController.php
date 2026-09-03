<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Admin\Resource;
use App\Core\Admin\Resources\UserResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * متحكّم واحد يخدم كل الموارد — الشاشات تُولَّد من تعريف المورد.
 */
final class ResourceController
{
    /** @var array<string, class-string<resource>> */
    private const RESOURCES = [
        'users' => UserResource::class,
    ];

    public function index(Request $request, string $resource): View
    {
        $instance = $this->resolve($resource);

        return view('admin.resource-index', [
            'resource' => $instance,
            'records' => $instance->paginate($request),
            'key' => $resource,
        ]);
    }

    public function create(string $resource): View
    {
        $instance = $this->resolveCreatable($resource);

        return view('admin.resource-form', [
            'resource' => $instance,
            'record' => null,
            'key' => $resource,
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $instance = $this->resolveCreatable($resource);

        $validated = $request->validate($instance->validationRules('create'));

        $model = $instance->model()::create($instance->fillable($validated, 'create'));

        return redirect(url('/admin/'.$resource.'/'.$model->getKey().'/edit'))
            ->with('status', __('تمت الإضافة بنجاح.'));
    }

    public function edit(string $resource, string $id): View
    {
        $instance = $this->resolveCreatable($resource);

        return view('admin.resource-form', [
            'resource' => $instance,
            'record' => $this->findOrFail($instance, $id),
            'key' => $resource,
        ]);
    }

    public function update(Request $request, string $resource, string $id): RedirectResponse
    {
        $instance = $this->resolveCreatable($resource);
        $record = $this->findOrFail($instance, $id);

        $validated = $request->validate($instance->validationRules('edit', $record));

        $record->update($instance->fillable($validated, 'edit'));

        return redirect(url('/admin/'.$resource.'/'.$record->getKey().'/edit'))
            ->with('status', __('تم حفظ التغييرات.'));
    }

    public function destroy(string $resource, string $id): RedirectResponse
    {
        $instance = $this->resolveCreatable($resource);

        $this->findOrFail($instance, $id)->delete();

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

    private function findOrFail(Resource $resource, string $id): Model
    {
        return $resource->query()->findOrFail($id);
    }
}
