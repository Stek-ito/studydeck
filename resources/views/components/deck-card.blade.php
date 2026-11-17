@props(['deck'])

<div class="rounded-lg border bg-white p-4">
    <h2 class="font-bold">{{ $deck->name }}</h2>
    <p class="text-gray-600">{{ $deck->description }}</p>
    <p class="text-sm">{{ $deck->cards_count }} fichas</p>
</div>