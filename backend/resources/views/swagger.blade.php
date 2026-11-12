<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $config->get('ui.title') ?? config('app.name') . ' - API' }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.34.1/swagger-ui.css">
    <style>
        html {
            box-sizing: border-box;
            overflow-y: scroll;
        }

        *,
        *::before,
        *::after {
            box-sizing: inherit;
        }

        body {
            margin: 0;
            background: #f7f9fc;
        }

        .swagger-ui .topbar {
            background: #244b3b;
        }

        .swagger-ui .topbar-wrapper .link {
            max-width: none;
        }

        .swagger-ui .topbar-wrapper .link::after {
            content: "Dziennik Podróży API";
            margin-left: 0.75rem;
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
<div id="swagger-ui"></div>
<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.34.1/swagger-ui-bundle.js"></script>
<script>
    window.onload = () => {
        window.ui = SwaggerUIBundle({
            url: '/docs/api.json',
            dom_id: '#swagger-ui',
            deepLinking: true,
            displayRequestDuration: true,
            filter: true,
            layout: 'BaseLayout',
            presets: [SwaggerUIBundle.presets.apis],
        });
    };
</script>
</body>
</html>
