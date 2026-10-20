<!-- Aviso de protección de datos: la plataforma solo trabaja con alias del alumnado
     ("NombreDePila Animal Color") y nunca debe recibir apellidos ni otros datos personales,
     tampoco en los campos de texto libre. Se usa en el
     alta/edición de grupos del docente y en el workspace del alumnado. -->
<script setup>
defineProps({
  // 'docente' | 'alumnado' — cambia el texto, no el aspecto
  para: { type: String, default: 'docente' },
})
</script>

<template>
  <div role="note"
       class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] leading-snug text-amber-800">
    <span class="shrink-0" aria-hidden="true">🔒</span>
    <p v-if="para === 'alumnado'" class="min-w-0 break-words">
      <strong>Solo el nombre de pila, nunca apellidos</strong> ni otros datos personales, en ningún campo.
      Cada persona es su alias (p. ej. «Adrián Águila Amarillo»): úsalo también para repartir tareas y en las reflexiones.
    </p>
    <p v-else class="min-w-0 break-words">
      <strong>Escribe solo el nombre de pila, nunca apellidos</strong> ni otros datos del alumnado, en ningún campo
      (tampoco en notas u observaciones). La plataforma le añade un animal y un color (p. ej. «Adrián Águila Amarillo»):
      ese alias es lo único que se guarda. Animal y color se pueden cambiar con el dado hasta que el grupo los confirme.
    </p>
  </div>
</template>
