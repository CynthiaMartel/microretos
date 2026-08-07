# Diccionario de preguntas — Diagnóstico de empresa

Cabecera a usar en el export (CSV): `campo_bd`. Varias opciones seleccionadas en una misma pregunta se separan con `|`, nunca con coma.

| orden | campo_bd | pregunta | tipo_dato | obligatorio | opciones_permitidas / formato |
|---|---|---|---|---|---|
| 1 | `dia_a_normal` | ¿Qué ofrece su empresa y qué hace en su día a día? | texto libre | sí | — |
| 2 | `friccion_area` | ¿Qué tarea da más trabajo del que debería? | texto libre | no | — |
| 3 | `friccion_problema` | ¿Por qué? Cuéntanos qué ocurre hoy | texto libre | sí | — |
| 4 | `consecuencias` | Si pudieran mejorar algo YA mismo, ¿qué sería? | selección múltiple + texto libre | no | `Errores frecuentes\|Costes innecesarios\|Pérdida de tiempo\|Insatisfacción del cliente\|Riesgos de seguridad\|Desperdicio de materiales\|Falta de comunicación interna` — separar seleccionadas con `\|` |
| 5 | `restricciones` | ¿Han probado solucionarlo? ¿Qué limitaciones tienen? | selección múltiple + texto libre | no | `Presupuesto Cero/Muy Bajo\|Equipos obsoletos\|Internet inestable\|Software cerrado\|Resistencia al cambio\|Espacio reducido\|Falta de tiempo\|Normativa RGPD` — mismo formato |
| 6 | `lo_que_no_quieren` | ¿Qué NO quieren bajo ningún concepto? | texto libre | no | — |
| 7 | `expectativas_alumno` | Si tuvieras a un alumno aquí, ¿qué esperas que realice? | texto libre | sí | — |
