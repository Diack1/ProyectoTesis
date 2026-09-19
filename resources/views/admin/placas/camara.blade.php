<section class="admin-page-card" id="camera-panel" data-frame-url="{{ route('admin.placas.camara') }}" data-client-url="{{ route('admin.placas.cliente') }}" data-csrf="{{ csrf_token() }}">
<div class="panel-heading"><h2>Cámara de entrada</h2><a class="btn btn-secondary" href="{{ route('admin.clientes-vehiculos.index') }}">Clientes y vehículos</a></div>
<p>Conecta la TEROS por USB, enciéndela aquí y selecciona su nombre. Puedes capturar una foto o vigilar la entrada mientras esta página permanece abierta y visible.</p>
<div class="form-group"><label for="camera-device">Cámara</label><select id="camera-device"><option value="">Cámara predeterminada (se mostrarán los nombres al permitir acceso)</option></select></div>
<div class="camera-buttons">
<button class="btn btn-secondary" id="camera-open" type="button">Encender cámara</button>
<button class="btn btn-secondary" id="camera-close" type="button" disabled>Apagar cámara</button>
<button class="btn btn-primary" id="camera-photo" type="button" disabled @if(!$disponible) data-unavailable="1" @endif>Capturar y analizar foto</button>
<button class="btn btn-primary" id="camera-watch" type="button" disabled @if(!$disponible) data-unavailable="1" @endif>Iniciar detección automática</button>
<button class="btn btn-secondary" id="camera-pause" type="button" disabled>Pausar detección</button>
</div>
<p id="camera-status" role="status">Cámara apagada. Solo se solicitará vídeo, sin micrófono.</p>
<video id="camera-video" autoplay muted playsinline hidden aria-label="Vista de la cámara de entrada"></video>
<p class="vision-note">Apunta únicamente al acceso y comprueba que la placa se lea en la imagen. La detección automática avisa después de dos lecturas coincidentes; no acredita un cruce de entrada ni genera tickets automáticamente.</p>
<div id="camera-results" aria-live="polite"></div>
<details><summary>Consultar una matrícula corregida</summary>
<form id="camera-lookup" class="search-form"><label for="camera-corrected">Placa revisada</label><input id="camera-corrected" maxlength="12" required placeholder="ABC-123"><button class="btn btn-secondary">Buscar cliente asociado</button></form>
</details>
</section>
