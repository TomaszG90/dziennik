<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zaproszenie do Dziennika podróży</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #f5f2e9;
            margin: 0;
            padding: 0;
            color: #294d3d;
        }
        .container {
            max-width: 600px;
            margin: 2rem auto;
            background: #ffffff;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 3px 12px rgba(41, 77, 61, 0.08);
        }
        h1 {
            color: #386a54;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        p {
            line-height: 1.6;
            margin-bottom: 1rem;
        }
        .profile-info {
            background: #e9f5ef;
            border-radius: 0.75rem;
            padding: 1.25rem;
            margin: 1.5rem 0;
        }
        .profile-info strong {
            color: #294d3d;
        }
        .button {
            display: inline-block;
            background: #386a54;
            color: #ffffff;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            margin-top: 1rem;
        }
        .footer {
            margin-top: 2rem;
            font-size: 0.85rem;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚗 Zaproszenie do Dziennika podróży</h1>

        <p>Cześć!</p>

        <p>
            {{ $inviter->name }} zaprasza Cię do dołączenia do niego w aplikacji
            <strong>Dziennik podróży</strong>.
        </p>

        <div class="profile-info">
            <p><strong>Profil:</strong> {{ $inviter->name }}</p>
            @if($inviter->city)
                <p><strong>Miasto:</strong> {{ $inviter->city }}</p>
            @endif
            <p>
                <strong>Link do profilu:</strong><br>
                <a href="{{ config('app.frontend_url', 'http://localhost:5173') }}/users/{{ $inviter->id }}">
                    {{ config('app.frontend_url', 'http://localhost:5173') }}/users/{{ $inviter->id }}
                </a>
            </p>
        </div>

        <p>
            Kliknij poniższy link, aby przejść bezpośrednio do profilu:
        </p>

        <a href="{{ config('app.frontend_url', 'http://localhost:5173') }}/users/{{ $inviter->id }}" class="button">
            Zobacz profil
        </a>

        <p class="footer">
            Jeśli nie chcesz odwiedzać tego profilu, po prostu zignoruj tę wiadomość.
        </p>
    </div>
</body>
</html>
