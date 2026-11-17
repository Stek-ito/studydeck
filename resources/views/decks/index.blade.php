<x-layout title="Mis mazos">
    <h1 class="text-2xl font-bold">Mis mazos</h1>
     <div class="mt-6 grid gap-4 sm:grid-cols-2">
        @forelse ($decks as $deck)
            <x-deck-card :deck="$deck" />
        @empty
            <p class="text-gray-600">No tienes mazos creados.</p>
        @endforelse
     </div>
</x-layout>