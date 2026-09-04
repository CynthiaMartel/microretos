// Fuente única de datos de noticias (mock). Cuando exista el backend real
// (ver TAREAS_PENDIENTES.txt FASE 6 y el subdominio de edición de noticias),
// este módulo se sustituye por llamadas a la API y ni InicioDocente.vue ni
// NoticiasListado.vue necesitan cambiar su lógica, solo la fuente de datos.

// Noticias generales de DuaLab — pendiente conectar con la API del subdominio
// de edición de noticias (agregando también lo publicado en info.dualab.es).
export const noticiasDualab = [
  {
    id: 'dualab-startup-day',
    categoria: 'Convocatoria',
    titulo: 'Startup Day 2025-2026 · Inscripciones abiertas',
    subtitulo: 'Plazo hasta el 15 de julio',
    imagen: 'https://images.unsplash.com/photo-1542744173-8e7e53415bb0?auto=format&fit=crop&w=600&q=80',
    alt: 'Startup Day presentación',
    alturaClase: 'h-40',
  },
  {
    id: 'dualab-taller-ia',
    categoria: 'Formación',
    titulo: 'Taller de retos con IA · 10 de julio',
    subtitulo: 'Plazas limitadas',
    imagen: 'https://images.unsplash.com/photo-1515378791036-0648a3ef77b2?auto=format&fit=crop&w=600&q=80',
    alt: 'Taller tecnología educativa',
    alturaClase: 'h-24',
  },
  {
    id: 'dualab-proyectos-destacados',
    categoria: 'Comunidad',
    titulo: 'Proyectos destacados del trimestre',
    subtitulo: 'Ver selección →',
    imagen: 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=600&q=80',
    alt: 'Trabajo en equipo',
    alturaClase: 'h-28',
  },
]

// Novedades de la propia plataforma — pendiente CRUD real (FASE 6).
export const novedadesPlataforma = [
  {
    id: 'plataforma-encuentros-compartir',
    categoria: 'Funcionalidad',
    titulo: 'Encuentros: ahora puedes compartirlos entre docentes',
    subtitulo: 'Nueva biblioteca de encuentros',
    imagen: 'https://images.unsplash.com/photo-1515879218367-8466d910aaa4?auto=format&fit=crop&w=600&q=80',
    alt: 'Plataforma tecnológica',
    alturaClase: 'h-48',
  },
  {
    id: 'plataforma-guia-microproyectos',
    categoria: 'Recurso',
    titulo: 'Nueva guía de microproyectos disponible',
    subtitulo: 'Biblioteca de recursos',
    imagen: 'https://images.unsplash.com/photo-1481627834876-b7833e8f5570?auto=format&fit=crop&w=600&q=80',
    alt: 'Guía microproyectos',
    alturaClase: 'h-32',
  },
]
