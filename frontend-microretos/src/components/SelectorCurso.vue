<!-- Selector del curso académico compartido (store cursoAcademico): el panel y las bibliotecas
     muestran el mismo curso. Con permitir-todos añade la opción "Todos los cursos". -->
<script setup>
import { computed } from 'vue'
import { useCursoAcademicoStore, etiquetaCurso, CURSO_POR_DEFECTO } from '../stores/cursoAcademico.js'

const props = defineProps({
  permitirTodos: { type: Boolean, default: false },
  // años de inicio presentes en los datos de la vista, para ofrecerlos aunque sean antiguos
  cursosConDatos: { type: Array, default: () => [] },
})

const store = useCursoAcademicoStore()
const opciones = computed(() => store.opciones(props.cursosConDatos))
const valor = computed({
  get: () => (store.curso === null ? (props.permitirTodos ? 'todos' : CURSO_POR_DEFECTO) : store.curso),
  set: (v) => { store.curso = v === 'todos' ? null : Number(v) },
})
</script>

<template>
  <label class="inline-flex items-center gap-2 text-xs font-semibold text-gray-500">
    <span class="whitespace-nowrap">Curso académico</span>
    <select v-model="valor"
            class="h-9 rounded-lg border border-gray-200 bg-white px-3 pr-8 text-sm font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-centros/40">
      <option v-if="permitirTodos" value="todos">Todos los cursos</option>
      <option v-for="y in opciones" :key="y" :value="y">{{ etiquetaCurso(y) }}</option>
    </select>
  </label>
</template>
