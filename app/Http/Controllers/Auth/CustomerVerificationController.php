<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Services\CustomerVerificationService;
use Illuminate\Http\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
class CustomerVerificationController extends Controller {
    public function show(Request $request) {
        if ($request->user()->hasVerifiedEmail() || !$request->user()->tieneRol('user', 'admin', 'operador')) { return redirect()->route('dashboard'); }
        return response()->view('auth.verify-email')->header('Cache-Control','private, no-store');
    }
    public function send(Request $request, CustomerVerificationService $service) {
        try { $service->send($request->user()); }
        catch (TransportExceptionInterface $e) { return back()->withErrors(['code'=>\App\Services\MailDeliveryIssue::message($e)]); }
        return redirect()->route('verification.notice')->with('status','Enviamos un código a tu correo. Revisa también la carpeta de spam.');
    }
    public function verify(Request $request, CustomerVerificationService $service) {
        $data=$request->validate(['code'=>'required|digits:6']);
        if (!$service->verify($request->user(),$data['code'])) {
            return back()->withErrors(['code'=>'El código es incorrecto, venció o agotaste los cinco intentos. Solicita uno nuevo.']);
        }
        $request->user()->refresh();
        $request->session()->regenerate();
        return redirect()->intended(route($request->user()->esUsuario() ? 'reservas.index' : 'admin.dashboard'))->with('status','Cuenta activada. Ya puedes ingresar con tu contraseña.');
    }
}
