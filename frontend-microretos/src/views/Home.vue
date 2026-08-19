<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useLoginModal } from '../composables/useLoginModal.js';

const router    = useRouter();
const authStore = useAuthStore();
const { openLogin } = useLoginModal();
const isLoaded  = ref(false);

onMounted(() => { setTimeout(() => { isLoaded.value = true; }, 100); });

const irABiblioteca = () => {
  if (authStore.isAuthenticated) router.push('/retos');
  else openLogin('/retos');
};
</script>

<template>
  <div class="min-h-screen bg-[#F8FAFC] overflow-x-hidden font-sans text-[#1F2937] relative flex items-start lg:items-center justify-center p-4 md:p-8 pt-14 md:pt-16 pb-4 md:pb-6">

    <div class="absolute top-[-10%] left-[-10%] w-[40vw] h-[40vw] bg-[#99CC33]/10 rounded-full blur-[100px] pointer-events-none transition-opacity duration-1000" :class="isLoaded ? 'opacity-100' : 'opacity-0'"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-[30vw] h-[30vw] bg-[#00A859]/10 rounded-full blur-[100px] pointer-events-none transition-opacity duration-1000 delay-300" :class="isLoaded ? 'opacity-100' : 'opacity-0'"></div>

    <div class="max-w-6xl w-full mx-auto relative z-10">

      <header class="flex justify-center mb-4 sm:mb-6 md:mb-8 lg:mb-10 transition-all duration-1000 ease-out transform"
        :class="isLoaded ? 'translate-y-0 opacity-100' : '-translate-y-10 opacity-0'">
        <div class="inline-flex items-center bg-[#1F2937] py-2 sm:py-3 pr-5 sm:pr-7 md:pr-10 pl-2 sm:pl-4 md:pl-5 rounded-[1.5rem] sm:rounded-[2rem] shadow-lg border border-[#333333]">
          <img src="../assets/logo.png" alt="Logo DuaLab" class="h-10 sm:h-14 md:h-18 lg:h-20 w-auto object-contain -mr-1 sm:-mr-2 md:-mr-3 relative z-10" style="height: clamp(2.5rem, 5vw, 5rem);" />
          <span class="font-black text-xl sm:text-2xl md:text-3xl lg:text-4xl tracking-tighter uppercase text-white relative z-20">
            Dua<span class="text-[#00A859]">Lab</span>
          </span>
        </div>
      </header>

      <main class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8 lg:gap-14 items-center">

        <div class="space-y-3 sm:space-y-4 md:space-y-5 text-center md:text-left">
          <div class="transition-all duration-700 delay-150 ease-out transform"
               :class="isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-10 opacity-0'">
            <div class="inline-block bg-[#99CC33]/15 text-[#00A859] px-3 py-1 rounded-full text-xs font-black uppercase tracking-widest mb-2 md:mb-3 border border-[#99CC33]/30">
              Innovación Educativa B2B
            </div>
            <h1 class="text-3xl sm:text-4xl md:text-4xl lg:text-5xl font-black tracking-tighter leading-[1.1] text-[#121212] mb-2 md:mb-3">
              Conecta talento <br class="hidden md:block"/> con <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#00A859] to-[#99CC33]">retos reales.</span>
            </h1>
            <p class="text-sm md:text-base text-gray-500 leading-relaxed font-medium max-w-xl mx-auto md:mx-0">
              DuaLab conecta empresas con alumnado en prácticas. Transformamos necesidades empresariales en <strong class="text-[#00A859] font-bold">retos académicos</strong> para impulsar el aprendizaje práctico y descubrir talento emergente.
            </p>
          </div>

          <div class="flex flex-col sm:flex-row items-center gap-3 pt-1 transition-all duration-700 delay-300 ease-out transform"
               :class="isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-10 opacity-0'">

            <!-- Sesión no iniciada: botón principal de login -->
            <button
              v-if="!authStore.isAuthenticated"
              @click="openLogin(null)"
              class="w-full sm:w-auto flex items-center justify-center gap-2.5 px-6 py-3.5 bg-gradient-to-r from-[#00A859] to-[#99CC33] text-white rounded-full font-black text-sm uppercase tracking-widest shadow-[0_8px_24px_rgba(0,168,89,0.3)] hover:shadow-[0_12px_32px_rgba(153,204,51,0.4)] transition-all duration-300 hover:-translate-y-1 active:scale-95"
            >
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
              </svg>
              Iniciar sesión
            </button>

            <button @click="irABiblioteca" class="w-full sm:w-auto flex items-center justify-center gap-2.5 px-6 py-3.5 bg-white text-[#1F2937] border-2 border-gray-200 rounded-full font-black text-sm uppercase tracking-widest shadow-sm hover:border-[#00A859] hover:text-[#00A859] transition-all duration-300 hover:-translate-y-1 active:scale-95">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
              Biblioteca de Retos
            </button>
          </div>
        </div>

        <div class="relative transition-all duration-1000 delay-500 ease-out transform"
             :class="isLoaded ? 'translate-y-0 opacity-100 scale-100' : 'translate-y-16 opacity-0 scale-95'">

          <div class="bg-white p-4 sm:p-5 rounded-[1.5rem] sm:rounded-[2rem] shadow-[0_20px_50px_rgb(0,0,0,0.05)] border border-gray-100 relative z-10">
            <div class="flex justify-between items-center border-b border-gray-100 pb-4 mb-4">
              <div class="flex items-center gap-2.5">
                <div class="w-2.5 h-2.5 rounded-full bg-[#99CC33]"></div>
                <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">Flujo de Trabajo</span>
              </div>
              <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/></svg>
            </div>

            <div class="space-y-3">
              <div class="flex gap-3 items-start p-3 rounded-2xl hover:bg-gray-50 transition-colors">
                <div class="bg-[#1F2937] text-white w-9 h-9 rounded-xl flex items-center justify-center shrink-0 font-black shadow-md text-sm">1</div>
                <div>
                  <h4 class="font-bold text-[#1F2937] text-base">La Empresa propone</h4>
                  <p class="text-sm text-gray-500 leading-relaxed mt-0.5">Identifican una fricción o cuello de botella real en su día a día.</p>
                </div>
              </div>

              <div class="w-0.5 h-3 bg-gradient-to-b from-gray-200 to-[#99CC33] ml-[1.625rem] -my-2 relative z-0"></div>

              <div class="flex gap-3 items-start p-3 rounded-2xl bg-[#99CC33]/10 border border-[#99CC33]/20 relative z-10">
                <div class="bg-[#99CC33] text-[#121212] w-9 h-9 rounded-xl flex items-center justify-center shrink-0 font-black shadow-md text-sm">2</div>
                <div>
                  <h4 class="font-bold text-[#00A859] text-base">La IA lo transforma</h4>
                  <p class="text-sm text-gray-600 leading-relaxed mt-0.5">DuaLab genera un reto académico alineado al currículo oficial.</p>
                </div>
              </div>

              <div class="w-0.5 h-3 bg-gradient-to-b from-[#99CC33] to-[#00A859] ml-[1.625rem] -my-2 relative z-0"></div>

              <div class="flex gap-3 items-start p-3 rounded-2xl hover:bg-gray-50 transition-colors">
                <div class="bg-[#00A859] text-white w-9 h-9 rounded-xl flex items-center justify-center shrink-0 font-black shadow-md shadow-[#00A859]/30 text-sm">3</div>
                <div>
                  <h4 class="font-bold text-[#1F2937] text-base">El Alumnado resuelve</h4>
                  <p class="text-sm text-gray-500 leading-relaxed mt-0.5">Ganan experiencia práctica mientras aportan valor real a la empresa.</p>
                </div>
              </div>
            </div>
          </div>

          <div class="hidden sm:flex absolute -bottom-3 -right-3 z-20 bg-[#1F2937] text-white p-3 sm:p-4 rounded-2xl shadow-2xl items-center gap-2.5 sm:gap-3 animate-bounce" style="animation-duration: 3s;">
            <div class="bg-[#99CC33] text-[#121212] rounded-full p-1.5">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
            </div>
            <div>
              <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Resultado</p>
              <p class="font-black whitespace-nowrap text-sm">Match Perfecto</p>
            </div>
          </div>

        </div>
      </main>
    </div>
  </div>
</template>

<style scoped>
/* Las fuentes asumen que tienes importada la familia globalmente, si no, te recomiendo importar un sans como Inter o Montserrat */
</style>