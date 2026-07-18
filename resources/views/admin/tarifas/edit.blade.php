@extends('layouts.admin')

@section('title', 'Editar tarifa - Cochera Tentación')
@section('page-title', 'Editar tarifa')
@section('page-subtitle', 'Actualiza los datos de la tarifa y sus condiciones de aplicación')

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
    <form action="{{ route('admin.tarifas.update', $tarifa) }}" method="POST">
        @csrf
        @method('PUT')

        @include('admin.tarifas._form', ['tarifa' => $tarifa])

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                Actualizar tarifa
            </button>

            <a href="{{ route('admin.tarifas.index') }}" class="btn btn-secondary">
                Cancelar
            </a>
        </div>
    </form>
</div>

@endsection