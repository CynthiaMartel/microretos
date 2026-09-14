// Helpers comunes a los tres composables de exportación PDF (usePdfExport.js,
// useMicroproyectoPdfExport.js, useDiagnosticoPdfExport.js) — antes cada uno
// duplicaba este mismo esqueleto (colores, checkBreak, setFont, addSectionTitle...).
import { jsPDF } from 'jspdf';

export const GREEN  = [0, 168, 89];
export const DARK   = [31, 41, 55];
export const GRAY   = [107, 114, 128];
export const LGRAY  = [75, 85, 99];
export const YELLOW = [161, 128, 0];
export const RED    = [220, 38, 38];
export const BLUE   = [37, 99, 235];
export const PURPLE = [126, 34, 206];
export const ORANGE = [194, 65, 12];

export const PAGE_W    = 210;
export const PAGE_H    = 297;
export const MARGIN    = 14;
export const CONTENT_W = PAGE_W - MARGIN * 2;
export const BOTTOM    = PAGE_H - 14; // margen inferior seguro (footer en PAGE_H - 9)

export function slugify(text) {
  return (text || 'documento')
    .toLowerCase()
    .normalize('NFD').replace(/[̀-ͯ]/g, '')
    .replace(/\s+/g, '-')
    .replace(/[^a-z0-9-]/g, '');
}

export function crearDocumento() {
  return new jsPDF({ unit: 'mm', format: 'a4', compress: true });
}

export function addFooters(doc) {
  const pageCount = doc.internal.getNumberOfPages();
  const today = new Date().toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
  for (let i = 1; i <= pageCount; i++) {
    doc.setPage(i);
    doc.setFontSize(6.5);
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(156, 163, 175);
    doc.text(
      `DuaLab · Generado el ${today} · Página ${i} de ${pageCount}`,
      MARGIN,
      PAGE_H - 6
    );
    doc.setDrawColor(229, 231, 235);
    doc.setLineWidth(0.2);
    doc.line(MARGIN, PAGE_H - 9, MARGIN + CONTENT_W, PAGE_H - 9);
  }
}

// Estado y helpers compartidos por página — cada composable añade sus propias
// funciones `render*` encima de este esqueleto.
export function makeBaseRenderer(doc) {
  const s = { y: 0 };

  const checkBreak = (needed = 20) => {
    if (s.y + needed > BOTTOM) {
      doc.addPage();
      s.y = MARGIN;
    }
  };

  const setFont = (size, style = 'normal', color = LGRAY) => {
    doc.setFontSize(size);
    doc.setFont('helvetica', style);
    doc.setTextColor(...color);
  };

  const addSectionTitle = (text, color = DARK) => {
    checkBreak(14);
    s.y += 2;
    setFont(6.5, 'bold', color);
    doc.text(text.toUpperCase(), MARGIN, s.y);
    doc.setDrawColor(...color);
    doc.setLineWidth(0.25);
    doc.line(MARGIN, s.y + 1, MARGIN + CONTENT_W, s.y + 1);
    s.y += 7;
  };

  // Texto párrafo: cada línea con su propio checkBreak para soportar saltos de página
  const addParagraph = (text, maxW = CONTENT_W, indent = 0) => {
    if (!text) return;
    setFont(8.5, 'normal', LGRAY);
    const lines = doc.splitTextToSize(String(text), maxW);
    for (const line of lines) {
      checkBreak(6);
      doc.text(line, MARGIN + indent, s.y);
      s.y += 4.5;
    }
    s.y += 3;
  };

  // Lista de puntos: cada ítem y cada línea con checkBreak individual. La fuente se
  // fija ANTES de medir con splitTextToSize (y no solo antes de imprimir) — jsPDF mide
  // el ancho con la fuente activa en el doc en ese instante, así que medir con una
  // fuente distinta a la que luego se imprime hace que el wrap subestime el ancho
  // real y el texto acabe desbordando el margen derecho.
  const addBulletList = (items, bulletColor = GREEN) => {
    if (!items?.length) return;
    items.forEach(item => {
      setFont(8.5, 'normal', LGRAY);
      const lines = doc.splitTextToSize(String(item), CONTENT_W - 6);
      checkBreak(5);
      setFont(9, 'bold', bulletColor);
      doc.text('•', MARGIN, s.y);
      setFont(8.5, 'normal', LGRAY);
      doc.text(lines[0], MARGIN + 5, s.y);
      s.y += 4.5;
      for (let i = 1; i < lines.length; i++) {
        checkBreak(5);
        doc.text(lines[i], MARGIN + 5, s.y);
        s.y += 4.5;
      }
      s.y += 1.5;
    });
    s.y += 2;
  };

  // Badge: omite silenciosamente si sobrepasaría el margen derecho
  const drawBadge = (text, bgColor, txtColor, x, badgeY) => {
    setFont(6.5, 'bold', txtColor);
    const tw = doc.getTextWidth(text);
    const bw = tw + 5;
    if (x + bw > MARGIN + CONTENT_W) return 0;
    doc.setFillColor(...bgColor);
    doc.roundedRect(x, badgeY - 3.5, bw, 6, 1.5, 1.5, 'F');
    doc.text(text, x + 2.5, badgeY);
    return bw + 2.5;
  };

  // Tarjeta con fondo y borde de color, replicando visualmente una tarjeta destacada de la
  // ficha en pantalla (no solo un título de sección con texto plano debajo). Pre-calcula la
  // altura total antes de dibujar el rectángulo de fondo, porque en jsPDF hay que dibujar el
  // fondo antes que el texto que va encima.
  const addHighlightedCard = (title, titleColor, bgColor, borderColor, fields) => {
    const present = fields.filter(f => f.text);
    if (!present.length) return;

    const measured = present.map(f => {
      setFont(8.5, 'normal', LGRAY);
      const lines = doc.splitTextToSize(String(f.text), CONTENT_W - 12);
      return { ...f, lines };
    });
    let contentH = 12; // padding superior + título
    measured.forEach(f => { contentH += 5 + f.lines.length * 4.5 + 3; });
    contentH += 3; // padding inferior

    checkBreak(contentH + 6);
    const boxY = s.y;
    doc.setFillColor(...bgColor);
    doc.setDrawColor(...borderColor);
    doc.setLineWidth(0.4);
    doc.roundedRect(MARGIN, boxY, CONTENT_W, contentH, 3, 3, 'FD');

    setFont(7, 'bold', titleColor);
    doc.text(title.toUpperCase(), MARGIN + 5, boxY + 7);

    let y = boxY + 14;
    measured.forEach(f => {
      setFont(7.5, 'bold', DARK);
      doc.text(f.label, MARGIN + 5, y);
      y += 4.5;
      setFont(8.5, 'normal', LGRAY);
      f.lines.forEach(line => {
        doc.text(line, MARGIN + 5, y);
        y += 4.5;
      });
      y += 3;
    });

    s.y = boxY + contentH + 7;
  };

  return { s, checkBreak, setFont, addSectionTitle, addParagraph, addBulletList, drawBadge, addHighlightedCard };
}
