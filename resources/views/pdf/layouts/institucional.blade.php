<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tituloPdf ?? 'Reporte institucional' }}</title>
    <style>
        @page { size: A4 landscape; margin: 30mm 9mm 24mm; }
        :root { --brand:#a20d25; --brand-dark:#720819; --ink:#172033; --muted:#667085; --line:#dfe3e8; --soft:#f5f7f9; --paper:#fff; }
        * { box-sizing: border-box; }
        html, body { margin:0; padding:0; }
        body { font-family: Arial, Helvetica, sans-serif; color:var(--ink); font-size:9.5pt; line-height:1.4; background:#e9edf1; }
        .report-document { position:relative; max-width:1120px; min-height:680px; margin:20px auto; padding:28px 30px 30px; background:var(--paper); box-shadow:0 14px 45px rgba(16,24,40,.14); overflow:hidden; }
        .institutional-header, .institutional-footer { position:fixed; left:0; right:0; z-index:2; pointer-events:none; }
        .institutional-header { top:-27mm; height:22mm; padding:1mm 5mm 2mm; border-bottom:.3mm solid #dbc2c7; display:flex; align-items:center; justify-content:space-between; }
        .header-mas-federacion { width:36mm; height:13mm; object-fit:contain; }
        .header-comite { width:47mm; height:14mm; object-fit:contain; }
        .header-divider { width:.3mm; height:12mm; margin-left:auto; margin-right:35mm; background:#a20d25; opacity:.45; }
        .institutional-footer { bottom:-21mm; height:16mm; padding-top:2mm; border-top:.3mm solid #e4d5d8; text-align:center; }
        .institutional-footer img { width:100%; height:100%; object-fit:contain; }
        .institutional-watermark { position:fixed; z-index:0; left:50%; top:50%; width:118mm; transform:translate(-50%,-50%) rotate(-8deg); opacity:.06; pointer-events:none; }
        .institutional-watermark img { display:block; width:100%; height:auto; filter:grayscale(100%); }
        main { position:relative; z-index:1; }
        .report-hero { display:table; width:100%; border-bottom:3px solid var(--brand); padding:0 0 10px; margin-bottom:12px; }
        .report-hero-main, .report-hero-meta { display:table-cell; vertical-align:bottom; }
        .report-hero-meta { width:250px; text-align:right; color:var(--muted); font-size:8px; }
        .eyebrow { color:var(--brand); font-size:8px; font-weight:700; letter-spacing:1.4px; text-transform:uppercase; margin-bottom:3px; }
        h1 { color:var(--ink); font-size:23px; line-height:1.05; margin:0; letter-spacing:-.3px; }
        h2 { color:var(--ink); font-size:12px; margin:0; }
        .section { margin-top:13px; page-break-inside:auto; }
        .section-heading { display:table; width:100%; margin-bottom:6px; }
        .section-heading h2, .section-heading span { display:table-cell; vertical-align:middle; }
        .section-heading span { text-align:right; color:var(--muted); font-size:8px; }
        .section-heading h2:before { content:''; display:inline-block; width:4px; height:12px; margin-right:6px; vertical-align:-2px; border-radius:2px; background:var(--brand); }
        .filters { margin:0 0 11px; padding:8px 10px; border:1px solid #ead9dd; border-left:4px solid var(--brand); border-radius:4px; background:#fbf7f8; color:#475467; }
        .filters strong { color:var(--brand-dark); }
        .filter-item { display:inline-block; margin:1px 10px 1px 0; }
        .summary { display:table; width:100%; table-layout:fixed; border-spacing:6px 0; margin:0 -6px 3px; }
        .metric { display:table-cell; padding:9px 8px; border:1px solid var(--line); border-top:3px solid var(--brand); border-radius:5px; background:#fff; text-align:left; }
        .metric strong { display:block; color:var(--brand); font-size:20px; line-height:1; margin-bottom:4px; }
        .metric span { color:var(--muted); font-size:8px; font-weight:700; text-transform:uppercase; letter-spacing:.35px; }
        .table-wrap { overflow:hidden; border:1px solid var(--line); border-radius:5px; background:#fff; }
        table { width:100%; border-collapse:collapse; page-break-inside:auto; }
        thead { display:table-header-group; }
        tr { page-break-inside:avoid; page-break-after:auto; }
        th { padding:6px 7px; border-bottom:1px solid #d1d6dc; background:#eef1f4; color:#344054; font-size:8.5pt; font-weight:700; text-align:left; text-transform:uppercase; letter-spacing:.25px; vertical-align:bottom; }
        td { padding:6px 7px; border-bottom:1px solid #e7e9ed; color:#344054; vertical-align:top; }
        tbody tr:nth-child(even) { background:#fafbfc; }
        tbody tr:last-child td { border-bottom:0; }
        .compact th, .compact td { padding:5px 6px; }
        .cell-primary { color:#101828; font-weight:700; }
        .cell-secondary { display:block; margin-top:2px; color:var(--muted); font-size:8.5pt; }
        .number { text-align:center; font-weight:700; }
        .badge { display:inline-block; padding:2px 6px; border-radius:10px; background:#edf1f4; color:#475467; font-size:7.5px; font-weight:700; white-space:nowrap; }
        .badge-success { background:#e8f5ed; color:#14743a; }
        .badge-warning { background:#fff3d6; color:#8a5800; }
        .badge-danger { background:#fde8ec; color:#a20d25; }
        .event-title { color:#101828; font-weight:700; }
        .event-description { margin-top:2px; color:#475467; line-height:1.45; }
        .event-details { margin-top:5px; padding:6px 8px; border-left:3px solid #98a2b3; border-radius:2px; background:#f6f7f8; color:#475467; }
        .event-details p { margin:2px 0; }
        .event-details strong { color:#344054; }
        .change-card { margin-top:4px; padding:5px 6px; border:1px solid #dfe3e8; border-radius:3px; background:#fff; }
        .metadata { margin-top:4px; }
        .meta-item { display:inline-block; max-width:100%; margin:1px 4px 1px 0; padding:2px 5px; border-radius:3px; background:#edf1f4; color:#475467; overflow-wrap:anywhere; }
        .meta-label { color:#344054; font-weight:700; }
        .case-card, .event-card { position:relative; page-break-inside:avoid; margin-bottom:7px; border:1px solid var(--line); border-radius:5px; background:rgba(255,255,255,.94); overflow:hidden; }
        .case-card-head, .event-card-head { display:table; width:100%; padding:7px 9px; background:#f6f7f8; border-bottom:1px solid var(--line); }
        .case-card-head > *, .event-card-head > * { display:table-cell; vertical-align:middle; }
        .case-card-body, .event-card-body { padding:8px 9px; }
        .info-grid { display:table; width:100%; table-layout:fixed; }
        .info-block { display:table-cell; padding-right:12px; vertical-align:top; }
        .info-label { display:block; margin-bottom:2px; color:var(--muted); font-size:8pt; font-weight:700; text-transform:uppercase; letter-spacing:.25px; }
        .empty { padding:18px !important; color:var(--muted); text-align:center; }
        .no-print { position:sticky; top:10px; z-index:10; display:block; margin:12px auto; padding:9px 18px; border:0; border-radius:6px; background:var(--brand); color:#fff; font-weight:700; cursor:pointer; box-shadow:0 4px 12px rgba(162,13,37,.22); }
        @media screen {
            .institutional-header, .institutional-footer { position:absolute; left:24px; right:24px; }
            .institutional-header { top:8px; height:54px; }
            .institutional-footer { bottom:7px; height:42px; }
            .institutional-watermark { position:absolute; }
            .report-document { padding-top:76px; padding-bottom:62px; }
        }
        @media print {
            body { background:#fff; }
            .report-document { max-width:none; min-height:0; margin:0; padding:0; box-shadow:none; overflow:visible; }
            .no-print { display:none; }
        }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Imprimir / Guardar como PDF</button>
    <div class="report-document">
        @include('pdf.partials.header')
        @include('pdf.partials.watermark')
        <main>@yield('content')</main>
        @include('pdf.partials.footer')
    </div>
</body>
</html>
