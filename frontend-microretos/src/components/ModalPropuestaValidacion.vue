<!-- Modal «Proyecto en propuesta» (diseño modal_propuesta_validación_proyecto.png), común al
     Taller de Ideas (paso 7) y a la ficha del proyecto. Dos vías independientes:
     · Vía A · empresa: enlace real del backend (token_empresa) con «Copiar», empresa asociada
       y «Enviar enlace a la empresa». El correo desde DUALAB solo existe en el directorio de
       empresas (POST /empresas/{id}/enviar-validacion, con contraseña especial); aquí se ofrece
       ir allí o registrar que el docente ya lo envió (escribiendo «enviar»). «Enviado» solo se
       muestra cuando el servidor lo confirma (prop `enviado`).
     · Vía B · docente: «Validar como docente» con confirmación; permiso comprobado en servidor.
     Las acciones llegan como funciones async (`marcarEnviado`, `validarDocente`): si fallan, el
     error se muestra aquí en vez de tragarse. Accesible por ModalTaller (GUIA_DISENO §6.4). -->
<script setup>
import { ref, computed, watch } from 'vue'
import ModalTaller from './ModalTaller.vue'

const props = defineProps({
  abierto:         { type: Boolean, required: true },
  enlace:          { type: String, default: '' },     // URL de validación (vacía si aún no hay token)
  empresa:         { type: Object, default: null },   // { nombre, sector, persona_contacto, email, descripcion }
  enviado:         { type: Boolean, default: false }, // enviado_a_empresa_mail confirmado por el servidor
  docenteValidado: { type: Boolean, default: false },
  mostrarEditar:   { type: Boolean, default: false }, // «Ir a editar proyecto»
  // Vista con la que se abre: 'principal' (las dos vías) o 'envio' (desde un botón «Enviar» de la página).
  vistaInicial:    { type: String, default: 'principal' },
  marcarEnviado:   { type: Function, required: true },
  validarDocente:  { type: Function, required: true },
})
const emit = defineEmits(['cerrar', 'editar', 'directorio'])

const vista = ref('principal')        // 'principal' | 'envio' | 'docente'
const copiado = ref(false)
const empresaAbierta = ref(false)
const ayuda = ref({ a: false, b: false })
const textoConfirmacion = ref('')
const trabajando = ref(false)
const error = ref('')

const confirmacionValida = computed(() => textoConfirmacion.value.trim().toLowerCase() === 'enviar')

watch(() => props.abierto, (v) => {
  if (v) { vista.value = props.vistaInicial; error.value = ''; textoConfirmacion.value = ''; empresaAbierta.value = false }
}, { immediate: true })

async function copiar() {
  if (!props.enlace) return
  try {
    await navigator.clipboard.writeText(props.enlace)
    copiado.value = true
    setTimeout(() => { copiado.value = false }, 2500)
  } catch {
    error.value = 'No se pudo copiar el enlace. Selecciónalo y cópialo a mano.'
  }
}

function irA(v) { error.value = ''; textoConfirmacion.value = ''; vista.value = v }

async function ejecutar(accion, mensajeError) {
  trabajando.value = true
  error.value = ''
  try {
    await accion()
    vista.value = 'principal'
  } catch (e) {
    error.value = e?.response?.data?.message || mensajeError
  } finally {
    trabajando.value = false
  }
}
const confirmarEnvio = () => confirmacionValida.value &&
  ejecutar(props.marcarEnviado, 'No se pudo registrar el envío. Inténtalo de nuevo.')
const confirmarDocente = () =>
  ejecutar(props.validarDocente, 'No se pudo validar el proyecto. Comprueba tus permisos e inténtalo de nuevo.')
</script>

<template>
  <ModalTaller :abierto="abierto" titulo="Proyecto en propuesta" ancho="w-[min(44rem,calc(100vw-2rem))]"
               @cerrar="emit('cerrar')">
    <template #cabecera="{ idTitulo }">
      <div class="pl-10 text-center">
        <!-- En móvil, cabecera compacta: deja sitio a las dos vías (la cabecera no hace scroll) -->
        <span class="mx-auto inline-flex h-10 w-10 items-center justify-center rounded-xl bg-alumnos/15 text-alumnos-dark sm:h-16 sm:w-16 sm:rounded-2xl" aria-hidden="true">
          <svg class="h-5 w-5 sm:h-8 sm:w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12L3.27 3.13a59.77 59.77 0 0118.22 8.88 59.77 59.77 0 01-18.22 8.88L6 12zm0 0h7.5"/></svg>
        </span>
        <p class="mt-2 sm:mt-3">
          <span class="inline-flex items-center gap-2 rounded-full border border-alumnos/30 bg-alumnos/10 px-3 py-1 text-xs font-bold uppercase tracking-wide text-alumnos-dark">
            <span class="h-2 w-2 rounded-full bg-alumnos" aria-hidden="true" />
            Propuesta · Pendiente de validar
          </span>
        </p>
        <h2 :id="idTitulo" class="mt-2 font-heading text-xl font-bold text-azul-noche sm:mt-3 sm:text-3xl">
          Proyecto en <span class="text-rol-fuerte">propuesta</span>
        </h2>
        <p class="mx-auto mt-2 hidden max-w-md text-sm text-texto-secundario sm:block sm:text-base">
          Este proyecto puede validarse a través de la empresa, directamente como docente, o por ambas vías.
        </p>
      </div>
    </template>

    <p v-if="error" class="mb-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">
      {{ error }}
    </p>

    <!-- ═══ Vista principal: las dos vías ═══ -->
    <div v-if="vista === 'principal'" class="space-y-4">

      <!-- Vía A · empresa -->
      <section class="rounded-2xl border border-empresas/20 bg-empresas/5 p-4 sm:p-5" aria-labelledby="via-a">
        <div class="flex items-start gap-3 sm:gap-4">
          <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-empresas/15 text-empresas-dark sm:h-12 sm:w-12" aria-hidden="true">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
          </span>
          <div class="min-w-0 flex-1">
            <h3 id="via-a" class="text-base font-bold text-azul-noche sm:text-lg">
              Vía A · <span class="text-empresas-dark">Validación empresa</span>
            </h3>
            <p class="mt-0.5 text-sm text-texto-secundario">Comparte el enlace con la empresa para que revise y valide la propuesta.</p>
          </div>
          <button type="button" @click="ayuda.a = !ayuda.a" :aria-expanded="ayuda.a"
                  class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-empresas-dark hover:bg-empresas/10"
                  aria-label="Cómo funciona la validación de la empresa">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          </button>
        </div>
        <p v-if="ayuda.a" class="mt-3 rounded-xl bg-white px-3 py-2 text-sm text-texto-secundario">
          La empresa abre el enlace, revisa la propuesta y la valida o propone cambios. Cuando la valide,
          el proyecto pasará a «Validado · Empresa».
        </p>

        <label for="enlace-validacion" class="mt-4 block text-sm font-semibold text-azul-noche">Enlace de validación</label>
        <div class="mt-1.5 flex flex-col gap-2 sm:flex-row">
          <input id="enlace-validacion" type="text" readonly :value="enlace || 'El enlace se genera al guardar la propuesta'"
                 class="min-w-0 flex-1 rounded-xl px-3 py-2.5 text-sm text-texto-secundario" @focus="$event.target.select()" />
          <button type="button" @click="copiar" :disabled="!enlace"
                  class="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-full border border-rol-fuerte/40 bg-white px-5 text-sm font-semibold text-rol-fuerte transition-colors hover:bg-rol-fuerte/5 disabled:cursor-not-allowed disabled:opacity-50">
            <svg v-if="copiado" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            <span aria-live="polite">{{ copiado ? 'Copiado' : 'Copiar' }}</span>
          </button>
        </div>

        <template v-if="empresa">
          <p class="mt-4 text-sm font-semibold text-azul-noche" id="empresa-asociada">Empresa asociada</p>
          <div class="mt-1.5 rounded-xl border border-borde-campo bg-white">
            <button type="button" @click="empresaAbierta = !empresaAbierta" :aria-expanded="empresaAbierta"
                    aria-describedby="empresa-asociada"
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left">
              <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-texto-secundario" aria-hidden="true">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
              </span>
              <span class="min-w-0 flex-1 truncate text-sm font-semibold text-azul-noche">{{ empresa.nombre || 'Empresa sin nombre' }}</span>
              <svg class="h-4 w-4 shrink-0 text-texto-secundario transition-transform" :class="empresaAbierta ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <dl v-if="empresaAbierta" class="space-y-1 border-t border-borde px-4 py-3 text-sm">
              <div v-if="empresa.sector" class="flex gap-2"><dt class="font-semibold text-azul-noche">Sector:</dt><dd class="min-w-0 break-words text-texto-secundario">{{ empresa.sector }}</dd></div>
              <div v-if="empresa.persona_contacto" class="flex gap-2"><dt class="font-semibold text-azul-noche">Contacto:</dt><dd class="min-w-0 break-words text-texto-secundario">{{ empresa.persona_contacto }}</dd></div>
              <div v-if="empresa.email" class="flex gap-2"><dt class="font-semibold text-azul-noche">Email:</dt><dd class="min-w-0 break-all text-texto-secundario">{{ empresa.email }}</dd></div>
              <p v-if="empresa.descripcion" class="border-t border-borde pt-2 text-texto-secundario">{{ empresa.descripcion }}</p>
            </dl>
          </div>
        </template>

        <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
          <button v-if="empresa" type="button" @click="irA('envio')"
                  class="inline-flex h-11 items-center justify-center gap-2 rounded-full px-4 text-sm font-semibold transition-colors"
                  :class="enviado ? 'border border-empresas/40 bg-white text-empresas-dark hover:bg-empresas/5' : 'bg-empresas-dark text-white shadow-sm hover:bg-empresas-dark/90'">
            <svg v-if="enviado" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            {{ enviado ? 'Enviado · reenviar' : 'Enviar enlace a la empresa' }}
          </button>
          <button type="button" @click="emit('directorio')"
                  class="inline-flex h-11 items-center justify-center gap-2 rounded-full border border-rol-fuerte/40 bg-white px-4 text-sm font-semibold text-rol-fuerte transition-colors hover:bg-rol-fuerte/5"
                  :class="empresa ? '' : 'sm:col-span-2'">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            Ir al directorio de empresas
          </button>
        </div>
      </section>

      <!-- Vía B · docente -->
      <section class="rounded-2xl border border-rol/20 bg-rol/5 p-4 sm:p-5" aria-labelledby="via-b">
        <div class="flex items-start gap-3 sm:gap-4">
          <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rol/15 text-rol-fuerte sm:h-12 sm:w-12" aria-hidden="true">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422A12.083 12.083 0 0118.82 17.5L12 21l-6.82-3.5a12.08 12.08 0 01.66-6.922L12 14z"/></svg>
          </span>
          <div class="min-w-0 flex-1">
            <h3 id="via-b" class="text-base font-bold text-azul-noche sm:text-lg">
              Vía B · <span class="text-rol-fuerte">Validación docente</span>
            </h3>
            <p class="mt-0.5 text-sm text-texto-secundario">Puedes validar el proyecto directamente sin esperar a la empresa.</p>
          </div>
          <button type="button" @click="ayuda.b = !ayuda.b" :aria-expanded="ayuda.b"
                  class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-rol-fuerte hover:bg-rol/10"
                  aria-label="Cómo funciona la validación docente">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          </button>
        </div>
        <p v-if="ayuda.b" class="mt-3 rounded-xl bg-white px-3 py-2 text-sm text-texto-secundario">
          Confirmas que el proyecto cumple los criterios pedagógicos. Quedará como «Validado · Docente»
          y podrás seguir esperando la respuesta de la empresa.
        </p>

        <p class="mt-4 flex items-start gap-2.5 rounded-xl border border-rol/20 bg-white/70 px-3 py-2.5 text-sm text-azul-noche">
          <svg class="mt-0.5 h-4 w-4 shrink-0 text-rol-fuerte" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          Esto no sustituye la validación de la empresa: ambas son independientes y complementarias.
        </p>

        <button v-if="!docenteValidado" type="button" @click="irA('docente')"
                class="mt-4 inline-flex h-11 w-full items-center justify-center gap-2 rounded-full bg-rol-fuerte px-4 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-rol-fuerte/90">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
          Validar como docente
        </button>
        <p v-else class="mt-4 flex items-center gap-2 rounded-xl bg-empresas/10 px-3 py-2.5 text-sm font-semibold text-empresas-dark" role="status">
          <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
          Ya has validado este proyecto como docente
        </p>
      </section>
    </div>

    <!-- ═══ Vista: enviar el enlace a la empresa ═══ -->
    <div v-else-if="vista === 'envio'" class="space-y-4">
      <button type="button" @click="irA('principal')" class="inline-flex h-9 items-center gap-1.5 rounded-full px-2 text-sm font-semibold text-texto-secundario hover:text-azul-noche">
        ← Volver
      </button>
      <h3 class="text-lg font-bold text-azul-noche">Enviar el enlace a {{ empresa?.nombre || 'la empresa' }}</h3>

      <div class="rounded-2xl border border-borde bg-white p-4">
        <p class="text-sm font-semibold text-azul-noche">Enviar el correo desde DUALAB</p>
        <p class="mt-1 text-sm text-texto-secundario">
          Se envía desde el directorio de empresas, con el mensaje preparado y el enlace. Necesitarás la contraseña especial del directorio.
        </p>
        <button type="button" @click="emit('directorio')"
                class="mt-3 inline-flex h-10 items-center gap-2 rounded-full bg-empresas-dark px-4 text-sm font-semibold text-white hover:bg-empresas-dark/90">
          Ir al directorio de empresas
        </button>
      </div>

      <div class="rounded-2xl border border-borde bg-white p-4">
        <p class="text-sm font-semibold text-azul-noche">Ya se lo he enviado yo</p>
        <p class="mt-1 text-sm text-texto-secundario">
          Si has copiado el enlace y se lo has hecho llegar por tu cuenta, regístralo para que conste como enviado.
        </p>
        <label for="confirmar-envio" class="mt-3 block text-sm text-azul-noche">
          Escribe <strong>enviar</strong> para confirmar
        </label>
        <input id="confirmar-envio" v-model="textoConfirmacion" type="text" autocomplete="off" placeholder="enviar"
               class="mt-1.5 w-full rounded-xl px-3 py-2.5 text-sm" :aria-invalid="!!textoConfirmacion && !confirmacionValida"
               @keydown.enter.prevent="confirmarEnvio()" />
        <div class="mt-3 flex justify-end">
          <button type="button" @click="confirmarEnvio" :disabled="!confirmacionValida || trabajando"
                  class="inline-flex h-10 items-center gap-2 rounded-full bg-rol-fuerte px-5 text-sm font-semibold text-white hover:bg-rol-fuerte/90 disabled:cursor-not-allowed disabled:opacity-50">
            <svg v-if="trabajando" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2v4a6 6 0 106 6h4a10 10 0 11-10-10z"/></svg>
            {{ trabajando ? 'Guardando…' : 'Marcar como enviado' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Vista: confirmar validación docente ═══ -->
    <div v-else class="space-y-4">
      <button type="button" @click="irA('principal')" class="inline-flex h-9 items-center gap-1.5 rounded-full px-2 text-sm font-semibold text-texto-secundario hover:text-azul-noche">
        ← Volver
      </button>
      <h3 class="text-lg font-bold text-azul-noche">Validar como docente</h3>
      <p class="text-sm text-texto-secundario">¿Confirmas que este proyecto cumple los criterios pedagógicos?</p>
      <p class="flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-900">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        Es independiente de la validación de la empresa: el proyecto quedará como «Validado · Docente» y la empresa podrá seguir respondiendo.
      </p>
      <div class="fila-pasos">
        <button type="button" @click="irA('principal')" class="btn-paso-atras">Cancelar</button>
        <button type="button" @click="confirmarDocente" :disabled="trabajando" class="btn-paso">
          {{ trabajando ? 'Validando…' : 'Confirmar validación' }}
        </button>
      </div>
    </div>

    <template #pie>
      <div class="grid grid-cols-1 gap-2" :class="mostrarEditar ? 'sm:grid-cols-2' : ''">
        <button type="button" @click="emit('cerrar')"
                class="inline-flex h-11 items-center justify-center rounded-full bg-gray-100 px-5 text-sm font-semibold text-azul-noche transition-colors hover:bg-gray-200">
          Entendido
        </button>
        <button v-if="mostrarEditar" type="button" @click="emit('editar')"
                class="inline-flex h-11 items-center justify-center gap-2 rounded-full border border-rol-fuerte/40 bg-white px-5 text-sm font-semibold text-rol-fuerte transition-colors hover:bg-rol-fuerte/5">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
          Ir a editar proyecto
        </button>
      </div>
    </template>
  </ModalTaller>
</template>
