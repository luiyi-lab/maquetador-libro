@extends('layouts.app')

@section('title', 'Entrar')

@section('content')
<div class="auth-wrap">
    <div class="auth-card">
        <h1 class="text-serif">Bienvenido de nuevo</h1>
        <p class="auth-sub">Entra para continuar con tus libros</p>

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="field">
                <label for="email">Correo electrónico</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       autocomplete="email" placeholder="tu@correo.com">
                @error('email')
                    <p class="field-error"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> {{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" required autocomplete="current-password">
                @error('password')
                    <p class="field-error"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> {{ $message }}</p>
                @enderror
            </div>

            <label class="checkbox">
                <input type="checkbox" name="remember" value="1"> Recordarme en este equipo
            </label>

            <div class="form-foot">
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Entrar
                </button>
            </div>
        </form>

        <p class="auth-alt">¿No tienes cuenta? <a href="{{ route('register') }}">Regístrate gratis</a></p>
    </div>
</div>
@endsection
