@extends('emails.layout')

{{-- Solo datos no libres (nombre, tipo): nunca el mensaje del formulario, para no
     reenviar texto arbitrario desde el dominio a cualquier dirección. --}}
@section('contenido')
  <h1 style="margin:0 0 16px;font-size:22px;color:#17283E;">¡Hola, {{ $nombre }}!</h1>
  <p style="margin:0 0 12px;">Hemos recibido tu consulta en el soporte de DUALAB. Te responderemos a este correo lo antes posible, normalmente en uno o dos días laborables.</p>

  <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:20px 0;background:#F6FAFC;border-radius:12px;">
    <tr><td style="padding:16px 20px;font-size:14px;">
      <p style="margin:0 0 6px;font-weight:bold;color:#17283E;">Tipo de consulta</p>
      <p style="margin:0;">{{ $tipoTexto }}</p>
    </td></tr>
  </table>

  <p style="margin:0 0 4px;">Un saludo,</p>
  <p style="margin:0;font-weight:bold;color:#17283E;">Soporte DUALAB</p>
@endsection

@section('pie')
  <p style="margin:0;">Recibes este correo porque se ha enviado el formulario de soporte de DUALAB con esta dirección. Si no has sido tú, puedes ignorarlo.</p>
@endsection
