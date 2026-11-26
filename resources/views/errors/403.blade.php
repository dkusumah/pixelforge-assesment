<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 Forbidden - Direct Access Prohibited</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    <style>
        body {
            margin: 0;
            font-family: 'Instrument Sans', system-ui, -apple-system, sans-serif;
            background-color: #090d16;
            color: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
            box-sizing: border-box;
        }
        .card {
            max-width: 480px;
            width: 100%;
            background: #0f172a;
            border: 1px solid #1e293b;
            border-radius: 1.5rem;
            padding: 2.5rem;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .badge {
            display: inline-block;
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 9999px;
            padding: 0.35rem 0.85rem;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 1.25rem;
        }
        h1 {
            font-size: 1.75rem;
            font-weight: 800;
            margin: 0 0 0.75rem;
            color: #ffffff;
            letter-spacing: -0.025em;
        }
        p {
            color: #94a3b8;
            font-size: 0.875rem;
            line-height: 1.6;
            margin: 0 0 2rem;
        }
        .btn {
            display: inline-block;
            background: #4f46e5;
            color: #ffffff;
            font-weight: 700;
            font-size: 0.875rem;
            padding: 0.75rem 1.75rem;
            border-radius: 0.75rem;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .btn:hover {
            background: #4338ca;
        }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge">403 Forbidden</span>
        <h1>Direct Access Prohibited</h1>
        <p>
            {{ $exception->getMessage() ?: 'Direct access to the application index or internal server files is strictly restricted. Please navigate to the application using standard URLs.' }}
        </p>
        <a href="/" class="btn">Return to Homepage &rarr;</a>
    </div>
</body>
</html>
