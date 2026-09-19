@props(['name'=>'parking'])
@php
$paths = [
'parking'=>'M8 20V4h5a5 5 0 0 1 0 10H8 M3 3h18v18H3z',
'car'=>'M5 10l2-6h10l2 6 M3 10h18v8H3z M5 18v2 M19 18v2 M6 14h2 M16 14h2',
'grid'=>'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
'clock'=>'M12 8v5l3 2 M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0',
'arrow'=>'M4 12h16 M14 6l6 6-6 6',
'entry'=>'M3 12h12 M10 7l5 5-5 5 M14 3h7v18h-7',
'exit'=>'M14 12H3 M8 7l-5 5 5 5 M14 3h7v18h-7',
'wallet'=>'M3 6h18v15H3z M3 6V3h15v3 M16 11h5v5h-5z',
'ticket'=>'M3 4h18v6a2 2 0 0 0 0 4v6H3v-6a2 2 0 0 0 0-4z M15 4v3 M15 10v4 M15 17v3',
'check'=>'M5 12l4 4L19 6',
'shield'=>'M12 3l8 3v6c0 5-8 9-8 9s-8-4-8-9V6z M8 12l3 3 5-6',
'pin'=>'M12 22S4 15 4 9a8 8 0 1 1 16 0c0 6-8 13-8 13z M15 9a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
'users'=>'M16 21v-3a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v3 M13 6a4 4 0 1 1-8 0 4 4 0 0 1 8 0 M17 3a4 4 0 0 1 0 8 M22 21v-3a4 4 0 0 0-3-4',
'settings'=>'M12 3v3 M12 18v3 M3 12h3 M18 12h3 M5 5l2 2 M17 17l2 2 M5 19l2-2 M17 7l2-2 M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
'chart'=>'M4 3v18h17 M8 16v-5 M13 16V6 M18 16v-8',
'search'=>'M21 21l-5-5 M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0',
'print'=>'M6 9V2h12v7 M6 18H3V9h18v9h-3 M6 14h12v8H6z',
'bell'=>'M18 8a6 6 0 0 0-12 0c0 7-3 8-3 8h18s-3-1-3-8 M10 20h4',
'plus'=>'M12 4v16 M4 12h16',
'menu'=>'M3 6h18 M3 12h18 M3 18h18',
];
@endphp
<svg {{ $attributes->class(['icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['parking'] }}"/></svg>
