<div class="duration-picker" data-duration-picker>
    <div class="duration-stepper">
        <button type="button" data-duration-step="-1" aria-label="Reducir una hora" hidden>−</button>
        <select id="{{ $id }}" name="{{ $name }}" required aria-describedby="{{ $id }}-help">
            @foreach(\App\Services\DuracionReserva::minutos() as $minutes)
                <option value="{{ $minutes }}" @selected((int)$selected === $minutes)>{{ \App\Services\TarifaResumen::tiempo($minutes) }}</option>
            @endforeach
        </select>
        <button type="button" data-duration-step="1" aria-label="Aumentar una hora" hidden>+</button>
    </div>
    <div class="duration-presets" aria-label="Duraciones frecuentes" hidden>
        @foreach([1,4,8,12] as $hours)
            <button type="button" data-duration-value="{{ $hours * 60 }}" aria-pressed="false">{{ $hours }} h</button>
        @endforeach
    </div>
    <small id="{{ $id }}-help">De 1 a 12 horas · El total se actualiza al elegir.</small>
</div>
@once
@push('scripts')
<script src="/js/duration-picker.js?v={{ filemtime(public_path('js/duration-picker.js')) }}" defer></script>
@endpush
@push('styles')
<style>
.duration-picker{min-width:0;width:100%}.duration-stepper{display:flex;align-items:center;gap:8px}.duration-stepper select{min-width:0;flex:1;width:0!important;margin:0!important}.duration-stepper button{flex:0 0 44px;min-height:44px;font-size:22px}.duration-picker button{border:1px solid #456079;border-radius:10px;background:#162536;color:#eaf3ff;cursor:pointer}.duration-picker button:disabled{opacity:.4;cursor:default}.duration-picker button:focus-visible{outline:3px solid #70baff;outline-offset:2px}.duration-presets{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}.duration-presets button{flex:1;min-width:44px;min-height:44px;padding:6px}.duration-presets button[aria-pressed=true]{background:#176cdb;border-color:#78baff}.duration-picker small{display:block;margin-top:8px;line-height:1.4}.duration-picker [hidden]{display:none!important}
</style>
@endpush
@endonce
