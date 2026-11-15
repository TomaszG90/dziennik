<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aktywacja konta w Dzienniku podróży</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
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
            padding: 2.5rem;
            box-shadow: 0 4px 16px rgba(41, 77, 61, 0.08);
        }
        .header {
            text-align: center;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 1.5rem;
        }
        .logo {
            font-size: 2.5rem;
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        h1 {
            color: #386a54;
            font-size: 1.5rem;
            margin: 0;
            font-weight: 700;
        }
        p {
            line-height: 1.6;
            margin: 0 0 1rem;
            font-size: 1rem;
        }
        .highlight-box {
            background: #e9f5ef;
            border-left: 4px solid #386a54;
            border-radius: 0.5rem;
            padding: 1.25rem;
            margin: 1.5rem 0;
        }
        .button-wrapper {
            text-align: center;
            margin: 2rem 0;
        }
        .button {
            display: inline-block;
            background: #386a54;
            color: #ffffff !important;
            text-decoration: none;
            padding: 0.85rem 2rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 1.05rem;
            box-shadow: 0 2px 6px rgba(56, 106, 84, 0.25);
        }
        .button:hover {
            background: #2d5443;
        }
        .link-fallback {
            word-break: break-all;
            font-size: 0.85rem;
            color: #4b5563;
            background: #f9fafb;
            padding: 0.75rem;
            border-radius: 0.375rem;
            border: 1px solid #e5e7eb;
        }
        .link-fallback a {
            color: #386a54;
            text-decoration: underline;
        }
        .footer {
            margin-top: 2.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
            font-size: 0.8rem;
            color: #6b7280;
            text-align: center;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🌍</div>
            <h1>Dziennik Podróży</h1>
        </div>

        <p>Cześć <strong>{{ $user->name }}</strong>!</p>

        <p>
            Dziękujemy za założenie konta w aplikacji <strong>Dziennik podróży</strong>.
            Cieszymy się, że do nas dołączasz! Zanim wyruszysz w podróż i zaczniesz rejestrować swoje przygody, aktywuj konto, klikając poniższy przycisk:
        </p>

        <div class="button-wrapper">
            <a href="{{ $activationUrl }}" class="button" target="_blank" rel="noopener noreferrer">
                Aktywuj konto
            </a>
        </div>

        <div class="highlight-box">
            <p style="margin: 0;">
                ℹ️ Link aktywacyjny jest ważny przez <strong>24 godziny</strong>. Po tym czasie konieczne będzie ponowne przesłanie linku aktywacyjnego.
            </p>
        </div>

        <p style="font-size: 0.9rem; color: #4b5563; margin-top: 1.5rem;">
            Jeśli przycisk nie działa, skopiuj poniższy link i wklej go bezpośrednio w pasku adresu przeglądarki:
        </p>
        <div class="link-fallback">
            <a href="{{ $activationUrl }}">{{ $activationUrl }}</a>
        </div>

        <div class="footer">
            <p style="margin-bottom: 0.5rem;">
                Jeśli to nie Ty zakładałeś konto w Dzienniku podróży, zignoruj tę wiadomość – konto pozostanie nieaktywne.
            </p>
            <p style="margin: 0;">
                &copy; {{ date('Y') }} Dziennik podróży. Wszelkie prawa zastrzeżone.
            </p>
        </div>
    </div>
</body>
</html>
