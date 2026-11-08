@extends('layouts.admin')
@section('page-title','Cobro del abono')
@section('page-subtitle',$abonoDraft['nombre'].' · '.$espacioAbono->codigo.' · '.$abonoDraft['meses'].' mes(es)')
@push('styles') @vite(['resources/css/pages/cobro.css','resources/css/pages/abonados.css']) @endpush
@section('content')
@include('admin.estadias.messages')
<section class="card subscriber-checkout">@include('admin.estadias.cobro-salida')</section>
@endsection
@push('scripts') @vite('resources/js/cobro.js') @endpush
