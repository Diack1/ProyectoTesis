@props(['label', 'value', 'icon' => 'chart'])
<div class="management-stat"><span class="management-stat-icon"><x-icon :name="$icon"/></span><div><span>{{ $label }}</span><strong>{{ $value }}</strong></div></div>
