<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccountingSeeder extends Seeder
{
    public const PERMISSIONS = [
        'accounting.view', 'accounting.create', 'accounting.update', 'accounting.post',
        'accounting.reverse', 'accounting.close_period', 'accounting.manage_accounts', 'accounting.reports',
        'expenses.view', 'expenses.create', 'expenses.update', 'expenses.reverse',
        'wages.view', 'wages.create', 'wages.update', 'wages.pay', 'wages.reverse',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $rows = [
            ['1000', 'الأصول', 'asset', null, false, null],
            ['1100', 'الأصول المتداولة', 'asset', '1000', false, null],
            ['1110', 'الخزينة', 'asset', '1100', true, 'cash'],
            ['1120', 'البنك', 'asset', '1100', true, 'bank'],
            ['1130', 'العملاء', 'asset', '1100', true, 'accounts_receivable'],
            ['1140', 'مخزون الخامات', 'asset', '1100', true, 'raw_material_inventory'],
            ['1150', 'مخزون المنتجات التامة', 'asset', '1100', true, 'finished_goods_inventory'],
            ['1160', 'إنتاج تحت التشغيل', 'asset', '1100', true, 'work_in_progress'],
            ['2000', 'الخصوم', 'liability', null, false, null],
            ['2100', 'الموردون', 'liability', '2000', true, 'accounts_payable'],
            ['2200', 'تكاليف تصنيع مستحقة', 'liability', '2000', true, 'manufacturing_costs_payable'],
            ['2210', 'أجور إنتاج مستحقة', 'liability', '2000', true, 'production_wages_payable'],
            ['3000', 'حقوق الملكية', 'equity', null, false, null],
            ['3100', 'رأس المال', 'equity', '3000', true, 'partner_capital'],
            ['3200', 'جاري الشركاء', 'equity', '3000', true, 'partner_current'],
            ['3300', 'مسحوبات الشركاء', 'equity', '3000', true, 'partner_withdrawals'],
            ['3400', 'أرباح محتجزة', 'equity', '3000', true, 'retained_earnings'],
            ['4000', 'الإيرادات', 'revenue', null, false, null],
            ['4100', 'مبيعات المنتجات', 'revenue', '4000', true, 'sales_revenue'],
            ['4200', 'مردودات المبيعات', 'revenue', '4000', true, 'sales_returns'],
            ['5000', 'تكلفة المبيعات', 'expense', null, false, null],
            ['5100', 'تكلفة البضاعة المباعة', 'expense', '5000', true, 'cost_of_goods_sold'],
            ['6000', 'المصروفات', 'expense', null, false, null],
            ['6100', 'مصروفات تشغيل', 'expense', '6000', true, 'operating_expenses'],
            ['6200', 'أجور', 'expense', '6000', true, 'wages_expense'],
            ['6300', 'كهرباء', 'expense', '6000', true, 'electricity_expense'],
            ['6310', 'مياه', 'expense', '6000', true, 'water_expense'],
            ['6320', 'إنترنت واتصالات', 'expense', '6000', true, 'internet_expense'],
            ['6330', 'نظافة', 'expense', '6000', true, 'cleaning_expense'],
            ['6340', 'صيانة', 'expense', '6000', true, 'maintenance_expense'],
            ['6350', 'نقل', 'expense', '6000', true, 'transport_expense'],
            ['6360', 'أدوات مكتبية', 'expense', '6000', true, 'office_supplies_expense'],
            ['6370', 'مصروفات إدارية', 'expense', '6000', true, 'administrative_expense'],
            ['6400', 'إيجار', 'expense', '6000', true, 'rent_expense'],
            ['6500', 'مصروفات أخرى', 'expense', '6000', true, 'other_expenses'],
            ['6600', 'فروق وتسويات المخزون', 'expense', '6000', true, 'inventory_adjustment'],
        ];

        foreach ($rows as [$code, $name, $type, $parentCode, $posting, $mapping]) {
            $parentId = $parentCode ? Account::query()->where('code', $parentCode)->value('id') : null;
            $account = Account::query()->firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'type' => $type, 'subtype' => $mapping, 'parent_id' => $parentId, 'is_active' => true, 'allow_posting' => $posting],
            );
            if ($mapping ?? null) {
                if (! $account->subtype) {
                    $account->update(['subtype' => $mapping]);
                }
                AccountMapping::query()->updateOrCreate(['key' => $mapping], ['account_id' => $account->id]);
            }
        }

        $wagePermissionsByRole = [
            'Admin' => ['wages.view', 'wages.create', 'wages.update', 'wages.pay', 'wages.reverse'],
            'Accountant' => ['wages.view', 'wages.pay', 'wages.reverse'],
            'Manufacturing' => ['wages.view'],
            'Production Supervisor' => ['wages.view', 'wages.create', 'wages.update'],
        ];
        foreach ($wagePermissionsByRole as $roleName => $permissions) {
            Role::query()->where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $superAdmin = User::query()->where('email', User::SUPER_ADMIN_EMAIL)->first();
        if ($superAdmin) {
            $role = Role::findOrCreate('Super Admin', 'web');
            $role->givePermissionTo(self::PERMISSIONS);
            $superAdmin->assignRole($role);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
