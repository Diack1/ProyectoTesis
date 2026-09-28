@extends('layouts.admin')
@section('page-title', 'Reconocimiento de placas')
@section('page-subtitle', 'Reconoce matrículas desde la cámara o una fotografía y revisa el cliente asociado.')
@push('styles')
@vite('resources/css/pages/placas.css')
@endpush
@section('content')
<p><a class="btn btn-secondary" href="{{ route('admin.estadias.create') }}">Escribir placa y registrar entrada</a> <a href="{{ route('admin.estadias.index') }}">Volver a entradas y salidas</a></p>
@include('admin.estadias.messages')
@include('admin.placas.camara')
<section class="admin-page-card">
    <h2>1. Selecciona una fotografía</h2>
    <p>Usa una foto nítida del vehículo, con la placa visible. Se procesa localmente en el equipo que ejecuta el sistema.</p>
    @unless($disponible)<div class="alert alert-info">El motor de reconocimiento está pendiente de instalación. Consulta <code>vision/README.md</code>.</div>@endunless
    <form action="{{ route('admin.placas.analizar') }}" method="post" enctype="multipart/form-data" id="form-placas">
        @csrf
        <div class="form-group"><label for="imagen">Foto del vehículo</label><input type="file" id="imagen" name="imagen" accept="image/jpeg,image/png,image/webp" required aria-describedby="ayuda-imagen"><small id="ayuda-imagen">JPG, PNG o WebP · máximo 8 MB y 20 megapíxeles · hasta 6000 píxeles por lado.</small></div>
        <button class="btn btn-primary" @disabled(!$disponible)>Analizar fotografía</button>
        <p id="estado-analisis" role="status" hidden>Buscando placas y leyendo los caracteres. Puede tardar unos segundos…</p>
    </form>
    <p class="vision-note">Esta prueba no registra tickets ni modifica reservas o pagos. El original se elimina al terminar; la vista reducida y los resultados quedan temporalmente en tu sesión.</p>
</section>
@if($resultado)
<section class="admin-page-card">
    <div class="panel-heading"><h2>2. Revisa el resultado</h2><form method="post" action="{{ route('admin.placas.limpiar') }}">@csrf @method('DELETE')<button class="btn btn-secondary">Borrar prueba</button></form></div>
    <img class="vision-preview" src="data:image/jpeg;base64,{{ $resultado['analysis']['preview'] }}" alt="Fotografía con las placas detectadas numeradas">
    <p>Tiempo de análisis: {{ number_format($resultado['analysis']['elapsed_ms'] / 1000, 2) }} s. Las puntuaciones del modelo no garantizan que la matrícula sea correcta.</p>
    @if($resultado['confirmed'])<div class="alert alert-success" role="status">Placa revisada: <strong>{{ $resultado['confirmed']['plate'] }}</strong> · detección {{ $resultado['confirmed']['candidate'] + 1 }}. Confirmación válida solo para esta prueba.</div>@endif
    @if(empty($resultado['analysis']['candidates']))
    <div class="alert alert-info">No se detectó una placa. Prueba una fotografía más cercana, de frente y con mejor iluminación. Puedes seguir usando el registro manual de ingresos.</div>
    @endif
    @if($resultado['analysis']['truncated'])<p>Se muestran las primeras 10 detecciones. Usa una foto de un solo vehículo para revisar con mayor claridad.</p>@endif
    <div class="vision-candidates">
    @foreach($resultado['analysis']['candidates'] as $index => $candidate)
    <article class="vision-candidate">
        <h3>Detección {{ $index + 1 }}</h3>
        <img class="vision-crop" src="data:image/jpeg;base64,{{ $candidate['crop'] }}" alt="Recorte de la placa detectada número {{ $index + 1 }}">
        <dl class="ticket-details"><div><dt>Detección YOLO</dt><dd>{{ number_format($candidate['detection_confidence'] * 100, 1) }} %</dd></div><div><dt>Lectura OCR (promedio)</dt><dd>{{ $candidate['ocr_confidence'] !== null ? number_format($candidate['ocr_confidence'] * 100, 1).' %' : 'Sin lectura' }}</dd></div></dl>
        <p>Sugerencia: <strong>{{ $candidate['text'] ?: 'No se pudieron leer los caracteres' }}</strong></p>
        @if(strlen($candidate['text']) < 5 || ($candidate['ocr_confidence'] ?? 0) < 0.8)
        <p class="alert alert-info">Lectura dudosa o incompleta. Si no distingues todos los caracteres, prueba una fotografía más cercana antes de confirmar.</p>
        @endif
        <form method="post" action="{{ route('admin.placas.confirmar') }}">
            @csrf
            <input type="hidden" name="prueba_id" value="{{ $resultado['id'] }}"><input type="hidden" name="candidato" value="{{ $index }}">
            <div class="form-group"><label for="placa-{{ $index }}">Matrícula revisada</label><input id="placa-{{ $index }}" name="placa" value="{{ old('candidato') !== null && (int) old('candidato') === $index ? old('placa') : $candidate['text'] }}" maxlength="12" required autocomplete="off" spellcheck="false"></div>
            <label class="checkbox-row"><input type="checkbox" name="revisado" value="1" required>He comparado los caracteres con la fotografía.</label>
            <button class="btn btn-primary">Confirmar esta placa</button>
        </form>
    </article>
    @endforeach
    </div>
</section>
@endif
@endsection
@push('scripts')
<script src="{{ asset('js/placas.js') }}" defer></script>
<script src="{{ asset('js/camara-placas.js') }}" type="module"></script>
@endpush
