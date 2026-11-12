¡Hola, {{ $nombre }}!

@if ($accion === 'registro')
Gracias por registrarte en DuaLab. Hemos recibido tus datos y nuestro equipo los revisará para completar el alta. Te escribiremos en los próximos días laborables.
@elseif ($accion === 'demo')
Gracias por tu interés en DuaLab. Hemos recibido tu solicitud de demo y nos pondremos en contacto contigo para acordar día y hora.
@else
Gracias por tu interés en DuaLab. Hemos recibido tu solicitud y te enviaremos más información muy pronto.
@endif

Resumen de tu solicitud
@if ($textoAccion){{ $textoAccion }}
@endif
Centro educativo · {{ $centro }}

Un saludo,
El equipo de DuaLab

Recibes este correo porque has facilitado esta dirección a una persona del equipo de DuaLab. Si no has sido tú, puedes ignorarlo.
