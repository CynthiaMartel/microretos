@extends('emails.layout')

{{-- «Tus retos están listos» (SolicitudRetoRetosListosMail): lo envía DuaLab a mano --}}
@section('contenido')
  <h1 style="margin:0 0 16px;font-size:22px;color:#17283E;">¡Hola, {{ $docente }}!</h1>

  <p style="margin:0 0 12px;">Somos DuaLab. Ya tenemos preparados los retos para tu alumnado a partir de lo que nos contaste.</p>
  @if ($nota)<p style="margin:0 0 12px;white-space:pre-line;">{{ $nota }}</p>@endif

  <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:20px 0;background:#F6FAFC;border-radius:12px;">
    <tr><td style="padding:16px 20px;font-size:14px;">
      <p style="margin:0 0 6px;font-weight:bold;color:#17283E;">{{ count($retos) === 1 ? 'Tu reto' : 'Tus retos' }}</p>
      @foreach ($retos as $titulo)<p style="margin:0;">· {{ $titulo }}</p>@endforeach
    </td></tr>
  </table>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:28px 0;">
    <tr><td align="center">
      <table role="presentation" cellpadding="0" cellspacing="0" align="center">
        <tr><td align="center" bgcolor="#3072AA" style="background:#3072AA;padding:18px 36px;">
          <a href="{{ $enlace }}" style="font-size:17px;font-weight:bold;color:#ffffff;text-decoration:none;letter-spacing:0.2px;">Ver mis retos &rarr;</a>
        </td></tr>
      </table>
    </td></tr>
  </table>

  <p style="margin:0 0 12px;">Si quieres cambiar algo, responde a este correo y te contestaremos.</p>

  <p style="margin:0 0 4px;">Un saludo,</p>
  <p style="margin:0;font-weight:bold;color:#17283E;">El equipo de DuaLab</p>
@endsection

@section('pie')
  <p style="margin:0 0 8px;">Si el botón no funciona, copia esta dirección en el navegador: <span style="word-break:break-all;">{{ $enlace }}</span></p>
  <p style="margin:0;">@if ($caduca)El enlace vale hasta el {{ $caduca }}. @endif Es personal: no lo reenvíes. El enlace que te enviamos antes para la solicitud ya no funciona.</p>
@endsection
