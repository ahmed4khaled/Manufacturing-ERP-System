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
            scrollbar-width: thin;
            scrollbar-color: #475569 transparent;
        }

        .erp-sidebar::-webkit-scrollbar { width: 6px; }
        .erp-sidebar::-webkit-scrollbar-thumb { background: #475569; border-radius: 999px; }

        .erp-sidebar .brand {
            height: var(--navbar-height);
            display: flex;
            align-items: center;
            padding: 0 1.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            position: sticky;
            top: 0;
            background: var(--sidebar-bg);
            z-index: 2;
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
            margin: 0.1rem 0.65rem;
            border-radius: 0.55rem;
            transition: background-color 0.18s ease, color 0.18s ease, transform 0.18s ease;
        }

        .erp-sidebar .nav-link:hover,
        .erp-sidebar .nav-link.active {
            background: var(--sidebar-hover);
            color: #fff;
        }

        .erp-sidebar a.nav-link:hover {
            transform: translateX(-2px);
        }

        .erp-sidebar .nav-link.active {
            box-shadow: inset -3px 0 0 var(--accent);
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
            padding: 0.58rem 2.7rem 0.58rem 1rem;
            font-size: 0.875rem;
            margin-block: 0;
        }

        .erp-sidebar .nav-link.sub-link i {
            font-size: 0.82rem;
            opacity: 0.85;
        }

        .erp-sidebar .nav-module {
            margin-bottom: 0.15rem;
        }

        .erp-sidebar .nav-children {
            position: relative;
            padding-block: 0.15rem 0.4rem;
        }

        .erp-sidebar .nav-children::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0.45rem;
            right: 1.85rem;
            width: 1px;
            background: rgba(148, 163, 184, 0.18);
        }

        .sidebar-footer-note {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1.25rem 1rem 0.5rem;
            padding: 0.75rem;
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 0.65rem;
            color: #94a3b8;
            font-size: 0.75rem;
            background: rgba(99, 102, 241, 0.08);
        }

        .erp-sidebar .nav-link.nav-toggle {
            width: 100%;
            border: 0;
            background: transparent;
            text-align: right;
        }

        .erp-sidebar .nav-link.nav-toggle .toggle-icon {
            margin-right: auto;
            transition: transform 0.2s ease;
        }

        .erp-sidebar .nav-link.nav-toggle[aria-expanded="true"] .toggle-icon {
            transform: rotate(180deg);
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
            transition: opacity 0.15s ease;
        }

        .erp-content.is-loading {
            opacity: 0.48;
            pointer-events: none;
        }

        .ajax-progress {
            position: fixed;
            top: var(--navbar-height);
            right: var(--sidebar-width);
            left: 0;
            height: 3px;
            z-index: 1055;
            opacity: 0;
            overflow: hidden;
            pointer-events: none;
            transition: opacity 0.15s ease;
        }

        .ajax-progress::after {
            content: '';
            display: block;
            width: 35%;
            height: 100%;
            background: var(--accent);
            animation: ajax-progress 0.85s ease-in-out infinite;
        }

        .ajax-progress.show { opacity: 1; }

        @keyframes ajax-progress {
            from { transform: translateX(290%); }
            to { transform: translateX(-100%); }
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

            .ajax-progress {
                right: 0;
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

    <meta id="erpPageStylesStart" name="erp-page-styles-start">
    @stack('styles')
    <meta id="erpPageStylesEnd" name="erp-page-styles-end">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="ajax-progress" id="ajaxProgress" role="progressbar" aria-label="جاري تحميل الصفحة"></div>

    <aside class="erp-sidebar" id="erpSidebar">
        <div class="brand">
            <i class="bi bi-scissors text-white me-2"></i>
            <span>مصنع العبايات</span>
        </div>

        <nav class="nav flex-column py-2" id="erpSidebarNav">
            @include('layouts.partials.erp-sidebar')

        </nav>
    </aside>

    <header class="erp-navbar">
        <button class="btn btn-link text-dark d-lg-none me-2 p-0" id="sidebarToggle" type="button">
            <i class="bi bi-list fs-4"></i>
        </button>

        <div class="flex-grow-1" id="erpBreadcrumb">
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

    <main class="erp-content" id="erpContent">
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
        (() => {
            const sidebar = document.getElementById('erpSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const toggle = document.getElementById('sidebarToggle');
            const progress = document.getElementById('ajaxProgress');
            let navigationController = null;

            const closeMobileSidebar = () => {
                sidebar?.classList.remove('show');
                overlay?.classList.remove('show');
            };

            toggle?.addEventListener('click', () => {
                sidebar?.classList.toggle('show');
                overlay?.classList.toggle('show');
            });

            overlay?.addEventListener('click', closeMobileSidebar);

            const pageStyleNodes = (documentNode) => {
                const start = documentNode.getElementById('erpPageStylesStart');
                const end = documentNode.getElementById('erpPageStylesEnd');
                const nodes = [];

                if (! start || ! end) {
                    return nodes;
                }

                let node = start.nextSibling;
                while (node && node !== end) {
                    nodes.push(node);
                    node = node.nextSibling;
                }

                return nodes;
            };

            const replacePageStyles = (nextDocument) => {
                pageStyleNodes(document).forEach((node) => node.remove());
                const end = document.getElementById('erpPageStylesEnd');

                pageStyleNodes(nextDocument).forEach((node) => {
                    end.before(document.importNode(node, true));
                });
            };

            const executePageScripts = async (scripts) => {
                const container = document.getElementById('erpPageScripts');
                container.replaceChildren();

                for (const source of scripts) {
                    const script = document.createElement('script');
                    [...source.attributes].forEach((attribute) => script.setAttribute(attribute.name, attribute.value));
                    script.textContent = source.textContent;

                    if (script.src) {
                        await new Promise((resolve, reject) => {
                            script.addEventListener('load', resolve, { once: true });
                            script.addEventListener('error', reject, { once: true });
                            container.appendChild(script);
                        });
                    } else {
                        container.appendChild(script);
                    }
                }
            };

            const navigate = async (url, pushState = true) => {
                navigationController?.abort();
                const controller = new AbortController();
                navigationController = controller;

                const content = document.getElementById('erpContent');
                content.classList.add('is-loading');
                content.setAttribute('aria-busy', 'true');
                progress?.classList.add('show');

                try {
                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'text/html',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        signal: controller.signal,
                    });

                    if (! response.ok) {
                        throw new Error(`Navigation failed with status ${response.status}`);
                    }

                    const html = await response.text();
                    const nextDocument = new DOMParser().parseFromString(html, 'text/html');
                    const nextContent = nextDocument.getElementById('erpContent');
                    const nextSidebarNav = nextDocument.getElementById('erpSidebarNav');

                    if (! nextContent || ! nextSidebarNav) {
                        window.location.assign(response.url || url);
                        return;
                    }

                    const scripts = [
                        ...nextContent.querySelectorAll('script'),
                        ...nextDocument.querySelectorAll('#erpPageScripts script'),
                    ];
                    nextContent.querySelectorAll('script').forEach((script) => script.remove());

                    replacePageStyles(nextDocument);
                    content.innerHTML = nextContent.innerHTML;
                    document.getElementById('erpSidebarNav').innerHTML = nextSidebarNav.innerHTML;
                    document.getElementById('erpBreadcrumb').innerHTML = nextDocument.getElementById('erpBreadcrumb')?.innerHTML ?? '';
                    document.title = nextDocument.title;

                    const nextToken = nextDocument.querySelector('meta[name="csrf-token"]')?.content;
                    if (nextToken) {
                        document.querySelector('meta[name="csrf-token"]').content = nextToken;
                    }

                    if (pushState) {
                        window.history.pushState({ erpAjax: true }, '', response.url || url);
                    }

                    closeMobileSidebar();
                    window.scrollTo({ top: 0, behavior: 'auto' });
                    await executePageScripts(scripts);
                    document.dispatchEvent(new CustomEvent('erp:navigated', { detail: { url: response.url || url } }));
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        window.location.assign(url);
                    }
                } finally {
                    if (navigationController === controller) {
                        content.classList.remove('is-loading');
                        content.removeAttribute('aria-busy');
                        progress?.classList.remove('show');
                    }
                }
            };

            document.addEventListener('click', (event) => {
                const link = event.target.closest('a[data-erp-nav]');

                if (! link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target || link.download) {
                    return;
                }

                const target = new URL(link.href, window.location.href);
                if (target.origin !== window.location.origin) {
                    return;
                }

                event.preventDefault();
                navigate(target.href);
            });

            window.addEventListener('popstate', () => navigate(window.location.href, false));
        })();
    </script>

    <div id="erpPageScripts" hidden>
        @stack('scripts')
    </div>
</body>
</html>
