// Los textos del BOE ya traen su código delante («a) Se ha…», «1. Distingue…»). Solo para
// mostrarlo: el código va en su columna y el resto al lado. La redacción oficial no se toca.
export function separarCodigo(texto, codigoPorDefecto = '') {
  const m = /^\s*([a-zñ]\)|\d+[.)])\s+/i.exec(texto || '')
  return m ? { codigo: m[1], texto: texto.slice(m[0].length) } : { codigo: codigoPorDefecto, texto: texto || '' }
}

export function normalizarTexto(t) {
  return (t || '').trim().toLowerCase().replace(/\s+/g, ' ')
}
