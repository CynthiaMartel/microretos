¡Hola, {{ $docente }}!

Somos DuaLab. Ya puedes trabajar con tu alumnado {{ count($retos) === 1 ? 'el reto que preparamos' : 'los retos que preparamos' }} a partir de lo que nos contaste: {{ count($retos) === 1 ? 'está' : 'están' }} en la Biblioteca de retos de tu centro.
@if ($nota)

{{ $nota }}
@endif

@foreach ($retos as $reto)
· {{ $reto['titulo'] }}: {{ $reto['enlace'] }}
@endforeach

Entra con tu cuenta de DuaLab, abre el reto y pulsa «Trabajar este reto» para ajustarlo a tu grupo: curso, módulos, criterios y objetivos.

Si quieres cambiar algo, responde a este correo y te contestaremos.

Un saludo,
El equipo de DuaLab
