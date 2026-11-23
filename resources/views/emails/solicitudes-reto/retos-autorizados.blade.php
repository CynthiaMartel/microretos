@extends('emails.layout')

{{-- «Ya puedes trabajar tus retos» (SolicitudRetoAutorizadosMail): lo envía DuaLab al autorizarlos --}}
@section('contenido')
  <h1 style="margin:0 0 16px;font-size:22px;color:#17283E;">¡Hola, {{ $docente }}!</h1>

  <p style="margin:0 0 12px;">Somos DuaLab. Ya puedes trabajar con tu alumnado {{ count($retos) === 1 ? 'el reto que preparamos' : 'los retos que preparamos' }} a partir de lo que nos contaste: {{ count($retos) === 1 ? 'está' : 'están' }} en la Biblioteca de retos de tu centro.</p>
  @if ($nota)<p style="margin:0 0 12px;white-space:pre-line;">{{ $nota }}</p>@endif

  <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:20px 0;background:#F6FAFC;border-radius:12px;">
    <tr><td style="padding:16px 20px;font-size:14px;">
      <p style="margin:0 0 6px;font-weight:bold;color:#17283E;">{{ count($retos) === 1 ? 'Tu reto' : 'Tus retos' }}</p>
      @foreach ($retos as $reto)<p style="margin:0 0 4px;">· <a href="{{ $reto['enlace'] }}" style="color:#3072AA;font-weight:bold;">{{ $reto['titulo'] }}</a></p>@endforeach
    </td></tr>
  </table>

  <p style="margin:0 0 12px;">Entra con tu cuenta de DuaLab, abre el reto y pulsa <strong>«Trabajar este reto»</strong> para ajustarlo a tu grupo: curso, módulos, criterios y objetivos.</p>
  <p style="margin:0 0 12px;">Si quieres cambiar algo, responde a este correo y te contestaremos.</p>

  <p style="margin:0 0 4px;">Un saludo,</p>
  <p style="margin:0;font-weight:bold;color:#17283E;">El equipo de DuaLab</p>
@endsection

@section('pie')
  <p style="margin:0;">Los enlaces abren DuaLab: necesitas entrar con tu cuenta.</p>
@endsection
