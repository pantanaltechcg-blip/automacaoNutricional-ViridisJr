<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'RótuloNutri')</title>
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body data-page="@yield('page')" class="min-h-screen bg-rn-bg text-rn-ink font-sans antialiased">
<div class="min-h-screen flex flex-col lg:flex-row">
    <aside aria-label="Navegação principal" class="w-full lg:w-60 shrink-0 bg-rn-sidebar text-white flex flex-col px-4 py-6">
        <a href="{{ route('recipes.create') }}" class="px-3 mb-8 text-xl font-semibold tracking-tight inline-block">
            <span>Rótulo</span><span class="text-rn-brand-accent">Nutri</span>
        </a>

        <nav class="flex flex-col gap-1.5 text-sm font-medium">
            <a href="{{ route('recipes.create') }}"
               class="px-3.5 py-2.5 rounded-lg transition-colors {{ request()->routeIs('recipes.*', 'ingredients.*') ? 'bg-rn-sidebar-active text-white' : 'text-white/80 hover:bg-white/10' }}">
                Receitas
            </a>

            <a href="{{ route('labels.index') }}"
               class="px-3.5 py-2.5 rounded-lg transition-colors {{ request()->routeIs('labels.*') ? 'bg-rn-sidebar-active text-white' : 'text-white/80 hover:bg-white/10' }}">
                Rótulos
            </a>
        </nav>
    </aside>

    <main class="flex-1 min-w-0 px-6 py-7 lg:px-10 lg:py-8">
        <div class="max-w-5xl mx-auto">
            <header class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between mb-6">
                <div>
                    @hasSection('breadcrumb')
                        <p class="text-xs font-medium text-rn-muted mb-1">@yield('breadcrumb')</p>
                    @endif
                    <h1 class="text-2xl lg:text-[28px] font-semibold text-rn-ink tracking-tight">@yield('heading')</h1>
                    <p class="text-sm text-rn-muted mt-0.5">@yield('subheading', 'Base de dados: Tabela TACO')</p>
                </div>

                @hasSection('stepper')
                    <div class="shrink-0">
                        @yield('stepper')
                    </div>
                @endif
            </header>

            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
