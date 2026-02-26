<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Teste Notificação</title>
</head>
<body style="font-family: Arial; padding: 40px;">

    <h2>Disparar Notificação</h2>

    @if(session('success'))
        <p style="color: green;">
            {{ session('success') }}
        </p>
    @endif

    <form method="POST" action="{{ route('test.notify') }}">
        @csrf
        <button type="submit" style="
            padding: 10px 20px;
            background: #111827;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        ">
            Gerar Notificação
        </button>
    </form>

</body>
</html>