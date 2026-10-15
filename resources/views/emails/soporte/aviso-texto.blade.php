{{-- Texto plano (text/plain): sin escapar HTML, el cliente no lo interpreta. --}}
CONSULTA DE SOPORTE — {!! $tipo !!}
{!! $fecha !!}

Nombre: {!! $solicitud['nombre'] !!}
Email: {!! $solicitud['email'] !!}

MENSAJE
{!! $solicitud['mensaje'] !!}

--
Aviso automático del formulario de soporte de DUALAB. Responde a este correo para contestar directamente a la persona.
