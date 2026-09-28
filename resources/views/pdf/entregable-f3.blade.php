<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 28px 34px; }
        body { font-family: 'Helvetica', sans-serif; color: #1F2937; font-size: 10.5px; line-height: 1.55; }
        .banda { background: #00A859; color: #fff; padding: 6px 10px; font-size: 8px; font-weight: bold;
                 letter-spacing: 1px; text-transform: uppercase; margin-bottom: 14px; }
        h1 { font-size: 19px; color: #1F2937; margin: 0 0 4px 0; }
        .meta { font-size: 9px; color: #6B7280; margin-bottom: 16px; }
        .meta b { color: #1F2937; }
        h2 { font-size: 12.5px; color: #00A859; text-transform: uppercase; letter-spacing: 0.5px;
             border-bottom: 2px solid #D1FAE5; padding-bottom: 4px; margin: 18px 0 8px 0; }
        h3 { font-size: 10.5px; color: #1F2937; margin: 10px 0 4px 0; }
        p { margin: 0 0 8px 0; text-align: justify; }
        .indice { background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 4px; padding: 10px 14px; }
        .indice ol { margin: 0; padding-left: 16px; }
        .indice li { margin-bottom: 3px; }
        .ejemplo { background: #F9FAFB; border-left: 3px solid #00A859; padding: 10px 14px; margin-top: 6px; }
        .ejemplo p { font-size: 10px; margin: 0 0 6px 0; }
        .ejemplo h1, .ejemplo h2, .ejemplo h3, .ejemplo h4 { font-size: 10.5px; color: #1F2937; text-transform: none;
            letter-spacing: 0; border-bottom: none; padding-bottom: 0; margin: 8px 0 4px 0; }
        .ejemplo ul, .ejemplo ol { margin: 0 0 6px 0; padding-left: 16px; }
        .ejemplo li { font-size: 10px; margin-bottom: 2px; }
        .ejemplo strong { color: #1F2937; }
        .mermaid-box { background: #1F2937; color: #D1FAE5; padding: 10px 14px; border-radius: 4px;
                       font-family: 'Courier New', monospace; font-size: 8.5px; white-space: pre-wrap; }
        .pasos-esquema { list-style: none; padding: 0; margin: 0; counter-reset: paso; }
        .pasos-esquema li { position: relative; padding: 8px 12px 8px 34px; margin-bottom: 6px;
                             background: #F9FAFB; border-left: 3px solid #00A859; border-radius: 0 4px 4px 0;
                             font-size: 10px; counter-increment: paso; }
        .pasos-esquema li:before { content: counter(paso); position: absolute; left: 8px; top: 8px;
                                    width: 16px; height: 16px; border-radius: 50%; background: #00A859; color: #fff;
                                    font-size: 8.5px; font-weight: bold; text-align: center; line-height: 16px; }
        .tabla-esquema { width: 100%; border-collapse: collapse; font-size: 9.5px; }
        .tabla-esquema th { background: #1F2937; color: #fff; text-align: left; padding: 6px 8px;
                             text-transform: uppercase; font-size: 8px; letter-spacing: 0.4px; }
        .tabla-esquema td { padding: 6px 8px; border-bottom: 1px solid #E5E7EB; vertical-align: top; }
        .tabla-esquema tr:nth-child(even) td { background: #F9FAFB; }
        .prototipo-titulo { font-size: 10px; font-weight: bold; color: #1F2937; margin-bottom: 6px; }
        .prototipo-img { width: 100%; max-height: 260px; border: 1px solid #E5E7EB; border-radius: 4px; }
        .checklist { list-style: none; padding: 0; margin: 0; }
        .checklist li { padding: 5px 0 5px 20px; border-bottom: 1px solid #F3F4F6; position: relative; }
        .checklist li:before { content: ""; position: absolute; left: 2px; top: 9px; width: 8px; height: 8px;
                                border: 1.5px solid #00A859; border-radius: 2px; }
        .herramientas { padding: 0; margin: 0; }
        .herramientas li { padding: 4px 0 4px 14px; border-left: 2px solid #BFDBFE; margin-bottom: 4px; font-size: 10px; }
        .herramientas b { color: #2563EB; }
        .footer-nota { margin-top: 22px; padding-top: 8px; border-top: 1px solid #E5E7EB; font-size: 8px; color: #9CA3AF; }
    </style>
</head>
<body>
    <div class="banda">Entregable final &middot; {{ $meta['ciclo'] ?? 'Formación Profesional' }}</div>

    <h1>{{ $titulo }}</h1>
    <div class="meta">
        Equipo <b>{{ $meta['equipo'] }}</b> &middot; para <b>{{ $meta['empresa'] }}</b>
        &middot; {{ $meta['ciclo'] }} &middot; {{ $meta['fecha'] }}
    </div>

    <p>{{ $introduccion }}</p>

    @if(!empty($indice))
        <h2>Índice</h2>
        <div class="indice">
            <ol>
                @foreach($indice as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ol>
        </div>
    @endif

    @if(!empty($ejemploTitulo))
        <h2>Ejemplo de solución entregada</h2>
        <h3>{{ $ejemploTitulo }}</h3>
        {{-- La IA a veces devuelve el contenido en Markdown pese a pedirle texto plano —
             se convierte a HTML en vez de imprimir los símbolos ##/** en crudo, así
             queda bien maquetado tanto si viene en Markdown como en texto normal. --}}
        <div class="ejemplo">{!! \Illuminate\Support\Str::markdown($ejemploContenido ?? '') !!}</div>
    @endif

    {{-- Esquema del proceso: 3 formatos posibles (asignado por equipo, no siempre el
         mismo) para que los 15 PDFs no se vean todos calcados con el mismo bloque de
         código Mermaid — 'mermaid' (diagrama en bloque de código), 'pasos' (lista
         numerada) o 'tabla' (fase/qué se hace/herramienta). --}}
    @if(!empty($esquema['contenido']))
        <h2>Esquema del proceso / solución</h2>

        @if($esquema['tipo'] === 'pasos')
            <ol class="pasos-esquema">
                @foreach($esquema['contenido'] as $paso)
                    <li>{{ $paso }}</li>
                @endforeach
            </ol>
        @elseif($esquema['tipo'] === 'tabla')
            <table class="tabla-esquema">
                <thead>
                    <tr><th>Fase</th><th>Qué se hace</th><th>Herramienta / responsable</th></tr>
                </thead>
                <tbody>
                    @foreach($esquema['contenido'] as $fila)
                        <tr>
                            <td>{{ $fila['fase'] ?? '' }}</td>
                            <td>{{ $fila['que_se_hace'] ?? '' }}</td>
                            <td>{{ $fila['herramienta'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="mermaid-box">{{ $esquema['contenido'] }}</div>
        @endif
    @endif

    @if(!empty($imagenBase64))
        <h2>Prototipo</h2>
        @if(!empty($imagenTitulo))
            <p class="prototipo-titulo">{{ $imagenTitulo }}</p>
        @endif
        <img class="prototipo-img" src="data:image/png;base64,{{ $imagenBase64 }}" alt="Prototipo">
    @endif

    @if(!empty($pasos) || !empty($herramientas))
        <h2>Anexo de implementación</h2>
        @if(!empty($pasos))
            <h3>Cómo ponerlo en marcha</h3>
            <ul class="checklist">
                @foreach($pasos as $paso)
                    <li>{{ $paso }}</li>
                @endforeach
            </ul>
        @endif
        @if(!empty($herramientas))
            <h3>Herramientas propuestas</h3>
            <ul class="herramientas">
                @foreach($herramientas as $h)
                    <li>{{ $h }}</li>
                @endforeach
            </ul>
        @endif
    @endif

    <div class="footer-nota">Entregable presentado por el equipo {{ $meta['equipo'] }} a {{ $meta['empresa'] }} &middot; Aprendizaje Basado en Retos, DuaLab.</div>
</body>
</html>
