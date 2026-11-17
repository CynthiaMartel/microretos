¡Hola{{ $contacto ? ', ' . $contacto : '' }}!

Soy {{ $remitente }}, del equipo de DuaLab. Como no nos ha dado tiempo a hacer la entrevista en persona, te la envío para que la completes cuando te venga bien{{ $empresa ? ' en nombre de ' . $empresa : '' }}.

DuaLab conecta a las empresas con el alumnado de Formación Profesional: con lo que nos cuentes diseñaremos retos reales para que el alumnado trabaje en ellos. Son tres pasos:

1. Los datos de tu empresa y con qué alumnado te gustaría trabajar.
2. Siete preguntas sobre vuestro día a día y lo que más trabajo os da.
3. La firma de las condiciones de participación y la protección de datos.

Completar la entrevista: {{ $enlace }}

Resumen
Entrevista para colaborar con DuaLab{{ $empresa ? ' · ' . $empresa : '' }}

Unos 15 minutos · puedes dejarla a medias y seguir después con el mismo enlace
@if ($caduca)
El enlace vale hasta el {{ $caduca }}
@endif

Si tienes cualquier duda, responde a este correo y te contestaré yo.

Un saludo,
{{ $remitente }} · El equipo de DuaLab

Recibes este correo porque has facilitado esta dirección a una persona del equipo de DuaLab. El enlace es personal: no lo reenvíes fuera de tu empresa. Si no esperabas este correo, puedes ignorarlo.
