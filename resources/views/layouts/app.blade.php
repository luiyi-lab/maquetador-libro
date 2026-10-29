<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title', 'Maquetador de Libros')</title>

    {{-- Tema: aplicar ANTES del CSS para evitar destello (FOUC) --}}
    <script>
        (function () {
            // Override explícito vía querystring (?theme=light|dark), útil para pruebas
            var param = new URLSearchParams(location.search).get('theme');
            if (param === 'light' || param === 'dark') {
                localStorage.setItem('theme', param);
                if (param === 'light') { history.replaceState(null, '', location.pathname); }
            }
            var saved = localStorage.getItem('theme');
            var dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (dark) document.documentElement.setAttribute('data-theme', 'dark');
        })();
    </script>

    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📖</text></svg>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body>
<a href="#main-content" class="skip-link">Saltar al contenido</a>

<header class="topbar" id="topbar">
    <a href="{{ route('dashboard') }}" class="brand">
        <span class="brand-icon"><i class="fa-solid fa-book-open" aria-hidden="true"></i></span>
        <span>Maquetador <span aria-hidden="true">·</span> Libros</span>
    </a>

    <div class="topbar-actions">
        @auth
            <button type="button" class="btn btn-sm btn-ghost" id="theme-toggle" aria-label="Cambiar tema claro/oscuro" title="Cambiar tema">
                <i class="fa-solid fa-moon" aria-hidden="true"></i>
            </button>
            <a href="{{ route('books.create') }}" class="btn btn-sm btn-primary">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                <span class="hide-sm">Nuevo libro</span>
            </a>
            <form method="POST" action="{{ route('logout') }}" class="inline topbar-logout">
                @csrf
                <button type="submit" class="btn btn-sm btn-ghost" title="Cerrar sesión de {{ auth()->user()->name }}">
                    <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                    <span class="hide-sm">{{ auth()->user()->name }}</span>
                </button>
            </form>
            <button type="button" class="nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="nav-menu" aria-label="Abrir menú">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        @endauth
        @guest
            <a href="{{ route('login') }}" class="btn btn-sm btn-ghost">Entrar</a>
            <a href="{{ route('register') }}" class="btn btn-sm btn-primary">Crear cuenta</a>
        @endguest
    </div>
</header>

{{-- Menú móvil (duplicado con etiquetas completas; mismo logout) --}}
@auth
    <nav class="nav-menu" id="nav-menu" aria-label="Menú principal">
        <a href="{{ route('books.create') }}" class="btn"><i class="fa-solid fa-plus"></i> Nuevo libro</a>
        <a href="{{ route('dashboard') }}" class="btn"><i class="fa-solid fa-gauge-high"></i> Mis libros</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn" style="width:100%">
                <i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión ({{ auth()->user()->name }})
            </button>
        </form>
    </nav>
@endauth

{{-- Notificaciones flash como toasts --}}
<div class="toasts" aria-live="polite">
    @if (session('status'))
        <div class="toast toast-success" role="status">
            <i class="fa-solid fa-circle-check toast-icon" aria-hidden="true"></i>
            <span class="toast-body">{{ session('status') }}</span>
            <button type="button" class="toast-close" aria-label="Cerrar aviso"><i class="fa-solid fa-xmark"></i></button>
        </div>
    @endif
    @if (session('error'))
        <div class="toast toast-error" role="alert">
            <i class="fa-solid fa-circle-exclamation toast-icon" aria-hidden="true"></i>
            <span class="toast-body">{{ session('error') }}</span>
            <button type="button" class="toast-close" aria-label="Cerrar aviso"><i class="fa-solid fa-xmark"></i></button>
        </div>
    @endif
</div>

<main class="container" id="main-content">
    @yield('content')
</main>

<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
