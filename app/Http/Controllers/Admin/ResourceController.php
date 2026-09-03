<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Admin\Resource;
use App\Core\Admin\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * متحكّم واحد يخدم كل الموارد — الشاشة تُولَّد من تعريف المورد.
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

    private function resolve(string $key): Resource
    {
        // القائمة مغلقة: لا يصل اسم صنف من المستخدم إلى الحاوية
        $class = self::RESOURCES[$key] ?? throw new NotFoundHttpException("مورد غير معروف: [{$key}]");

        return app($class);
    }
}
