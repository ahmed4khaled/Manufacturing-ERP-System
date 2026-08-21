<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'نظام إدارة مصنع العبايات'))</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=tajawal:400,500,600,700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 260px;
            --navbar-height: 60px;
            --sidebar-bg: #1e293b;
            --sidebar-hover: #334155;
            --accent: #6366f1;
        }

        body {
            font-family: 'Tajawal', sans-serif;
            background-color: #f1f5f9;
        }

        .erp-sidebar {
            position: fixed;
            top: 0;
            right: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--sidebar-bg);
            z-index: 1040;
            transition: transform 0.3s ease;
            overflow-y: auto;
        }

        .erp-sidebar .brand {
            height: var(--navbar-height);
            display: flex;
            align-items: center;
            padding: 0 1.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .erp-sidebar .brand span {
            color: #fff;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .erp-sidebar .nav-link {
            color: #cbd5e1;
            padding: 0.75rem 1.25rem;
            border-radius: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.925rem;
        }

        .erp-sidebar .nav-link:hover,
        .erp-sidebar .nav-link.active {
            background: var(--sidebar-hover);
            color: #fff;
        }

        .erp-sidebar .nav-section {
            color: #64748b;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 1.25rem 1.25rem 0.5rem;
        }

        .erp-sidebar .nav-link.sub-link {
            padding-right: 2.5rem;
            font-size: 0.875rem;
        }

        .erp-navbar {
            position: fixed;
            top: 0;
            right: var(--sidebar-width);
            left: 0;
            height: var(--navbar-height);
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            z-index: 1030;
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
        }

        .erp-content {
            margin-right: var(--sidebar-width);
            margin-top: var(--navbar-height);
            padding: 1.5rem;
            min-height: calc(100vh - var(--navbar-height));
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1035;
        }

        @media (max-width: 991.98px) {
            .erp-sidebar {
                transform: translateX(100%);
            }

            .erp-sidebar.show {
                transform: translateX(0);
            }

            .erp-navbar {
                right: 0;
            }

            .erp-content {
                margin-right: 0;
            }

            .sidebar-overlay.show {
                display: block;
            }
        }

        .page-header {
            margin-bottom: 1.5rem;
        }

        .card {
            border: none;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .table th {
            font-weight: 600;
            font-size: 0.8rem;
            color: #64748b;
            border-bottom-width: 1px;
        }
    </style>

    @stack('styles')
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="erp-sidebar" id="erpSidebar">
        <div class="brand">
            <i class="bi bi-scissors text-white me-2"></i>
            <span>مصنع العبايات</span>
        </div>

        <nav class="nav flex-column py-2">
            <div class="nav-section">الرئيسية</div>
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="bi bi-speedometer2"></i>
                لوحة التحكم
            </a>

            <div class="nav-section">المبيعات</div>
            <a href="{{ route('customers.index') }}" class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}">
                <i class="bi bi-people"></i>
                العملاء
            </a>
            <a href="{{ route('sales-invoices.index') }}" class="nav-link {{ request()->routeIs('sales-invoices.*') ? 'active' : '' }}">
                <i class="bi bi-receipt"></i>
                فواتير المبيعات
            </a>
            <a href="{{ route('payment-receipts.index') }}" class="nav-link {{ request()->routeIs('payment-receipts.*') ? 'active' : '' }}">
                <i class="bi bi-cash-coin"></i>
                سندات القبض
            </a>
            <a href="{{ route('sales-returns.index') }}" class="nav-link {{ request()->routeIs('sales-returns.*') ? 'active' : '' }}">
                <i class="bi bi-arrow-return-right"></i>
                مرتجعات المبيعات
            </a>
            <a href="{{ route('customer-ledger.index') }}" class="nav-link {{ request()->routeIs('customer-ledger.*') ? 'active' : '' }}">
                <i class="bi bi-journal-text"></i>
                كشف حساب العملاء
            </a>
            <div class="nav-section">المشتريات</div>
            <a href="{{ route('suppliers.index') }}" class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                <i class="bi bi-truck"></i>
                الموردون
            </a>
            <a href="{{ route('purchase-orders.index') }}" class="nav-link {{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}"><i class="bi bi-file-earmark-text"></i>أوامر الشراء</a>
            <a href="{{ route('goods-receipts.index') }}" class="nav-link {{ request()->routeIs('goods-receipts.*') ? 'active' : '' }}"><i class="bi bi-box-arrow-in-down"></i>أذون استلام الخامات</a>
            <a href="{{ route('supplier-invoices.index') }}" class="nav-link {{ request()->routeIs('supplier-invoices.*') ? 'active' : '' }}"><i class="bi bi-receipt"></i>فواتير الموردين</a>
            <a href="{{ route('supplier-payments.index') }}" class="nav-link {{ request()->routeIs('supplier-payments.*') ? 'active' : '' }}"><i class="bi bi-cash-stack"></i>مدفوعات الموردين</a>
            <a href="{{ route('purchase-returns.index') }}" class="nav-link {{ request()->routeIs('purchase-returns.*') ? 'active' : '' }}"><i class="bi bi-arrow-return-left"></i>مرتجعات المشتريات</a>

            <div class="nav-section">الخامات</div>
            <a href="{{ route('materials.index') }}" class="nav-link sub-link {{ request()->routeIs('materials.*') ? 'active' : '' }}">
                <i class="bi bi-box-seam"></i>
                دليل الخامات
            </a>
            <a href="{{ route('material-inventory.index') }}" class="nav-link sub-link {{ request()->routeIs('material-inventory.index', 'material-inventory.show') ? 'active' : '' }}">
                <i class="bi bi-archive"></i>
                مخزون الخامات
            </a>
            <a href="{{ route('material-inventory.movements') }}" class="nav-link sub-link {{ request()->routeIs('material-inventory.movements') ? 'active' : '' }}">
                <i class="bi bi-arrow-left-right"></i>
                حركات الخامات
            </a>
            <a href="{{ route('material-inventory.opening-stock.create') }}" class="nav-link sub-link {{ request()->routeIs('material-inventory.opening-stock.*') ? 'active' : '' }}">
                <i class="bi bi-box-arrow-in-down"></i>
                أرصدة الخامات الافتتاحية
            </a>
            <a href="{{ route('material-inventory.adjustments.create') }}" class="nav-link sub-link {{ request()->routeIs('material-inventory.adjustments.*') ? 'active' : '' }}">
                <i class="bi bi-sliders"></i>
                تسويات مخزون الخامات
            </a>

            <div class="nav-section">التصنيع</div>
            <a href="{{ route('workers.index') }}" class="nav-link sub-link {{ request()->routeIs('workers.*') ? 'active' : '' }}"><i class="bi bi-people"></i>العمال</a>
            <a href="{{ route('manufacturing-operations.index') }}" class="nav-link sub-link {{ request()->routeIs('manufacturing-operations.*') ? 'active' : '' }}"><i class="bi bi-list-ol"></i>عمليات التصنيع</a>
            <a href="{{ route('boms.index') }}" class="nav-link sub-link {{ request()->routeIs('boms.*') ? 'active' : '' }}"><i class="bi bi-diagram-3"></i>قوائم المواد BOM</a>
            <a href="{{ route('production-orders.index') }}" class="nav-link sub-link {{ request()->routeIs('production-orders.*', 'work-assignments.*', 'production-defects.*', 'production-reworks.*') ? 'active' : '' }}"><i class="bi bi-gear-wide-connected"></i>أوامر الإنتاج</a>
            <a href="{{ route('production-reports.material-variance') }}" class="nav-link sub-link {{ request()->routeIs('production-reports.*') ? 'active' : '' }}"><i class="bi bi-graph-up"></i>انحراف الخامات</a>

            <div class="nav-section">مخزون المنتجات</div>
            <a href="{{ route('inventory.index') }}" class="nav-link {{ request()->routeIs('inventory.index', 'inventory.show') ? 'active' : '' }}">
                <i class="bi bi-boxes"></i>
                أرصدة المنتجات
            </a>
            <a href="{{ route('inventory.movements') }}" class="nav-link sub-link {{ request()->routeIs('inventory.movements') ? 'active' : '' }}">
                <i class="bi bi-arrow-left-right"></i>
                حركات مخزون المنتجات
            </a>
            <a href="{{ route('inventory.opening-stock.create') }}" class="nav-link sub-link {{ request()->routeIs('inventory.opening-stock.*') ? 'active' : '' }}">
                <i class="bi bi-box-arrow-in-down"></i>
                الأرصدة الافتتاحية
            </a>
            <a href="{{ route('inventory.adjustments.create') }}" class="nav-link sub-link {{ request()->routeIs('inventory.adjustments.*') ? 'active' : '' }}">
                <i class="bi bi-sliders"></i>
                تسويات المخزون
            </a>
            <div class="nav-section">المنتجات</div>
            <a href="{{ route('categories.index') }}" class="nav-link sub-link {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                <i class="bi bi-tags"></i>
                التصنيفات
            </a>
            <a href="{{ route('products.index') }}" class="nav-link sub-link {{ request()->routeIs('products.*') ? 'active' : '' }}">
                <i class="bi bi-box-seam"></i>
                الموديلات
            </a>
            <a href="{{ route('colors.index') }}" class="nav-link sub-link {{ request()->routeIs('colors.*') ? 'active' : '' }}">
                <i class="bi bi-palette"></i>
                الألوان
            </a>
            <a href="{{ route('sizes.index') }}" class="nav-link sub-link {{ request()->routeIs('sizes.*') ? 'active' : '' }}">
                <i class="bi bi-rulers"></i>
                المقاسات
            </a>
            <a href="{{ route('product-variants.index') }}" class="nav-link sub-link {{ request()->routeIs('product-variants.*') ? 'active' : '' }}">
                <i class="bi bi-layers"></i>
                متغيرات المنتجات
            </a>

           

            <div class="nav-section">قريبًا</div>
            <span class="nav-link text-muted pe-none">
                <i class="bi bi-gear-wide-connected"></i>
                التصنيع
            </span>
            <span class="nav-link text-muted pe-none">
                <i class="bi bi-calculator"></i>
                المحاسبة
            </span>
        </nav>
    </aside>

    <header class="erp-navbar">
        <button class="btn btn-link text-dark d-lg-none me-2 p-0" id="sidebarToggle" type="button">
            <i class="bi bi-list fs-4"></i>
        </button>

        <div class="flex-grow-1">
            @hasSection('breadcrumb')
                @yield('breadcrumb')
            @endif
        </div>

        <div class="dropdown">
            <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-person-circle"></i>
                <span class="d-none d-sm-inline">{{ Auth::user()->name }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>الملف الشخصي</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i>تسجيل الخروج
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </header>

    <main class="erp-content">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.getElementById('erpSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const toggle = document.getElementById('sidebarToggle');

        toggle?.addEventListener('click', () => {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });

        overlay?.addEventListener('click', () => {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    </script>

    @stack('scripts')
</body>
</html>
