<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Track Your Time') }} · Admin</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-rose-950 text-rose-50">
        <div class="min-h-screen flex items-center justify-center px-6 py-12">
            <div class="w-full max-w-md">
                @yield('content')
            </div>
        </div>
    </body>
</html>
