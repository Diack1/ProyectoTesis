@if(session('success'))<div class="alert-box alert-success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="errors-box" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
