@extends('layouts.admin')

@section('title', 'Nueva tarifa - Parke’o')
@section('page-title', 'Nueva tarifa')
@section('page-subtitle', 'Selecciona vehículo y configura una de las tres tarifas disponibles.')
@push('styles') @vite('resources/css/pages/abonados.css') @endpush

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
