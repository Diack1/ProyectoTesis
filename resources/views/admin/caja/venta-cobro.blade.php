@extends('layouts.admin')
@section('page-title',$tipo==='pagos'?'Modificar pagos':'Anular y corregir')
@section('page-subtitle','Comprobante '.$ventaCorreccion->numero)
@push('styles') @vite('resources/css/pages/cobro.css') @endpush
@section('content')
@include('admin.estadias.messages')
@include('admin.estadias.cobro-salida')
@endsection
@push('scripts') @vite('resources/js/cobro.js') @endpush
