<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $title }} · Parke’o</title></head>
<body style="margin:0;padding:0;background-color:#eef3f8;color:#172638;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">{{ $title }}. Tu código vence en {{ $minutes }} minutos.</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#eef3f8;">
        <tr><td align="center" style="padding:28px 12px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;border:1px solid #dbe4ef;border-radius:16px;background-color:#ffffff;overflow:hidden;">
                <tr><td style="padding:24px;background-color:#0d1b2c;border-bottom:3px solid #187cff;">
                    <div style="font-size:28px;font-weight:700;color:#ffffff;">Parke<span style="color:#53a4ff;">’o</span></div>
                    <div style="margin-top:5px;font-size:13px;color:#bdcede;">Tu espacio, antes de llegar.</div>
                </td></tr>
                <tr><td style="padding:28px 24px;">
                    <h1 style="margin:0 0 16px;font-size:24px;line-height:1.3;color:#172638;">{{ $title }}</h1>
                    <p style="margin:0 0 24px;font-size:16px;line-height:1.6;color:#46586c;">{{ $intro }}</p>
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#edf5ff;border:1px solid #b9d8ff;border-radius:12px;">
                        <tr><td align="center" style="padding:22px 8px;">
                            <div style="font-size:12px;font-weight:700;letter-spacing:1px;color:#375878;">TU CÓDIGO</div>
                            <div style="margin-top:10px;font-family:Consolas,monospace;font-size:34px;line-height:1.3;font-weight:700;letter-spacing:6px;color:#125bc1;">{{ $code }}</div>
                            <div style="margin-top:12px;font-size:14px;color:#375878;">Vence en {{ $minutes }} minutos · Un solo uso</div>
                        </td></tr>
                    </table>
                    <p style="margin:24px 0 0;font-size:14px;line-height:1.6;color:#46586c;">{{ $notice }}</p>
                    <p style="margin:24px 0 0;font-size:14px;color:#172638;">El equipo de Parke’o</p>
                </td></tr>
                <tr><td style="padding:18px 24px;border-top:1px solid #e3eaf2;font-size:12px;line-height:1.6;color:#62748a;">Mensaje automático de seguridad de Parke’o.</td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
