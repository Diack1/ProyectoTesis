<?php

namespace App\Services;

use App\Models\Estadia;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\URL;

class TicketQrService
{
    public function path(Estadia $estadia): string
    {
        return URL::signedRoute('admin.estadias.identificar',
            ['estadia'=>$estadia->id, 'codigo'=>$estadia->codigo_ticket], absolute:false);
    }

    public function svg(Estadia $estadia): string
    {
        $url = request()->getSchemeAndHttpHost().$this->path($estadia);
        return (new Writer(new ImageRenderer(new RendererStyle(240, 2), new SvgImageBackEnd())))->writeString($url);
    }
}
