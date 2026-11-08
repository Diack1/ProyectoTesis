@extends('layouts.admin')
@section('page-title','Registrar salida')
@section('page-subtitle','Escanea el QR del ticket para abrir su espacio sin iniciar un cobro.')
@push('styles') @vite('resources/css/pages/qr-salida.css') @endpush
@section('content')
<div class="qr-scan-overlay"><section class="qr-scan-dialog card" aria-labelledby="qr-scan-title"><div class="qr-scan-heading"><h2 id="qr-scan-title">Buscar ticket por QR</h2><a href="{{ route('admin.dashboard') }}" aria-label="Cerrar escáner">×</a></div><p>Usa la cámara o una foto del ticket. Al reconocerlo verás sus datos y podrás elegir si imprimir o cobrar.</p><video id="qr-video" playsinline muted hidden></video><canvas id="qr-canvas" hidden></canvas><div class="qr-scan-actions"><button type="button" class="btn btn-primary" id="qr-camera">Usar cámara</button><label class="btn btn-secondary" for="qr-photo">Agregar foto</label><input type="file" id="qr-photo" accept="image/*" hidden></div><p id="qr-status" role="status" aria-live="polite">La cámara solicitará permiso al activarla.</p><a href="{{ route('admin.estadias.index') }}#buscar">Buscar por placa o número de ticket</a></section></div>
@endsection
@push('scripts') @vite('resources/js/qr-salida.js') @endpush
