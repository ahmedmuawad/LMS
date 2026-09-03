<?php

declare(strict_types=1);

namespace App\Core\Billing\Actions;

use App\Core\Billing\InvoiceNumber;
use App\Core\Billing\Models\Invoice;
use App\Core\Billing\Models\Subscription;
use App\Core\Support\Money;

/**
 * إصدار فاتورة دورة اشتراك.
 *
 * البنود وبيانات الفوترة تُجمَّد نسخةً داخل الفاتورة: تغيير اسم
 * الباقة أو عنوان المشترك لاحقاً لا يجوز أن يغيّر فاتورة صدرت.
 */
final class IssueInvoice
{
    public function handle(Subscription $subscription, ?float $taxRate = null, int $dueInDays = 7): Invoice
    {
        $tenant = $subscription->tenant;
        $amount = $subscription->amount();
        $taxRate ??= 0.0;

        $tax = $taxRate > 0 ? $amount->percentage($taxRate) : Money::zero($subscription->currency);
        $total = $amount->plus($tax);

        $planName = $subscription->plan?->name ?? ['ar' => $subscription->plan_key];

        return Invoice::create([
            'number' => InvoiceNumber::next(),
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'status' => 'open',
            'currency' => $subscription->currency,
            'subtotal_minor' => $amount->minor,
            'tax_minor' => $tax->minor,
            'total_minor' => $total->minor,
            'tax_rate' => $taxRate,
            'tax_label' => $taxRate > 0 ? __('ضريبة القيمة المضافة') : null,
            'lines' => [[
                'title' => $planName,
                'description' => __('اشتراك :interval', [
                    'interval' => $subscription->interval === 'year' ? __('سنوي') : __('شهري'),
                ]),
                'quantity' => 1,
                'unit_minor' => $amount->minor,
                'total_minor' => $amount->minor,
            ]],
            'billing_details' => [
                'name' => $tenant?->name,
                'email' => $tenant?->owner_email,
                'country' => $tenant?->country,
            ],
            'period_start' => $subscription->current_period_start,
            'period_end' => $subscription->current_period_end,
            'issued_at' => now(),
            'due_at' => now()->addDays($dueInDays),
        ]);
    }
}
