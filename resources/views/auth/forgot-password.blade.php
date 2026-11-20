<x-layout title="Recuperar contraseña">
    <h1 class="text-2xl font-bold">Recuperar contraseña</h1>

    <p class="mt-4 text-gray-600">Escribe tu email y te enviaremos un enlace para elegir una contraseña nueva.</p>

    @if (session('status'))
        <p class="mt-4 text-sm text-green-700">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 max-w-sm space-y-4">
        @csrf
        <div>
            <label for="email" class="block text-sm font-medium">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}"
                   class="mt-1 w-full rounded border px-3 py-2">
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="rounded bg-gray-900 px-4 py-2 text-white">Enviar enlace</button>
    </form>
</x-layout>