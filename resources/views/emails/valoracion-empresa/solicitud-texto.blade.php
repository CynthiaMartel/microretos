¡Hola, equipo de {{ $empresa }}!

Somos DuaLab. El alumnado ha terminado de trabajar el reto que nos planteasteis y ya tenéis sus soluciones.
@if ($nota)

{{ $nota }}
@endif

El reto: {{ $reto }}

En el enlace veréis las soluciones de cada grupo y unas pocas preguntas para valorarlas. No hace falta cuenta y se tarda unos minutos. También nos podéis decir si os interesaría seguir con un proyecto en vuestra empresa.

Ver las soluciones: {{ $enlace }}

Si tenéis cualquier duda, responded a este correo.

Un saludo,
El equipo de DuaLab

@if ($caduca)El enlace vale hasta el {{ $caduca }}. @endif Solo se puede valorar una vez.
