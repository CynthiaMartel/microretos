<script setup>
import { generarAliasAleatorio } from '../utils/alias.js'

const props = defineProps({
  nombre: { type: String, default: '' },
})
const emit = defineEmits(['generado'])

function generar() {
  emit('generado', generarAliasAleatorio(props.nombre))
}
</script>

<template>
  <span class="dice-wrap">
    <button @click="generar" type="button" title="Generador de alias"
            class="dice-btn" aria-label="Generador de alias">
      <span class="dice-emoji">🎲</span>
    </button>
    <span class="dice-tooltip" role="tooltip">Generador de alias</span>
  </span>
</template>

<style scoped>
.dice-wrap {
  position: relative;
  display: inline-flex;
  flex-shrink: 0;
}
.dice-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  flex-shrink: 0;
  border: none;
  border-radius: 9999px;
  background: linear-gradient(135deg, #34d399, #059669);
  box-shadow: 0 2px 6px rgba(5, 150, 105, .4);
  cursor: pointer;
  transition: transform .18s cubic-bezier(.34, 1.56, .64, 1), box-shadow .18s ease;
}
.dice-btn:hover {
  transform: scale(1.35) rotate(-10deg);
  box-shadow: 0 4px 14px rgba(5, 150, 105, .55);
}
.dice-btn:active {
  transform: scale(1.1) rotate(6deg);
}
.dice-emoji {
  font-size: .8rem;
  line-height: 1;
}

.dice-tooltip {
  position: absolute;
  bottom: calc(100% + 7px);
  left: 50%;
  transform: translateX(-50%) translateY(2px);
  white-space: nowrap;
  background: #1F2937;
  color: #fff;
  font-size: .65rem;
  font-weight: 700;
  line-height: 1;
  padding: .35rem .55rem;
  border-radius: .4rem;
  pointer-events: none;
  opacity: 0;
  transition: opacity .12s ease, transform .12s ease;
  z-index: 20;
}
.dice-tooltip::after {
  content: '';
  position: absolute;
  top: 100%;
  left: 50%;
  transform: translateX(-50%);
  border: 4px solid transparent;
  border-top-color: #1F2937;
}
.dice-wrap:hover .dice-tooltip,
.dice-btn:focus-visible ~ .dice-tooltip {
  opacity: 1;
  transform: translateX(-50%) translateY(0);
}
</style>
