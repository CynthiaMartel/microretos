{{-- Plantilla común de los correos de la app (misma que la del frontoffice) --}}
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DUALAB</title>
</head>
<body style="margin:0;padding:0;background:#EDF4FA;font-family:Arial,Helvetica,sans-serif;color:#1F2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EDF4FA;padding:24px 12px;">
  <tr><td align="center">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;">
      <tr><td style="padding:24px 32px 16px;">
        <span style="font-size:26px;font-weight:900;letter-spacing:-0.5px;color:#17283E;">DUA</span><span style="font-size:26px;font-weight:900;letter-spacing:-0.5px;color:#275d8a;">LAB</span>
      </td></tr>
      <tr><td>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
          <td height="5" style="background:#3072AA;font-size:0;line-height:0;">&nbsp;</td>
          <td height="5" style="background:#509928;font-size:0;line-height:0;">&nbsp;</td>
          <td height="5" style="background:#FF8920;font-size:0;line-height:0;">&nbsp;</td>
          <td height="5" style="background:#19A7A8;font-size:0;line-height:0;">&nbsp;</td>
        </tr></table>
      </td></tr>

      <tr><td style="padding:28px 32px 8px;font-size:15px;line-height:1.6;">
        @yield('contenido')
      </td></tr>

      <tr><td style="padding:24px 32px 28px;font-size:12px;line-height:1.5;color:#6B7280;border-top:1px solid #EEF2F7;">
        @yield('pie')
        <p style="margin:12px 0 0;">© {{ date('Y') }} DUALAB · Gran Canaria, España</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
