import {
  GREEN, DARK, GRAY, LGRAY, YELLOW,
  PAGE_W, MARGIN, CONTENT_W,
  slugify, crearDocumento, addFooters, makeBaseRenderer,
} from './pdfHelpers.js';
import { formatCurso } from '../utils/formatCurso.js';

// Nivel alcanzado evaluado por el docente para un RA concreto (F4), si existe —
// busca por coincidencia exacta de texto del RA, igual que initEvaluacionForm()
// en MisGruposDetalle.vue.
const nivelParaRa = (evaluados, ra) => evaluados.find(e => e.ra === ra)?.nivel || null;

const NIVEL_LABELS = {
  no_alcanzado: 'No alcanzado',
  en_proceso:   'En proceso',
  alcanzado:    'Alcanzado',
  superado:     'Superado',
};
const NIVEL_COLORS = {
  no_alcanzado: [220, 38, 38],
  en_proceso:   YELLOW,
  alcanzado:    GREEN,
  superado:     [21, 128, 61],
};

function makeRenderer(doc) {
  const { s, checkBreak, setFont, addSectionTitle, addParagraph, addBulletList, drawBadge } = makeBaseRenderer(doc);

  const renderDiagnostico = ({ equipo, encuentro }) => {
    s.y = 0;
    const diag      = equipo.diagnostico_final || {};
    const proyecto  = equipo.proyecto || {};
    const evaluados = equipo.fases?.[4]?.datos?.evaluacion_docente?.ras || [];
    const notaFinal = equipo.fases?.[4]?.nota_docente;

    // ── BANDA SUPERIOR ───────────────────────────────────────────────────────
    doc.setFillColor(...GREEN);
    doc.rect(0, 0, PAGE_W, 8, 'F');
    setFont(6, 'bold', [255, 255, 255]);
    doc.text('DUALAB · DIAGNÓSTICO FINAL DE EQUIPO', MARGIN, 5.5);

    s.y = 14;

    // Título
    setFont(18, 'bold', DARK);
    const titleLines = doc.splitTextToSize(equipo.nombre || 'Equipo', CONTENT_W);
    doc.text(titleLines, MARGIN, s.y);
    s.y += titleLines.length * 7 + 2;

    if (proyecto.titulo) {
      setFont(10, 'normal', GRAY);
      const projLines = doc.splitTextToSize(proyecto.titulo, CONTENT_W);
      doc.text(projLines, MARGIN, s.y);
      s.y += projLines.length * 5 + 2;
    }

    // Badges de contexto (curso, grupo, ciclo, centro, familia)
    let bx = MARGIN;
    if (encuentro?.curso)            bx += drawBadge(`${formatCurso(encuentro.curso)} curso`, [209, 250, 229], GREEN, bx, s.y);
    if (encuentro?.grupo)            bx += drawBadge(`Grupo ${encuentro.grupo}`,   DARK,            [255, 255, 255], bx, s.y);
    if (encuentro?.ciclo_formativo)  bx += drawBadge(encuentro.ciclo_formativo,    [243, 244, 246], GRAY, bx, s.y);
    if (proyecto.familia)            drawBadge(proyecto.familia, [243, 244, 246], GRAY, bx, s.y);
    s.y += 10;

    if (encuentro?.centro_educativo) {
      setFont(8, 'normal', GRAY);
      doc.text(encuentro.centro_educativo, MARGIN, s.y);
      s.y += 5;
    }

    doc.setDrawColor(229, 231, 235);
    doc.setLineWidth(0.3);
    doc.line(MARGIN, s.y, MARGIN + CONTENT_W, s.y);
    s.y += 6;

    // ── EQUIPO ───────────────────────────────────────────────────────────────
    if (equipo.miembros?.length) {
      addSectionTitle(`Equipo (${equipo.miembros.length} personas)`, DARK);
      equipo.miembros.forEach(m => {
        checkBreak(6);
        setFont(8.5, 'normal', LGRAY);
        doc.text(`• ${m.nombre}`, MARGIN, s.y);
        if (m.rol) {
          const nw = doc.getTextWidth(`• ${m.nombre}`);
          setFont(8, 'normal', GRAY);
          doc.text(` · ${m.rol}`, MARGIN + nw, s.y);
        }
        s.y += 4.8;
      });
      s.y += 2;
    }

    // ── DIAGNÓSTICO IA ───────────────────────────────────────────────────────
    if (diag.resumen) {
      addSectionTitle('Resumen', GREEN);
      addParagraph(diag.resumen);
    }

    if (diag.fortalezas?.length) {
      addSectionTitle('Fortalezas', GREEN);
      addBulletList(diag.fortalezas, GREEN);
    }

    if (diag.areas_mejora?.length) {
      addSectionTitle('Áreas de mejora', YELLOW);
      addBulletList(diag.areas_mejora, YELLOW);
    }

    if (diag.valoracion_ra_ce) {
      addSectionTitle('Valoración RA/CE', DARK);
      addParagraph(diag.valoracion_ra_ce);
    }

    if (diag.conclusion) {
      checkBreak(16);
      setFont(9, 'bolditalic', DARK);
      const lines = doc.splitTextToSize(diag.conclusion, CONTENT_W);
      doc.text(lines, MARGIN, s.y);
      s.y += lines.length * 5 + 4;
    }

    // ── RA / CE Y MÓDULOS TRABAJADOS ─────────────────────────────────────────
    if (proyecto.evaluacion_oficial?.length) {
      addSectionTitle('Resultados de aprendizaje y criterios de evaluación trabajados', DARK);

      proyecto.evaluacion_oficial.forEach((evalObj, idx) => {
        // splitTextToSize mide el ancho con la fuente ACTIVA en el doc en ese instante —
        // hay que fijarla antes de medir, o el wrap se calcula con una fuente más
        // estrecha que la que luego se usa para imprimir y el texto desborda el margen.
        setFont(8.5, 'bold', DARK);
        const modLines = doc.splitTextToSize(evalObj.modulo || '', CONTENT_W - 6);
        setFont(8.5, 'normal', LGRAY);
        const raLines  = doc.splitTextToSize(evalObj.ra || '', CONTENT_W - 6);
        const ceItems  = evalObj.ce || [];
        const nivel    = nivelParaRa(evaluados, evalObj.ra);

        const modH = modLines.length * 5 + 9;
        checkBreak(modH);
        doc.setFillColor(243, 244, 246);
        doc.setDrawColor(229, 231, 235);
        doc.setLineWidth(0.25);
        doc.roundedRect(MARGIN, s.y, CONTENT_W, modH, 1.5, 1.5, 'FD');
        doc.setFillColor(...DARK);
        doc.rect(MARGIN, s.y, 2.5, modH, 'F');
        setFont(6, 'bold', GRAY);
        doc.text('MÓDULO', MARGIN + 5, s.y + 4.5);
        setFont(8.5, 'bold', DARK);
        doc.text(modLines, MARGIN + 5, s.y + 8.5);
        if (nivel) drawBadge(NIVEL_LABELS[nivel] || nivel, [243, 244, 246], NIVEL_COLORS[nivel] || GRAY, MARGIN + CONTENT_W - 40, s.y + modH / 2 + 1.5);
        s.y += modH + 3;

        checkBreak(8);
        setFont(6, 'bold', GREEN);
        doc.text('RESULTADO DE APRENDIZAJE', MARGIN + 2, s.y);
        s.y += 4.5;
        setFont(8.5, 'normal', LGRAY);
        for (const line of raLines) {
          checkBreak(5);
          doc.text(line, MARGIN + 2, s.y);
          s.y += 4.5;
        }
        s.y += 3;

        if (ceItems.length) {
          checkBreak(8);
          setFont(6, 'bold', GRAY);
          doc.text('CRITERIOS DE EVALUACIÓN', MARGIN + 2, s.y);
          s.y += 4.5;
          ceItems.forEach(ce => {
            setFont(8, 'normal', LGRAY);
            const ceL = doc.splitTextToSize(String(ce), CONTENT_W - 10);
            checkBreak(ceL.length * 4.5 + 2);
            setFont(7, 'bold', GREEN);
            doc.text('✓', MARGIN + 2, s.y);
            setFont(8, 'normal', LGRAY);
            doc.text(ceL, MARGIN + 7, s.y);
            s.y += ceL.length * 4.5 + 1.5;
          });
        }

        s.y += 4;
        if (idx < proyecto.evaluacion_oficial.length - 1) {
          checkBreak(6);
          doc.setDrawColor(229, 231, 235);
          doc.setLineWidth(0.2);
          doc.line(MARGIN + 4, s.y, MARGIN + CONTENT_W - 4, s.y);
          s.y += 5;
        }
      });
    }

    // ── NOTAS ────────────────────────────────────────────────────────────────
    const notasPorFase = (equipo.fases || [])
      .filter(f => f?.nota_docente !== null && f?.nota_docente !== undefined && f?.numero_fase !== 4);
    if (notasPorFase.length || notaFinal !== null && notaFinal !== undefined) {
      addSectionTitle('Notas', DARK);
      notasPorFase.forEach(f => {
        checkBreak(5);
        setFont(8.5, 'normal', LGRAY);
        doc.text(`Fase ${f.numero_fase}`, MARGIN, s.y);
        setFont(8.5, 'bold', GREEN);
        doc.text(String(f.nota_docente), MARGIN + 25, s.y);
        s.y += 5;
      });
      if (notaFinal !== null && notaFinal !== undefined) {
        checkBreak(8);
        setFont(9, 'bold', DARK);
        doc.text('Nota final', MARGIN, s.y);
        setFont(11, 'bold', GREEN);
        doc.text(`${notaFinal} / 10`, MARGIN + 28, s.y);
        s.y += 6;
      }
    }

    if (equipo.diagnostico_generado_en) {
      s.y += 2;
      setFont(7.5, 'normal', GRAY);
      doc.text(
        `Diagnóstico generado el ${new Date(equipo.diagnostico_generado_en).toLocaleString('es-ES')}`,
        MARGIN, s.y
      );
      s.y += 5;
    }
  };

  return { renderDiagnostico };
}

export function useDiagnosticoPdfExport() {
  const descargarPDF = ({ equipo, encuentro }) => {
    const doc = crearDocumento();
    const { renderDiagnostico } = makeRenderer(doc);
    renderDiagnostico({ equipo, encuentro });
    addFooters(doc);
    doc.save(`diagnostico-${slugify(equipo.nombre)}.pdf`);
  };

  return { descargarPDF };
}
