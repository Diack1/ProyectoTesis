<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Services\CustomerVerificationService;
use Illuminate\Http\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
class CustomerVerificationController extends Controller {
    public function show(Request $request) {
        if ($request->user()->hasVerifiedEmail() || !$request->user()->esUsuario()) { return redirect()->route('dashboard'); }
        return response()->view('auth.verify-email')->header('Cache-Control','private, no-store');
    }
    public function send(Request $request, CustomerVerificationService $service) {
        try { $service->send($request->user()); }
        catch (TransportExceptionInterface $e) { return back()->withErrors(['code'=>'No pudimos enviar el correo. Inténtalo nuevamente en un minuto.']); }
        return redirect()->route('verification.notice')->with('status','Enviamos un código a tu correo. Revisa también la carpeta de spam.');
    }
    public function verify(Request $request, CustomerVerificationService $service) {
        $data=$request->validate(['code'=>'required|digits:6']);
        if (!$service->verify($request->user(),$data['code'])) {
            return back()->withErrors(['code'=>'El código es incorrecto, venció o agotaste los cinco intentos. Solicita uno nuevo.']);
        }
        $request->session()->regenerate();
        return redirect()->route('reservas.index')->with('status','Correo verificado. Ya puedes reservar.');
    }
}
