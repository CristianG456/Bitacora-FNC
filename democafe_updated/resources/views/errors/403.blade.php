<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acceso Denegado - Sistema Jurídico</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body style="background:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;">

<div style="text-align:center;max-width:420px;padding:40px;">

    <div style="width:80px;height:80px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;">
        <i data-lucide="lock" style="width:36px;height:36px;color:#dc2626;"></i>
    </div>

    <h1 style="font-size:22px;font-weight:700;color:#111827;margin:0 0 10px;">Acceso denegado</h1>
    <p style="font-size:14px;color:#6b7280;margin:0 0 28px;line-height:1.7;">
        No tienes permiso para acceder a esta sección del sistema.<br>
        Contacta al administrador si crees que es un error.
    </p>

    <a href="{{ route('dashboard') }}"
       style="background:#b11226;color:white;padding:11px 24px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:8px;">
        <i data-lucide="arrow-left" style="width:16px;height:16px;"></i>
        Volver al Dashboard
    </a>

</div>

<script>lucide.createIcons();</script>
</body>
</html>
