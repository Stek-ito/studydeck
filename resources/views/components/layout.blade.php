@props(['title' => 'StudyDeck'])

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · StudyDeck</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">
    <nav class="border-b bg-white">
        <div class="mx-auto flex max-w-4xl gap-6 px-4 py-3">
            <a href="{{ route('home') }}" class="font-bold">StudyDeck</a>
            <a href="{{ route('decks.index') }}">Mis mazos</a>
        </div>
    </nav>

    <main class="mx-auto max-w-4xl px-4 py-8">
        {{ $slot }}
    </main>
</body>
</html>