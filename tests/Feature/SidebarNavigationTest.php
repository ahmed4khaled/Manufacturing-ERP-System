<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

it('renders the sidebar once in business priority order with ajax navigation', function () {
    $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);

    foreach (['statistics.view', 'accounting.view', 'users.view', 'roles.view', 'permissions.view'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user->givePermissionTo(Permission::query()->whereIn('name', [
        'statistics.view',
        'accounting.view',
        'users.view',
        'roles.view',
        'permissions.view',
    ])->get());

    $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $html = $response->getContent();

    expect(substr_count($html, 'id="statistics-nav"'))->toBe(1)
        ->and(substr_count($html, 'id="accounting-nav"'))->toBe(1)
        ->and(strpos($html, 'التشغيل اليومي'))->toBeLessThan(strpos($html, 'المخزون والبيانات'))
        ->and(strpos($html, 'المخزون والبيانات'))->toBeLessThan(strpos($html, 'المالية والرقابة'))
        ->and(strpos($html, 'المالية والرقابة'))->toBeLessThan(strpos($html, 'إدارة النظام'))
        ->and(strpos($html, 'href="'.route('suppliers.index').'"'))->toBeLessThan(strpos($html, 'href="'.route('supplier-invoices.index').'"'))
        ->and(strpos($html, 'href="'.route('supplier-invoices.index').'"'))->toBeLessThan(strpos($html, 'href="'.route('supplier-payments.index').'"'))
        ->and(strpos($html, 'href="'.route('supplier-payments.index').'"'))->toBeLessThan(strpos($html, 'href="'.route('purchase-returns.index').'"'))
        ->and(strpos($html, 'href="'.route('purchase-returns.index').'"'))->toBeLessThan(strpos($html, 'href="'.route('purchase-orders.index').'"'));

    $response
        ->assertSee('id="erpSidebarNav"', false)
        ->assertSee('id="erpContent"', false)
        ->assertSee('data-erp-nav', false)
        ->assertSee('fetch(url', false)
        ->assertSee("window.addEventListener('popstate'", false);
});
