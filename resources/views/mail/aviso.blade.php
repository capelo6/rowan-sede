<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $asunto }}</title></head>
<body style="margin:0;padding:0;background:#f5f6f8;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f6f8;">
    <tr><td align="center" style="padding:24px 12px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border:1px solid #c4c9d0;border-radius:6px;">
            <tr><td style="background:#1f3a5f;color:#ffffff;padding:16px 24px;font-size:18px;font-weight:bold;">{{ $organismo }} · Sede electrónica</td></tr>
            <tr><td style="padding:24px;font-size:16px;line-height:1.5;">
                <h1 style="font-size:20px;color:#1f3a5f;margin:0 0 16px;">{{ $asunto }}</h1>
                @foreach($lineas as $linea)<p style="margin:0 0 12px;">{{ $linea }}</p>@endforeach
                @if($enlace)
                    <p style="margin:24px 0;"><a href="{{ $enlace }}" style="display:inline-block;background:#0b5cad;color:#ffffff;padding:12px 20px;border-radius:6px;text-decoration:none;font-weight:bold;">Ir a la sede electrónica</a></p>
                @endif
                <p style="margin:24px 0 0;font-size:13px;color:#4a4a4a;">Este es un aviso automático. No responda a este correo. Para acceder necesitará su certificado electrónico.</p>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
