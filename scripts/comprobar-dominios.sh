#!/bin/bash
# =============================================================================
# comprobar-dominios.sh — ¿Responde cada dominio y subdominio de DuaLab?
# Solo hace peticiones de lectura (GET) y muestra el código HTTP. No cambia nada.
# Qué es cada dirección: docs/MAPA_DOMINIOS.md
#   ./scripts/comprobar-dominios.sh
# =============================================================================

VERDE='\033[0;32m'; ROJO='\033[0;31m'; NC='\033[0m'
fallos=0

# dirección | qué es | códigos que se consideran bien | (opcional) <title> que debe tener la página
# El título distingue una herramienta de la página por defecto de Hostinger o de otra app
# (una carpeta vacía o una ruta que no existe también pueden dar 200).
comprobar() {
  local url="$1" que="$2" esperado="$3" titulo="$4"
  local cuerpo codigo motivo=""
  cuerpo=$(mktemp)
  codigo=$(curl -s -o "$cuerpo" --max-time 15 -H "Accept: application/json" -w "%{http_code}" "$url")
  if [[ " $esperado " != *" $codigo "* ]]; then
    motivo="esperado $esperado"
  elif [ -n "$titulo" ] && ! grep -q "<title>$titulo</title>" "$cuerpo"; then
    motivo="la página no es «${titulo}» (¿sin subir?)"
  fi
  rm -f "$cuerpo"
  if [ -z "$motivo" ]; then
    printf "${VERDE}✓${NC} %-3s %-28s %s\n" "$codigo" "$que" "$url"
  else
    printf "${ROJO}✗${NC} %-3s %-28s %s — %s\n" "$codigo" "$que" "$url" "$motivo"
    fallos=$((fallos + 1))
  fi
}

echo "— App DuaLab (repo microretos)"
comprobar https://dualab.es/                       "App DuaLab"                 "200" "App DuaLab"
comprobar https://api.dualab.es/api/microretos     "API de la app (sin sesión)" "401"
comprobar https://comercial.dualab.es/             "App comercial"              "200" "App comercial"
comprobar https://central.dualab.es/               "DuaLab Central"             "200" "DuaLab Central"
comprobar https://nexo.dualab.es/                  "Nexo"                       "200" "Nexo"

echo "— Web pública (repo frontoffice)"
comprobar https://dualab.es/centros                "Web pública"                "200" "DuaLab"
comprobar https://dualab.es/gestion-web            "Gestión web"                "200" "DuaLab"
comprobar https://info.dualab.es/api-backend/public/api/retos           "API de la web"         "200"
comprobar https://info.dualab.es/api-backend/public/sanctum/csrf-cookie "API web: cookie CSRF"  "204"

echo "— Archivos privados (deben dar 403 o 404)"
comprobar https://api.dualab.es/.env                       ".env de la app"  "403 404"
comprobar https://info.dualab.es/api-backend/.env          ".env de la web"  "403 404"

echo
if [ "$fallos" -eq 0 ]; then echo -e "${VERDE}Todo responde como se espera.${NC}"; else echo -e "${ROJO}$fallos comprobaciones fallan: revisa docs/MAPA_DOMINIOS.md${NC}"; exit 1; fi
