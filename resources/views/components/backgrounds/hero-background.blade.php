{{-- Native decorative light streaks, independent of the Home content. Not the React Bits component. --}}
@props(['variant' => 'light-droplets'])
<div class="hero-background site-background" data-hero-background="{{ $variant }}" aria-hidden="true">
    @if($variant === 'light-droplets')
        @for($i = 0; $i < 30; $i++)
            <i class="hero-droplet" style="--x:{{ ($i * 37 + 7) % 100 }}%;--duration:{{ 12 + $i % 9 }}s;--delay:-{{ ($i * 7) % 21 }}s;--length:{{ 90 + ($i * 13) % 110 }}px"></i>
        @endfor
    @endif
</div>
