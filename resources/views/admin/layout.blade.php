<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin' }} · TL Store</title>
    <style>
        :root{color-scheme:light;--ink:#132d23;--muted:#64756d;--green:#08734f;--green-dark:#07583e;--wash:#f1f5f2;--line:#dce6df;--white:#fff;--gold:#e5ae2e;--red:#b42318;--shadow:0 12px 35px rgba(19,45,35,.08)}*{box-sizing:border-box}body{margin:0;background:var(--wash);color:var(--ink);font:14px/1.5 Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif}button,input,select,textarea{font:inherit}a{color:inherit;text-decoration:none}.shell{min-height:100vh;display:grid;grid-template-columns:260px minmax(0,1fr)}.sidebar{background:#102f23;color:#e9f4ed;min-height:100vh;padding:20px 14px;position:sticky;top:0;height:100vh;overflow:auto}.brand{display:flex;align-items:center;gap:12px;padding:6px 10px 21px;border-bottom:1px solid rgba(255,255,255,.14)}.brand-mark{width:40px;height:40px;border-radius:13px;background:linear-gradient(135deg,#21a976,#076544);display:grid;place-items:center;font-weight:900;color:#fff;box-shadow:0 7px 19px rgba(0,0,0,.2)}.brand strong{font-size:15px;letter-spacing:.2px;display:block}.brand small{color:#a8c4b3}.nav-section{margin-top:20px}.nav-label{font-size:10px;letter-spacing:1.2px;text-transform:uppercase;color:#8fad9b;font-weight:800;padding:0 10px 7px}.nav-link{display:flex;align-items:center;gap:11px;padding:9px 10px;margin:2px 0;border-radius:10px;color:#c9dbd0;font-weight:600;font-size:13px;transition:.16s}.nav-link:hover,.nav-link.active{background:rgba(47,174,122,.2);color:white}.nav-icon{width:20px;text-align:center;color:#a8d8bd}.sidebar-footer{margin-top:22px;padding:12px 10px;border-top:1px solid rgba(255,255,255,.14);font-size:11px;color:#9bb7a5}.main{min-width:0}.topbar{height:72px;background:rgba(255,255,255,.9);backdrop-filter:blur(12px);border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 30px;position:sticky;top:0;z-index:5}.topbar h1{margin:0;font-size:18px;letter-spacing:-.3px}.topbar .who{display:flex;align-items:center;gap:13px;color:var(--muted);font-size:12px}.content{max-width:1480px;margin:auto;padding:26px 30px 56px}.eyebrow{font-size:11px;text-transform:uppercase;letter-spacing:1.3px;font-weight:800;color:var(--green)}.page-title{font-size:27px;line-height:1.2;letter-spacing:-.7px;margin:5px 0;color:var(--ink)}.page-subtitle{color:var(--muted);margin:0 0 22px}.card{background:var(--white);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);padding:18px}.grid{display:grid;gap:14px}.metric-grid{grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:18px}.metric-card{padding:17px 18px;display:flex;align-items:center;gap:13px}.metric-icon{width:42px;height:42px;border-radius:14px;background:#e7f3eb;color:var(--green);display:grid;place-items:center;font-size:19px}.metric-label{font-size:11px;color:var(--muted);font-weight:700}.metric-value{font-size:25px;font-weight:900;line-height:1.1;margin-top:3px}.section-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px}.section-head h2{font-size:16px;margin:0}.button{display:inline-flex;align-items:center;justify-content:center;gap:8px;background:var(--green);color:#fff;padding:9px 13px;border:0;border-radius:10px;font-weight:800;font-size:12px;cursor:pointer}.button:hover{background:var(--green-dark)}.button.secondary{background:#edf4ef;color:var(--green);border:1px solid var(--line)}.button.danger{background:#fff0ef;color:var(--red);border:1px solid #f2cbc8}.button.small{padding:6px 9px;font-size:11px}.toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}.search{display:flex;gap:8px;flex:1;max-width:560px}.input,.select,.textarea{width:100%;background:#fff;border:1px solid #ccd9d0;border-radius:10px;padding:10px 11px;color:var(--ink);outline:0}.input:focus,.select:focus,.textarea:focus{border-color:#2c9b70;box-shadow:0 0 0 3px rgba(44,155,112,.12)}.textarea{min-height:125px;resize:vertical}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:12px}table{width:100%;border-collapse:collapse;min-width:720px}th{font-size:10px;text-transform:uppercase;letter-spacing:.7px;color:#75867d;background:#f7faf7;text-align:left;padding:11px 12px;white-space:nowrap}td{padding:12px;border-top:1px solid #edf1ee;font-size:12px;vertical-align:middle}td strong{font-size:12px}.muted{color:var(--muted)}.mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11px}.badge{display:inline-flex;padding:4px 8px;border-radius:100px;background:#eff3f0;color:#58675f;font-size:10px;font-weight:800}.badge.green{background:#e6f5ec;color:#08734f}.badge.gold{background:#fff4d9;color:#855e00}.badge.red{background:#ffedeb;color:#ae2c25}.row-actions{display:flex;gap:6px;align-items:center;white-space:nowrap}.inline-form{display:inline}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.field{display:grid;gap:6px}.field.full{grid-column:1/-1}.field label{font-size:12px;font-weight:800}.field-help{font-size:10px;color:var(--muted)}.checkline{display:flex;align-items:center;gap:10px;padding:9px 0}.flash{padding:11px 14px;border-radius:11px;margin-bottom:15px;background:#e7f5ec;color:#0a6849;border:1px solid #c6e6d2;font-size:12px;font-weight:700}.flash.error{background:#fff0ef;color:#aa2921;border-color:#f2cbc8}.errors{color:#ad2921;font-size:11px}.pagination{display:flex;justify-content:space-between;align-items:center;margin-top:13px;font-size:11px;color:var(--muted)}.link{color:var(--green);font-weight:800}.quick-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.quick-link{display:flex;align-items:center;justify-content:space-between;padding:15px;border:1px solid var(--line);border-radius:13px;background:#fff}.quick-link:hover{border-color:#87bea1;transform:translateY(-1px)}.quick-link small{display:block;color:var(--muted);margin-top:3px}.detail-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.detail-row{display:flex;justify-content:space-between;gap:14px;padding:9px 0;border-bottom:1px solid #edf1ee;font-size:12px}.detail-row:last-child{border-bottom:0}.danger-zone{border-color:#efc3c0}.mobile-menu{display:none}
        @media(max-width:1100px){.shell{grid-template-columns:220px minmax(0,1fr)}.content{padding:22px}.metric-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.quick-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:760px){.shell{display:block}.sidebar{position:fixed;left:-285px;top:0;width:260px;z-index:20;transition:left .2s;box-shadow:var(--shadow)}.sidebar.open{left:0}.mobile-menu{display:inline-grid;place-items:center;width:36px;height:36px;background:#edf4ef;border:0;border-radius:10px;margin-right:10px;color:var(--green)}.topbar{height:62px;padding:0 14px}.topbar h1{font-size:15px}.topbar .who span{display:none}.content{padding:18px 13px 35px}.page-title{font-size:23px}.metric-grid{gap:9px}.metric-card{padding:13px;gap:9px}.metric-value{font-size:21px}.metric-icon{width:35px;height:35px}.quick-grid,.detail-grid{grid-template-columns:1fr}.form-grid{grid-template-columns:1fr}.field.full{grid-column:auto}.toolbar{align-items:stretch;flex-direction:column}.search{max-width:none}.card{padding:14px}}
    </style>
</head>
<body>
@php
    $admin = $admin ?? auth('admin')->user();
@endphp
<div class="shell">
    <aside class="sidebar" id="adminSidebar">
        <a class="brand" href="{{ route('admin.dashboard') }}"><span class="brand-mark">TL</span><span><strong>TL Store</strong><small>Administration</small></span></a>
        @php
            $role = (string) ($admin->role ?? '');
            $groups = [
                'Workspace' => ['admin.dashboard' => 'Overview', 'admin.users.index' => 'Users', 'admins' => 'Admin accounts'],
                'Marketplace' => ['apps' => 'App catalog', 'review-campaigns' => 'Review campaigns', 'ads' => 'Ads listings'],
                'Packages & rewards' => ['subscription-groups' => 'Subscription groups', 'subscription-plans' => 'Subscription plans', 'package-addons' => 'Package addons', 'rank-levels' => 'Ranking levels', 'referrals' => 'Referrals'],
                'Moderation' => ['review-moderation' => 'App reviews', 'share-moderation' => 'Ad shares', 'social-follow-activity' => 'Social follow rewards', 'deposit-moderation' => 'Manual deposits', 'admin.gateway-payments.index' => 'Gateway payments', 'admin.gateway-balances.index' => 'Gateway balances', 'withdrawal-moderation' => 'Withdrawals', 'campaign-orders' => 'Campaign orders', 'app-reports' => 'App reports', 'admin.support-tickets.index' => 'Support tickets'],
                'Platform content' => ['review-questions' => 'Review questions', 'review-templates' => 'Question templates', 'announcements' => 'Announcements', 'faqs' => 'FAQs', 'testimonials' => 'Testimonials', 'legal-documents' => 'Privacy & terms', 'admin.notifications.index' => 'Email & notification templates', 'admin.telegram.index' => 'Telegram messaging'],
                'Configuration' => ['admin.settings.index' => 'System settings', 'payment-options' => 'Payment gateways', 'withdrawal-options' => 'Withdrawal methods', 'banks' => 'Banks', 'countries' => 'Countries', 'regions' => 'Regions', 'region-countries' => 'Region coverage', 'social-handles' => 'Social platforms', 'app-versions' => 'App versions', 'exchange-rates' => 'Exchange rates', 'vtu-orders' => 'VAS orders', 'audit-logs' => 'Audit log'],
            ];
            $roleModules = match ($role) {
                'super_admin' => [...array_keys(config('admin.modules')), 'admin.users.index', 'admin.settings.index', 'admin.support-tickets.index', 'admin.notifications.index', 'admin.telegram.index', 'admin.gateway-payments.index', 'admin.gateway-balances.index'],
                'admin' => [...array_values(array_diff(array_keys(config('admin.modules')), ['admins', 'system-settings'])), 'admin.users.index', 'admin.support-tickets.index', 'admin.notifications.index', 'admin.telegram.index', 'admin.gateway-payments.index'],
                'content_admin' => ['apps','review-campaigns','ads','subscription-groups','subscription-plans','package-addons','review-questions','review-templates','announcements','faqs','testimonials','legal-documents','countries','regions','region-countries','social-handles','app-versions'],
                'moderator' => ['review-moderation','share-moderation','social-follow-activity','app-reports','campaign-orders','admin.users.index','admin.support-tickets.index'],
                'finance' => ['deposit-moderation','admin.gateway-payments.index','withdrawal-moderation','payment-options','withdrawal-options','banks','admin.users.index'],
                default => [],
            };
            $module = request()->route('module');
        @endphp
        @foreach($groups as $group => $links)
            @php $visible = collect($links)->filter(fn ($label, $key) => $key === 'admin.dashboard' || in_array($key, $roleModules, true)); @endphp
            @if($visible->isNotEmpty())
                <div class="nav-section"><div class="nav-label">{{ $group }}</div>
                    @foreach($visible as $key => $label)
                        @if($key === 'admin.dashboard')
                            <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><span class="nav-icon">⌂</span>{{ $label }}</a>
                        @elseif($key === 'admin.support-tickets.index')
                            <a class="nav-link {{ request()->routeIs('admin.support-tickets.*') ? 'active' : '' }}" href="{{ route('admin.support-tickets.index') }}"><span class="nav-icon">✉</span>{{ $label }}</a>
                        @elseif($key === 'admin.notifications.index')
                            <a class="nav-link {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}" href="{{ route('admin.notifications.index') }}"><span class="nav-icon">✉</span>{{ $label }}</a>
                        @elseif($key === 'admin.telegram.index')
                            <a class="nav-link {{ request()->routeIs('admin.telegram.*') ? 'active' : '' }}" href="{{ route('admin.telegram.index') }}"><span class="nav-icon">➤</span>{{ $label }}</a>
                        @elseif($key === 'admin.users.index')
                            <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><span class="nav-icon">♙</span>{{ $label }}</a>
                        @elseif($key === 'admin.settings.index')
                            <a class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.index') }}"><span class="nav-icon">⚙</span>{{ $label }}</a>
                        @elseif($key === 'admin.gateway-payments.index')
                            <a class="nav-link {{ request()->routeIs('admin.gateway-payments.*') ? 'active' : '' }}" href="{{ route('admin.gateway-payments.index') }}"><span class="nav-icon">₦</span>{{ $label }}</a>
                        @elseif($key === 'admin.gateway-balances.index')
                            <a class="nav-link {{ request()->routeIs('admin.gateway-balances.*') ? 'active' : '' }}" href="{{ route('admin.gateway-balances.index') }}"><span class="nav-icon">◫</span>{{ $label }}</a>
                        @else
                            <a class="nav-link {{ $module === $key ? 'active' : '' }}" href="{{ route('admin.modules.index', $key) }}"><span class="nav-icon">{{ match($key) {'apps'=>'▣','ads'=>'◈','review-moderation'=>'✓','share-moderation'=>'↗','deposit-moderation'=>'＋','withdrawal-moderation'=>'↙','system-settings'=>'⚙','audit-logs'=>'☷',default=>'·'} }}</span>{{ $label }}</a>
                        @endif
                    @endforeach
                </div>
            @endif
        @endforeach
        <div class="sidebar-footer">Admin Console · {{ now()->format('Y') }}<br>All sensitive changes are audited.</div>
    </aside>
    <main class="main">
        <header class="topbar">
            <div style="display:flex;align-items:center"><button class="mobile-menu" onclick="document.getElementById('adminSidebar').classList.toggle('open')" aria-label="Open menu">☰</button><h1>{{ $title ?? 'Overview' }}</h1></div>
            <div class="who"><span>{{ $admin?->full_name ?? 'Administrator' }} · {{ str_replace('_',' ', $admin?->role ?? '') }}</span><form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="button secondary small" type="submit">Sign out</button></form></div>
        </header>
        <section class="content">
            @if(session('success'))<div class="flash">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="flash error">{{ $errors->first() }}</div>@endif
            @yield('content')
        </section>
    </main>
</div>
</body>
</html>
