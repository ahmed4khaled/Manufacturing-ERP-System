<?php

namespace App\Support;

use Illuminate\Support\Collection;

class PermissionGroups
{
    /** @var array<string, string> */
    private const LABELS = [
        'customers' => 'العملاء',
        'customer_ledger' => 'كشف حساب العملاء',
        'sales_invoices' => 'فواتير المبيعات',
        'sales_returns' => 'مرتجعات المبيعات',
        'payment_receipts' => 'سندات القبض',
        'suppliers' => 'الموردون',
        'purchase_orders' => 'أوامر الشراء',
        'goods_receipts' => 'استلام الخامات',
        'supplier_invoices' => 'فواتير الموردين',
        'supplier_payments' => 'مدفوعات الموردين',
        'purchase_returns' => 'مرتجعات المشتريات',
        'materials' => 'الخامات',
        'material_inventory' => 'مخزون الخامات',
        'inventory' => 'مخزون المنتجات',
        'categories' => 'التصنيفات',
        'colors' => 'الألوان',
        'sizes' => 'المقاسات',
        'products' => 'الموديلات',
        'product_variants' => 'متغيرات المنتجات والباركود',
        'workers' => 'العمال',
        'manufacturing_operations' => 'عمليات التصنيع',
        'boms' => 'قوائم المواد BOM',
        'production_orders' => 'أوامر الإنتاج',
        'production_reports' => 'تقارير الإنتاج',
        'statistics' => 'الإحصائيات والتقارير',
        'users' => 'المستخدمون',
        'roles' => 'الأدوار',
        'permissions' => 'الصلاحيات',
        'expenses' => 'المصروفات السريعة',
        'wages' => 'أجور العاملين',
    ];

    /**
     * @template TKey of array-key
     * @template TValue of object
     *
     * @param  Collection<TKey, TValue>  $permissions
     * @return Collection<string, Collection<TKey, TValue>>
     */
    public static function group(Collection $permissions): Collection
    {
        return $permissions
            ->sortBy('name')
            ->groupBy(fn ($permission): string => str($permission->name)->before('.')->toString())
            ->mapWithKeys(fn (Collection $items, string $module): array => [
                self::LABELS[$module] ?? $module => $items,
            ]);
    }
}
