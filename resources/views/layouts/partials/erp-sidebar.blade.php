@php
    $operationalGroups = [
        [
            'id' => 'sales-nav',
            'label' => 'المبيعات والتحصيل',
            'icon' => 'bi-receipt-cutoff',
            'patterns' => ['sales-invoices.*', 'payment-receipts.*', 'sales-returns.*', 'customer-ledger.*', 'customers.*'],
            'items' => [
                ['sales-invoices.index', ['sales-invoices.*'], 'bi-receipt', 'فواتير المبيعات'],
                ['payment-receipts.index', ['payment-receipts.*'], 'bi-cash-coin', 'سندات القبض'],
                ['sales-returns.index', ['sales-returns.*'], 'bi-arrow-return-right', 'مرتجعات المبيعات'],
                ['customer-ledger.index', ['customer-ledger.*'], 'bi-journal-text', 'كشف حساب العملاء'],
                ['customers.index', ['customers.*'], 'bi-people', 'العملاء'],
            ],
        ],
        [
            'id' => 'purchases-nav',
            'label' => 'المشتريات والموردون',
            'icon' => 'bi-cart-check',
            'patterns' => ['purchase-orders.*', 'goods-receipts.*', 'supplier-invoices.*', 'supplier-payments.*', 'purchase-returns.*', 'suppliers.*'],
            'items' => [
                ['suppliers.index', ['suppliers.*'], 'bi-truck', 'الموردون'],
                ['supplier-invoices.index', ['supplier-invoices.*'], 'bi-receipt', 'فواتير الموردين'],
                ['supplier-payments.index', ['supplier-payments.*'], 'bi-cash-stack', 'مدفوعات الموردين'],
                ['purchase-returns.index', ['purchase-returns.*'], 'bi-arrow-return-left', 'مرتجعات المشتريات'],
                ['purchase-orders.index', ['purchase-orders.*'], 'bi-file-earmark-text', 'أوامر الشراء'],
                ['goods-receipts.index', ['goods-receipts.*'], 'bi-box-arrow-in-down', 'الاستلامات'],
            ],
        ],
        [
            'id' => 'manufacturing-nav',
            'label' => 'التصنيع والإنتاج',
            'icon' => 'bi-gear-wide-connected',
            'patterns' => ['production-orders.*', 'work-assignments.*', 'production-defects.*', 'production-reworks.*', 'manufacturing-operations.*', 'boms.*', 'workers.*', 'worker-wages.*', 'production-reports.*'],
            'items' => [
                ['production-orders.index', ['production-orders.*', 'work-assignments.*', 'production-defects.*', 'production-reworks.*'], 'bi-gear-wide-connected', 'أوامر الإنتاج'],
                ['manufacturing-operations.index', ['manufacturing-operations.*'], 'bi-list-ol', 'عمليات التصنيع'],
                ['boms.index', ['boms.*'], 'bi-diagram-3', 'قوائم المواد BOM'],
                ['workers.index', ['workers.*'], 'bi-person-workspace', 'العمال'],
                ['worker-wages.index', ['worker-wages.*'], 'bi-cash-coin', 'أجور العاملين', 'wages.view'],
                ['production-reports.material-variance', ['production-reports.*'], 'bi-graph-up', 'انحراف الخامات'],
            ],
        ],
    ];

    $inventoryGroups = [
        [
            'id' => 'inventory-nav',
            'label' => 'المخزون',
            'icon' => 'bi-boxes',
            'patterns' => ['inventory.*', 'material-inventory.*'],
            'items' => [
                ['inventory.index', ['inventory.index', 'inventory.show'], 'bi-boxes', 'أرصدة المنتجات'],
                ['material-inventory.index', ['material-inventory.index', 'material-inventory.show'], 'bi-archive', 'أرصدة الخامات'],
                ['inventory.movements', ['inventory.movements'], 'bi-arrow-left-right', 'حركات المنتجات'],
                ['material-inventory.movements', ['material-inventory.movements'], 'bi-arrow-left-right', 'حركات الخامات'],
                ['inventory.cost-layers.index', ['inventory.cost-layers.*'], 'bi-layers-half', 'تكلفة المنتجات FIFO', 'inventory.view'],
                ['material-inventory.cost-layers.index', ['material-inventory.cost-layers.*'], 'bi-layers-half', 'تكلفة الخامات FIFO', 'material_inventory.view'],
                ['inventory.adjustments.create', ['inventory.adjustments.*'], 'bi-sliders', 'تسويات المنتجات'],
                ['material-inventory.adjustments.create', ['material-inventory.adjustments.*'], 'bi-sliders', 'تسويات الخامات'],
                ['inventory.opening-stock.create', ['inventory.opening-stock.*'], 'bi-box-arrow-in-down', 'افتتاحي المنتجات'],
                ['material-inventory.opening-stock.create', ['material-inventory.opening-stock.*'], 'bi-box-arrow-in-down', 'افتتاحي الخامات'],
            ],
        ],
        [
            'id' => 'master-data-nav',
            'label' => 'البيانات الأساسية',
            'icon' => 'bi-database-gear',
            'patterns' => ['products.*', 'product-variants.*', 'categories.*', 'colors.*', 'sizes.*', 'materials.*'],
            'items' => [
                ['products.index', ['products.*'], 'bi-box-seam', 'الموديلات'],
                ['product-variants.index', ['product-variants.*'], 'bi-layers', 'متغيرات المنتجات'],
                ['categories.index', ['categories.*'], 'bi-tags', 'التصنيفات'],
                ['colors.index', ['colors.*'], 'bi-palette', 'الألوان'],
                ['sizes.index', ['sizes.*'], 'bi-rulers', 'المقاسات'],
                ['materials.index', ['materials.*'], 'bi-box-seam', 'دليل الخامات'],
            ],
        ],
    ];
@endphp

<div class="nav-section">الرئيسية</div>
<a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" data-erp-nav>
    <i class="bi bi-speedometer2"></i>
    <span>لوحة التحكم</span>
</a>

<div class="nav-section">التشغيل اليومي</div>
@foreach ($operationalGroups as $group)
    @php($groupActive = request()->routeIs(...$group['patterns']))
    <div class="nav-module">
        <button class="nav-link nav-toggle {{ $groupActive ? 'active' : '' }}"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $group['id'] }}"
                aria-expanded="{{ $groupActive ? 'true' : 'false' }}"
                aria-controls="{{ $group['id'] }}">
            <i class="bi {{ $group['icon'] }}"></i>
            <span>{{ $group['label'] }}</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </button>
        <div class="collapse {{ $groupActive ? 'show' : '' }} nav-children" id="{{ $group['id'] }}">
            @foreach ($group['items'] as [$routeName, $patterns, $icon, $label])
                <a href="{{ route($routeName) }}" class="nav-link sub-link {{ request()->routeIs(...$patterns) ? 'active' : '' }}" data-erp-nav>
                    <i class="bi {{ $icon }}"></i>
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </div>
@endforeach

<div class="nav-section">المخزون والبيانات</div>
@foreach ($inventoryGroups as $group)
    @php($groupActive = request()->routeIs(...$group['patterns']))
    <div class="nav-module">
        <button class="nav-link nav-toggle {{ $groupActive ? 'active' : '' }}"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $group['id'] }}"
                aria-expanded="{{ $groupActive ? 'true' : 'false' }}"
                aria-controls="{{ $group['id'] }}">
            <i class="bi {{ $group['icon'] }}"></i>
            <span>{{ $group['label'] }}</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </button>
        <div class="collapse {{ $groupActive ? 'show' : '' }} nav-children" id="{{ $group['id'] }}">
            @foreach ($group['items'] as $item)
                @php([$routeName, $patterns, $icon, $label, $permission] = array_pad($item, 5, null))
                @continue($permission && ! auth()->user()->can($permission))
                <a href="{{ route($routeName) }}" class="nav-link sub-link {{ request()->routeIs(...$patterns) ? 'active' : '' }}" data-erp-nav>
                    <i class="bi {{ $icon }}"></i>
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </div>
@endforeach

<div class="nav-section">المالية والرقابة</div>
@canany(['expenses.view', 'expenses.create', 'accounting.view', 'accounting.post'])
    @php($expensesActive = request()->routeIs('accounting.expenses*'))
    <div class="nav-module">
        <button class="nav-link nav-toggle {{ $expensesActive ? 'active' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#expenses-nav" aria-expanded="{{ $expensesActive ? 'true' : 'false' }}" aria-controls="expenses-nav">
            <i class="bi bi-cash-stack"></i>
            <span>المصروفات</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </button>
        <div class="collapse {{ $expensesActive ? 'show' : '' }} nav-children" id="expenses-nav">
            @canany(['expenses.create', 'accounting.post'])
                <a href="{{ route('accounting.expenses.create') }}" class="nav-link sub-link {{ request()->routeIs('accounting.expenses.create') ? 'active' : '' }}" data-erp-nav>
                    <i class="bi bi-plus-circle"></i><span>تسجيل مصروف</span>
                </a>
            @endcanany
            @canany(['expenses.view', 'accounting.view'])
                <a href="{{ route('accounting.expenses') }}" class="nav-link sub-link {{ request()->routeIs('accounting.expenses', 'accounting.expenses.show') ? 'active' : '' }}" data-erp-nav>
                    <i class="bi bi-list-ul"></i><span>سجل المصروفات</span>
                </a>
            @endcanany
        </div>
    </div>
@endcanany

@canany(['accounting.view', 'accounting.reports', 'cutover.view'])
    @php($accountingActive = (request()->routeIs('accounting.*') && ! request()->routeIs('accounting.expenses*')) || request()->routeIs('cutover.*'))
    <div class="nav-module">
        <button class="nav-link nav-toggle {{ $accountingActive ? 'active' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#accounting-nav" aria-expanded="{{ $accountingActive ? 'true' : 'false' }}" aria-controls="accounting-nav">
            <i class="bi bi-calculator"></i>
            <span>المحاسبة</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </button>
        <div class="collapse {{ $accountingActive ? 'show' : '' }} nav-children" id="accounting-nav">
            @can('cutover.view')
                <a href="{{ route('cutover.index') }}" class="nav-link sub-link {{ request()->routeIs('cutover.*') ? 'active' : '' }}" data-erp-nav><i class="bi bi-box-arrow-in-right"></i><span>الأرصدة الافتتاحية</span></a>
            @endcan
            @foreach ([
                ['accounting.journals', 'bi-journal-text', 'القيود اليومية'],
                ['accounting.cash-bank', 'bi-bank', 'الخزينة والبنوك'],
                ['accounting.customer-balances', 'bi-people', 'حسابات العملاء'],
                ['accounting.supplier-balances', 'bi-truck', 'حسابات الموردين'],
                ['accounting.ledger', 'bi-book', 'دفتر الأستاذ'],
                ['accounting.trial-balance', 'bi-list-check', 'ميزان المراجعة'],
                ['accounting.profit-loss', 'bi-graph-up-arrow', 'الأرباح والخسائر'],
                ['accounting.balance-sheet', 'bi-columns-gap', 'الميزانية العمومية'],
                ['accounting.inventory-valuation', 'bi-boxes', 'تقييم المخزون'],
                ['accounting.cogs', 'bi-graph-down-arrow', 'تكلفة المبيعات FIFO'],
                ['accounting.accounts', 'bi-diagram-3', 'دليل الحسابات'],
                ['accounting.periods', 'bi-calendar-range', 'الفترات المالية'],
                ['accounting.partners', 'bi-person-badge', 'حسابات الشركاء'],
            ] as [$routeName, $icon, $label])
                <a href="{{ route($routeName) }}" class="nav-link sub-link {{ request()->routeIs($routeName) ? 'active' : '' }}" data-erp-nav>
                    <i class="bi {{ $icon }}"></i>
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </div>
@endcanany

@can('statistics.view')
    @php($statisticsActive = request()->routeIs('statistics.*'))
    <div class="nav-module">
        <button class="nav-link nav-toggle {{ $statisticsActive ? 'active' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#statistics-nav" aria-expanded="{{ $statisticsActive ? 'true' : 'false' }}" aria-controls="statistics-nav">
            <i class="bi bi-bar-chart-line"></i>
            <span>الإحصائيات والتقارير</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </button>
        <div class="collapse {{ $statisticsActive ? 'show' : '' }} nav-children" id="statistics-nav">
            @foreach ([
                ['statistics.dashboard', 'bi-speedometer2', 'لوحة الإحصائيات'],
                ['statistics.sales', 'bi-receipt', 'المبيعات'],
                ['statistics.collections', 'bi-cash-coin', 'التحصيلات'],
                ['statistics.purchases', 'bi-cart-check', 'المشتريات'],
                ['statistics.inventory', 'bi-boxes', 'مخزون المنتجات'],
                ['statistics.materials', 'bi-box-seam', 'مخزون الخامات'],
                ['statistics.manufacturing', 'bi-gear-wide-connected', 'التصنيع'],
                ['statistics.customers', 'bi-people', 'العملاء'],
                ['statistics.workers', 'bi-person-workspace', 'العمال'],
                ['statistics.defects', 'bi-bandaid', 'العيوب والهالك'],
                ['statistics.returns', 'bi-arrow-return-right', 'المرتجعات'],
            ] as [$routeName, $icon, $label])
                <a href="{{ route($routeName) }}" class="nav-link sub-link {{ request()->routeIs($routeName) ? 'active' : '' }}" data-erp-nav>
                    <i class="bi {{ $icon }}"></i>
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </div>
@endcan

@canany(['users.view', 'roles.view', 'permissions.view'])
    <div class="nav-section">إدارة النظام</div>
    @can('users.view')
        <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" data-erp-nav><i class="bi bi-people-fill"></i><span>المستخدمون</span></a>
    @endcan
    @can('roles.view')
        <a href="{{ route('roles.index') }}" class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}" data-erp-nav><i class="bi bi-person-badge"></i><span>الأدوار</span></a>
    @endcan
    @can('permissions.view')
        <a href="{{ route('permissions.index') }}" class="nav-link {{ request()->routeIs('permissions.*') ? 'active' : '' }}" data-erp-nav><i class="bi bi-shield-check"></i><span>الصلاحيات</span></a>
    @endcan
@endcanany

<div class="sidebar-footer-note">
    <i class="bi bi-lightning-charge-fill"></i>
    <span>تنقل سريع بدون إعادة تحميل</span>
</div>
