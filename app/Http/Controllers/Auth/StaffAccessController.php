<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\StaffAccessRequest;
use App\Models\User;
use App\Services\StaffAccessService;
use App\Services\SecurityEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class StaffAccessController extends Controller
{
    public function show(Request $request, StaffAccessService $service)
    {
        abort_unless($request->user()->esSuperAdmin(), 403);
        return response()->view('auth.staff-access', ['entry'=>$service->current($request),
            'owner'=>$request->user()->esSuperAdmin(), 'ready'=>$service->mailReady() && $service->owner(), 'retryAfter'=>$service->retryAfter($request->user())])
            ->header('Cache-Control', 'private, no-store');
    }

    public function start(Request $request, StaffAccessService $service)
    {
        try { $service->start($request); }
        catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('staff-access.show')->withErrors($e->errors());
        }
        catch (TransportExceptionInterface $e) {
            return redirect()->route('staff-access.show')->withErrors(['access'=>\App\Services\MailDeliveryIssue::message($e)]);
        }
        return redirect()->route('staff-access.show')->with('status', 'Correo enviado al superadministrador.');
    }

    public function verify(Request $request, StaffAccessService $service)
    {
        $code = $request->validate(['code'=>'required|digits:6'])['code'];
        if (!$service->consume($request, $code)) {
            return back()->withErrors(['access'=>'El código no es válido, se agotaron los intentos o la solicitud venció.']);
        }
        return redirect()->route('admin.dashboard');
    }

}
