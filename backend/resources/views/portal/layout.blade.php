<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Harbor Safe Portal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: sans-serif;
            background-color: #f5f5f5;
        }

        .navbar {
            background-color: #5c0f8b;
            padding: 0 2rem;
            height: 60px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .navbar img {
            height: 36px;
        }

        .navbar span {
            color: white;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .content {
            padding: 2rem;
        }

        .question {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }

        .question p {
            font-weight: 600;
            margin-bottom: 0.75rem;
        }

        .question label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-right: 1.5rem;
            cursor: pointer;
        }

        button[type="submit"] {
            margin-top: 1rem;
            background-color: #1a3c5e;
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
        }

        button[type="submit"]:hover {
            background-color: #15304d;
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <img src="/images/HSHAC Black and White.svg" alt="HarborSafe Logo">
        <span>Harbor Safe Lethality Assessment Portal</span>
    </nav>

    <div class="content">
        @yield('content')
    </div>

    @yield('scripts')
</body>
</html> 