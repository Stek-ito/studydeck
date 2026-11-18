<x-layout title="Verifica tu email">
    <h1 class="text-2xl font-bold">Verifica tu email</h1>

    <p class="mt-4 text-gray-600">Te hemos enviado un enlace a tu correo. Púlsalo para activar tu cuenta.</p>

    @if (session('status') === 'verification-link-sent')
        <p class="mt-4 text-sm text-green-700">Te hemos enviado un enlace nuevo.</p>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
        @csrf

        <button type="submit" class="rounded bg-gray-900 px-4 py-2 text-white">Reenviar correo</button>
    </form>
</x-layout>