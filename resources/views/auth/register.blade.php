@extends('layouts.app')

@section('title', 'Crear cuenta')

@section('content')
<div class="auth-wrap">
    <div class="auth-card">
        <h1 class="text-serif">Crea tu cuenta</h1>
        <p class="auth-sub">Sube tu manuscrito y conviértelo en un libro</p>

        <form method="POST" action="{{ route('register') }}">
            @csrf
            <div class="field">
                <label for="name">Nombre</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name">
                @error('name')
                    <p class="field-error"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> {{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label for="email">Correo electrónico</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
                @error('email')
                    <p class="field-error"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> {{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" required autocomplete="new-password">
                <p class="field-hint">Mínimo 8 caracteres</p>
                @error('password')
                    <p class="field-error"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> {{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label for="password_confirmation">Repite la contraseña</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
            </div>

            <div class="form-foot">
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fa-solid fa-user-plus" aria-hidden="true"></i> Crear cuenta
                </button>
            </div>
        </form>

        <p class="auth-alt">¿Ya tienes cuenta? <a href="{{ route('login') }}">Entrar</a></p>
    </div>
</div>
@endsection
