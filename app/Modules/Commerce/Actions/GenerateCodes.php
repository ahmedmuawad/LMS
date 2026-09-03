<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Actions;

use App\Models\User;
use App\Modules\Commerce\Models\RechargeBatch;
use App\Modules\Commerce\Models\RechargeCode;
use Illuminate\Support\Facades\DB;

/** توليد دفعة أكواد للطباعة. */
final class GenerateCodes
{
    /** @param  array<string, mixed>  $input */
    public function handle(array $input, ?User $creator = null): RechargeBatch
    {
        $quantity = max(1, min(10000, (int) $input['quantity']));

        $batch = RechargeBatch::create([
            'name' => $input['name'],
            'quantity' => $quantity,
            'type' => $input['type'] ?? 'wallet',
            'currency' => $input['currency'] ?? (string) (tenant('currency') ?? 'EGP'),
            'value_minor' => (int) ($input['value_minor'] ?? 0),
            'course_id' => $input['course_id'] ?? null,
            'expires_at' => $input['expires_at'] ?? null,
            'created_by' => $creator?->getKey(),
        ]);

        DB::transaction(function () use ($batch, $quantity): void {
            $rows = [];
            $seen = [];

            // نُولّد ثم نُدخل دفعةً واحدة: عشرة آلاف صفّ لا تُدخل صفاً صفاً
            while (count($rows) < $quantity) {
                $code = RechargeCode::generate();

                if (isset($seen[$code]) || RechargeCode::where('code', $code)->exists()) {
                    continue;
                }

                $seen[$code] = true;

                $rows[] = [
                    'code' => $code,
                    'batch_id' => $batch->getKey(),
                    'type' => $batch->type,
                    'currency' => $batch->currency,
                    'value_minor' => $batch->value_minor,
                    'course_id' => $batch->course_id,
                    'status' => 'unused',
                    'expires_at' => $batch->expires_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                RechargeCode::insert($chunk);
            }
        });

        return $batch->refresh();
    }
}
