@extends('layouts.admin')
@section('page-title','Comprobante de pago')
@section('page-subtitle','Abono registrado y pagado')
@push('styles') @vite('resources/css/pages/cobro.css') @endpush
@section('content')
@include('admin.estadias.comprobante-pagado',['urlImprimir'=>route('admin.impresoras.abono',$abono)])
@endsection
