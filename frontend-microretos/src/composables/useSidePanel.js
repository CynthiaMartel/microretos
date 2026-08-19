import { ref } from 'vue'

// Singleton: el toggle vive en TopBar (botón hamburguesa) y el estado lo consume SidePanel
const mobileOpen = ref(false)

export function useSidePanel() {
  return {
    mobileOpen,
    toggleMobilePanel: () => { mobileOpen.value = !mobileOpen.value },
    closeMobilePanel: () => { mobileOpen.value = false },
  }
}
