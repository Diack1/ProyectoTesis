@extends('layouts.admin')

@section('title', 'Nueva tarifa - Cochera Tentación')
@section('page-title', 'Nueva tarifa')
@section('page-subtitle', 'Registra una tarifa para auto, moto, horario, duración y condiciones de cobro')

@section('content')

@if($errors->any())
<div class="errors-box">
    <strong>Corrige los siguientes errores:</strong>
    <ul>
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="form-card">
    <form action="{{ route('admin.tarifas.store') }}" method="POST">
        @csrf

        @include('admin.tarifas._form', ['tarifa' => null])

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                Guardar tarifa
            </button>

            <a href="{{ route('admin.tarifas.index') }}" class="btn btn-secondary">
                Cancelar
            </a>
        </div>
    </form>
</div>

@endsection