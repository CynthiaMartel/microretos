@extends('emails.layout')

{{-- Aviso interno: el docente ha enviado su solicitud de retos (SolicitudRetoRespondidaMail) --}}
@section('contenido')
  <h1 style="margin:0 0 16px;font-size:22px;color:#17283E;">¡Hola, {{ $destinatario }}!</h1>

  <p style="margin:0 0 12px;"><strong>{{ $docente }}</strong> ({{ $centro }}) ha enviado la solicitud de retos que le mandaste.</p>
  <p style="margin:0 0 12px;">Ya puedes leer sus respuestas, elegir la empresa y abrir el generador desde DuaLab Central.</p>

  <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
    <tr><td style="border-radius:999px;background:#509928;">
      <a href="{{ $enlace }}" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;">Abrir la solicitud</a>
    </td></tr>
  </table>
@endsection

@section('pie')
  <p style="margin:0;">Recibes este aviso porque enviaste la solicitud desde tu cuenta de DuaLab Central.</p>
@endsection
