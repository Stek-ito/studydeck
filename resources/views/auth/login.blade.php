<x-layout title="Entrar">
    <h1 class="text-2xl font-bold">Entrar</h1>

    <form method="POST" action="{{ route('login') }}" class="mt-6 max-w-sm space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}"
                   class="mt-1 w-full rounded border px-3 py-2">
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium">Contraseña</label>
            <input id="password" name="password" type="password"
                   class="mt-1 w-full rounded border px-3 py-2">
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center gap-2">
            <input id="remember" name="remember" type="checkbox">
            <label for="remember" class="text-sm">Recuérdame</label>
        </div>

        <button type="submit" class="rounded bg-gray-900 px-4 py-2 text-white">Entrar</button>

        <p class="text-sm text-gray-600">
            ¿No tienes cuenta? <a href="{{ route('register') }}" class="underline">Crea una</a>
        </p>
    </form>
</x-layout>