¡Hola, {{ $docente }}!

Somos DuaLab. Ya tenemos preparados los retos para tu alumnado a partir de lo que nos contaste.
@if ($nota)

{{ $nota }}
@endif

{{ count($retos) === 1 ? 'Tu reto' : 'Tus retos' }}:
@foreach ($retos as $titulo)
· {{ $titulo }}
@endforeach

Ver mis retos: {{ $enlace }}

Si quieres cambiar algo, responde a este correo y te contestaremos.

Un saludo,
El equipo de DuaLab

@if ($caduca)El enlace vale hasta el {{ $caduca }}. @endif Es personal: no lo reenvíes. El enlace que te enviamos antes para la solicitud ya no funciona.
