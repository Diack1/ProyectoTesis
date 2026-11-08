@extends('layouts.admin')
@section('page-title','Editar usuario')
@section('page-subtitle',$user->name)
@section('content')
@if($errors->any())<div class="errors-box"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="form-card"><form method="post" action="{{ route('superadmin.admins.update',$user) }}">@csrf @method('PUT')
<div class="form-group"><label>Nombre completo<input class="form-control" name="name" required maxlength="255" value="{{ old('name',$user->name) }}"></label></div>
<div class="form-group"><label>Correo<input class="form-control" type="email" name="email" required value="{{ old('email',$user->email) }}"></label></div>
<div class="form-group"><label>Rol<select class="form-control" name="role"><option value="junior" @selected(old('role',$user->role)!=='super_admin')>Junior</option><option value="super_admin" @selected(old('role',$user->role)==='super_admin')>Administrador</option></select></label></div>
<div class="form-actions"><button class="btn btn-primary">Guardar cambios</button><a class="btn btn-secondary" href="{{ route('superadmin.dashboard') }}">Volver</a></div>
</form></div>
<div class="form-card"><h2>Restablecer contraseña</h2><p>El usuario podrá ingresar con la nueva contraseña asignada.</p><form method="post" action="{{ route('superadmin.admins.password',$user) }}">@csrf @method('PUT')
<div class="form-grid"><div class="form-group"><label>Nueva contraseña<input class="form-control" type="password" name="password" required minlength="12" autocomplete="new-password"></label></div><div class="form-group"><label>Confirmar contraseña<input class="form-control" type="password" name="password_confirmation" required minlength="12" autocomplete="new-password"></label></div></div>
<button class="btn btn-secondary">Restablecer</button></form></div>
@endsection
