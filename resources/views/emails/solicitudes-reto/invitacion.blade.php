@extends('emails.layout')

{{-- Invitación al docente (SolicitudRetoMail). Sale desde info@ por la web, que limpia el HTML con
     HTMLPurifier (perfil 'correo'): sin border-radius ni display, así que el botón destaca por tamaño,
     color (azul de centros) y por ir centrado en su propia celda, no por la forma. --}}
@section('contenido')
  <h1 style="margin:0 0 16px;font-size:22px;color:#17283E;">¡Hola, {{ $docente }}!</h1>

  <p style="margin:0 0 12px;">Somos DuaLab. Te escribimos para preparar de forma personalizada y más eficiente los retos para tu alumnado.</p>
  <p style="margin:0 0 12px;">Solo necesitamos que nos cuentes qué necesitas trabajar. Te dejamos el enlace: haz clic en el siguiente botón.</p>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:28px 0;">
    <tr><td align="center">
      <table role="presentation" cellpadding="0" cellspacing="0" align="center">
        <tr><td align="center" bgcolor="#3072AA" style="background:#3072AA;padding:18px 36px;">
          <a href="{{ $enlace }}" style="font-size:17px;font-weight:bold;color:#ffffff;text-decoration:none;letter-spacing:0.2px;">Contarnos qué necesitas trabajar &rarr;</a>
        </td></tr>
      </table>
    </td></tr>
  </table>

  <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 8px;">
    <tr><td style="padding:4px 0;font-size:15px;"><strong style="color:#3072AA;">1.</strong> Tus datos y los de tu centro.</td></tr>
    <tr><td style="padding:4px 0;font-size:15px;"><strong style="color:#3072AA;">2.</strong> La familia, el ciclo, el curso, los módulos y los RA/CE que quieres trabajar (puedes pedir varios).</td></tr>
    <tr><td style="padding:4px 0;font-size:15px;"><strong style="color:#3072AA;">3.</strong> Cómo es tu grupo y cuándo haríais la sesión en el aula.</td></tr>
    <tr><td style="padding:4px 0;font-size:15px;"><strong style="color:#3072AA;">4.</strong> Qué tipo de propuesta quieres trabajar.</td></tr>
  </table>

  <p style="margin:0 0 12px;">La empresa la elegimos nosotros. Cuando tengamos los retos, te avisaremos por correo.</p>

  <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:20px 0;background:#F6FAFC;">
    <tr><td style="padding:16px 20px;font-size:14px;">
      <p style="margin:0 0 6px;font-weight:bold;color:#17283E;">Resumen</p>
      <p style="margin:0;">Solicitud de retos para tu alumnado</p>
      <p style="margin:0;">Unos 10 minutos · puedes dejarla a medias y seguir después con el mismo enlace</p>
      @if ($caduca)<p style="margin:0;">El enlace vale hasta el {{ $caduca }}</p>@endif
    </td></tr>
  </table>

  <p style="margin:0 0 12px;">Si tienes cualquier duda, responde a este correo y te contestaremos.</p>

  <p style="margin:0 0 4px;">Un saludo,</p>
  <p style="margin:0;font-weight:bold;color:#17283E;">El equipo de DuaLab</p>
@endsection

@section('pie')
  <p style="margin:0 0 8px;">Si el botón no funciona, copia esta dirección en el navegador: <span style="word-break:break-all;">{{ $enlace }}</span></p>
  <p style="margin:0;">Recibes este correo porque has facilitado esta dirección al equipo de DuaLab. El enlace es personal: no lo reenvíes. Si no esperabas este correo, puedes ignorarlo.</p>
@endsection
