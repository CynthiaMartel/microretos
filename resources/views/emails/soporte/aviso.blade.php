@extends('emails.layout')

{{-- {{ }} escapa el HTML: el mensaje es texto libre de un formulario público --}}
@section('contenido')
  <p style="margin:0 0 4px;font-size:12px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:#3072AA;">Consulta de soporte</p>
  <h1 style="margin:0 0 4px;font-size:22px;color:#17283E;">{{ $tipo }}</h1>
  <p style="margin:0 0 20px;color:#6B7280;">{{ $fecha }}</p>

  <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;font-size:14px;border-collapse:collapse;">
    <tr>
      <td style="padding:6px 12px 6px 0;color:#6B7280;vertical-align:top;width:30%;border-bottom:1px solid #EEF2F7;">Nombre</td>
      <td style="padding:6px 0;vertical-align:top;border-bottom:1px solid #EEF2F7;">{{ $solicitud['nombre'] }}</td>
    </tr>
    <tr>
      <td style="padding:6px 12px 6px 0;color:#6B7280;vertical-align:top;border-bottom:1px solid #EEF2F7;">Email</td>
      <td style="padding:6px 0;vertical-align:top;border-bottom:1px solid #EEF2F7;">{{ $solicitud['email'] }}</td>
    </tr>
  </table>

  <p style="margin:20px 0 8px;font-weight:bold;color:#17283E;">Mensaje</p>
  <p style="margin:0;white-space:pre-line;background:#F6FAFC;border-radius:12px;padding:16px 20px;font-size:14px;">{{ $solicitud['mensaje'] }}</p>
@endsection

@section('pie')
  <p style="margin:0;">Aviso automático del formulario de soporte de DUALAB. Responde a este correo para contestar directamente a la persona.</p>
@endsection
