@extends('emails.layout')

{{-- Aviso al comercial: la empresa ha respondido el formulario (FormularioEmpresaRespondidoMail) --}}
@section('contenido')
  <h1 style="margin:0 0 16px;font-size:22px;color:#17283E;">¡Hola, {{ $comercial }}!</h1>

  <p style="margin:0 0 12px;"><strong>{{ $empresa }}</strong> ha completado y firmado la entrevista que le enviaste por correo.</p>
  <p style="margin:0 0 12px;">Ya está pendiente de que DuaLab la valide. Puedes leer sus datos y respuestas en la app comercial.</p>

  <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
    <tr><td style="border-radius:999px;background:#509928;">
      <a href="{{ $enlace }}" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;">Abrir la entrevista</a>
    </td></tr>
  </table>
@endsection

@section('pie')
  <p style="margin:0;">Recibes este aviso porque enviaste el formulario desde tu cuenta comercial de DuaLab.</p>
@endsection
