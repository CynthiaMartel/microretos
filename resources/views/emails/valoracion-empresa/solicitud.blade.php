@extends('emails.layout')

{{-- Enlace a la empresa para ver las soluciones y valorar el reto (ValoracionEmpresaMail), desde Central --}}
@section('contenido')
  <h1 style="margin:0 0 16px;font-size:22px;color:#17283E;">¡Hola, equipo de {{ $empresa }}!</h1>

  <p style="margin:0 0 12px;">Somos DuaLab. El alumnado ha terminado de trabajar el reto que nos planteasteis y ya tenéis sus soluciones.</p>
  @if ($nota)<p style="margin:0 0 12px;white-space:pre-line;">{{ $nota }}</p>@endif

  <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:20px 0;background:#F6FAFC;border-radius:12px;">
    <tr><td style="padding:16px 20px;font-size:14px;">
      <p style="margin:0 0 6px;font-weight:bold;color:#17283E;">El reto</p>
      <p style="margin:0;">{{ $reto }}</p>
    </td></tr>
  </table>

  <p style="margin:0 0 12px;">En el enlace veréis las soluciones de cada grupo y unas pocas preguntas para valorarlas. No hace falta cuenta y se tarda unos minutos. También nos podéis decir si os interesaría seguir con un proyecto en vuestra empresa.</p>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:28px 0;">
    <tr><td align="center">
      <table role="presentation" cellpadding="0" cellspacing="0" align="center">
        <tr><td align="center" bgcolor="#3072AA" style="background:#3072AA;padding:18px 36px;">
          <a href="{{ $enlace }}" style="font-size:17px;font-weight:bold;color:#ffffff;text-decoration:none;letter-spacing:0.2px;">Ver las soluciones &rarr;</a>
        </td></tr>
      </table>
    </td></tr>
  </table>

  <p style="margin:0 0 12px;">Si tenéis cualquier duda, responded a este correo.</p>

  <p style="margin:0 0 4px;">Un saludo,</p>
  <p style="margin:0;font-weight:bold;color:#17283E;">El equipo de DuaLab</p>
@endsection

@section('pie')
  <p style="margin:0 0 8px;">Si el botón no funciona, copiad esta dirección en el navegador: <span style="word-break:break-all;">{{ $enlace }}</span></p>
  <p style="margin:0;">@if ($caduca)El enlace vale hasta el {{ $caduca }}. @endif Solo se puede valorar una vez.</p>
@endsection
