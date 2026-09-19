@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert"><strong>Revisa estos datos:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
