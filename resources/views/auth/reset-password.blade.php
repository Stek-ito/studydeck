<x-layout title="Nueva contraseña">
    <h1 class="text-2xl font-bold">Nueva contraseña</h1>

    <form method="POST" action="{{ route('password.store') }}" class="mt-6 max-w-sm space-y-4">
        @csrf
        
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="block text-sm font-medium">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}"
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

        <div>
            <label for="password_confirmation" class="block text-sm font-medium">Confirmar contraseña</label>
            <input id="password_confirmation" name="password_confirmation" type="password"
                   class="mt-1 w-full rounded border px-3 py-2">
        </div>

        <button type="submit" class="rounded bg-gray-900 px-4 py-2 text-white">Cambiar contraseña</button>
    </form>
</x-layout>