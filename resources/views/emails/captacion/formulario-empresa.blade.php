@extends('emails.layout')

{{-- Invitación a la entrevista por correo (FormularioEmpresaMail). Mismo formato que los
     correos de los formularios de dualab.es: saludo, texto, resumen y pie. --}}
@section('contenido')
  <h1 style="margin:0 0 16px;font-size:22px;color:#17283E;">¡Hola{{ $contacto ? ', ' . $contacto : '' }}!</h1>

  <p style="margin:0 0 12px;">Soy {{ $remitente }}, del equipo de DuaLab. Como no nos ha dado tiempo a hacer la entrevista en persona, te la envío para que la completes cuando te venga bien{!! $empresa ? ' en nombre de <strong>' . e($empresa) . '</strong>' : '' !!}.</p>
  <p style="margin:0 0 12px;">DuaLab conecta a las empresas con el alumnado de Formación Profesional: con lo que nos cuentes diseñaremos retos reales para que el alumnado trabaje en ellos. Son tres pasos:</p>

  <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 8px;">
    <tr><td style="padding:4px 0;font-size:15px;"><strong style="color:#509928;">1.</strong> Los datos de tu empresa y con qué alumnado te gustaría trabajar.</td></tr>
    <tr><td style="padding:4px 0;font-size:15px;"><strong style="color:#509928;">2.</strong> Siete preguntas sobre vuestro día a día y lo que más trabajo os da.</td></tr>
    <tr><td style="padding:4px 0;font-size:15px;"><strong style="color:#509928;">3.</strong> La firma de las condiciones de participación y la protección de datos.</td></tr>
  </table>

  <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
    <tr><td style="border-radius:999px;background:#509928;">
      <a href="{{ $enlace }}" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;">Completar la entrevista</a>
    </td></tr>
  </table>

  <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:20px 0;background:#F6FAFC;border-radius:12px;">
    <tr><td style="padding:16px 20px;font-size:14px;">
      <p style="margin:0 0 6px;font-weight:bold;color:#17283E;">Resumen</p>
      <p style="margin:0;">Entrevista para colaborar con DuaLab{{ $empresa ? ' · ' . $empresa : '' }}</p>
      <p style="margin:0;">Unos 15 minutos · puedes dejarla a medias y seguir después con el mismo enlace</p>
      @if ($caduca)<p style="margin:0;">El enlace vale hasta el {{ $caduca }}</p>@endif
    </td></tr>
  </table>

  <p style="margin:0 0 12px;">Si tienes cualquier duda, responde a este correo y te contestaré yo.</p>

  <p style="margin:0 0 4px;">Un saludo,</p>
  <p style="margin:0;font-weight:bold;color:#17283E;">{{ $remitente }} · El equipo de DuaLab</p>
@endsection

@section('pie')
  <p style="margin:0 0 8px;">Si el botón no funciona, copia esta dirección en el navegador: <span style="word-break:break-all;">{{ $enlace }}</span></p>
  <p style="margin:0;">Recibes este correo porque has facilitado esta dirección a una persona del equipo de DuaLab. El enlace es personal: no lo reenvíes fuera de tu empresa. Si no esperabas este correo, puedes ignorarlo.</p>
@endsection
