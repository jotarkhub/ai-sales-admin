<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Masuk — {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: system-ui, sans-serif; background: #f3f4f6; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .card { background: #fff; padding: 2rem; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,.1); width: 100%; max-width: 360px; }
        h1 { font-size: 1.25rem; margin-bottom: 1.5rem; }
        label { display: block; font-size: .875rem; margin-bottom: .25rem; color: #374151; }
        input { width: 100%; padding: .5rem; margin-bottom: 1rem; border: 1px solid #d1d5db; border-radius: .375rem; box-sizing: border-box; }
        button { width: 100%; padding: .6rem; background: #4f46e5; color: #fff; border: none; border-radius: .375rem; cursor: pointer; }
        .error { color: #dc2626; font-size: .875rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Masuk ke {{ config('app.name') }}</h1>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required>

            <button type="submit">Masuk</button>
        </form>
    </div>
</body>
</html>
