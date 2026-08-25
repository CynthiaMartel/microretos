import { duracionPorFase } from '../config/fasesProyecto.js';
import {
  GREEN, DARK, GRAY, LGRAY, BLUE,
  PAGE_W, MARGIN, CONTENT_W,
  slugify, crearDocumento, addFooters, makeBaseRenderer,
} from './pdfHelpers.js';

function makeRenderer(doc) {
  const { s, checkBreak, setFont, addSectionTitle, addParagraph, addBulletList, drawBadge } = makeBaseRenderer(doc);

  const renderProyecto = (p) => {
    s.y = 0;

    // ── BANDA SUPERIOR ───────────────────────────────────────────────────────
    doc.setFillColor(...GREEN);
    doc.rect(0, 0, PAGE_W, 8, 'F');
    setFont(6, 'bold', [255, 255, 255]);
    doc.text('DUALAB · FICHA DE MICROPROYECTO', MARGIN, 5.5);

    s.y = 14;

    // Título
    setFont(18, 'bold', DARK);
    const titleLines = doc.splitTextToSize(p.titulo || 'Sin título', CONTENT_W);
    doc.text(titleLines, MARGIN, s.y);
    s.y += titleLines.length * 7 + 2;

    // Badges (se omiten si sobrepasan el margen derecho)
    let bx = MARGIN;
    const estadoColors = {
      publicado: { bg: [209, 250, 229], txt: GREEN },
      borrador:  { bg: [254, 243, 199], txt: [161, 128, 0] },
      archivado: { bg: [243, 244, 246], txt: GRAY },
    };
    const ec = estadoColors[p.estado] || estadoColors.borrador;
    bx += drawBadge((p.estado || 'borrador').toUpperCase(), ec.bg, ec.txt, bx, s.y);
    if (p.empresa_nombre) bx += drawBadge(p.empresa_nombre,  DARK,            [255,255,255], bx, s.y);
    if (p.ciclo_nombre)   bx += drawBadge(p.ciclo_nombre,    [209, 250, 229], GREEN,         bx, s.y);
    if (p.centro_nombre)  drawBadge(p.centro_nombre,          [243, 244, 246], GRAY,          bx, s.y);
    s.y += 10;

    // Separador
    doc.setDrawColor(229, 231, 235);
    doc.setLineWidth(0.3);
    doc.line(MARGIN, s.y, MARGIN + CONTENT_W, s.y);
    s.y += 6;

    // ── EMPRESA ──────────────────────────────────────────────────────────────
    const emp = p.datos_empresa;
    if (emp?.nombre) {
      addSectionTitle('Empresa', GREEN);
      checkBreak(5);
      setFont(9, 'bold', DARK);
      doc.text(emp.nombre, MARGIN, s.y);
      s.y += 5;
      if (emp.sector) {
        checkBreak(5);
        setFont(8, 'normal', GRAY);
        doc.text(emp.sector, MARGIN, s.y);
        s.y += 4.5;
      }
      if (emp.persona_contacto || emp.email) {
        checkBreak(5);
        setFont(8, 'normal', LGRAY);
        const contacto = [emp.persona_contacto, emp.email].filter(Boolean).join(' · ');
        doc.text(contacto, MARGIN, s.y);
        s.y += 4.5;
      }
      if (emp.descripcion) addParagraph(emp.descripcion);
    }

    // ── EQUIPO ───────────────────────────────────────────────────────────────
    if (p.equipo?.alumnos?.length) {
      addSectionTitle(`Equipo (${p.equipo.alumnos.length} personas)`, DARK);
      p.equipo.alumnos.forEach(a => {
        checkBreak(6);
        const nombre = a.nombre || '';
        const rol    = a.rol ? ` · ${a.rol}` : '';
        setFont(8.5, 'normal', LGRAY);
        doc.text(`• ${nombre}`, MARGIN, s.y);
        if (a.rol) {
          const nw = doc.getTextWidth(`• ${nombre}`);
          setFont(8, 'normal', GRAY);
          doc.text(rol, MARGIN + nw, s.y);
        }
        s.y += 4.8;
      });
      s.y += 2;
    }

    // ── EL RETO ──────────────────────────────────────────────────────────────
    const reto = p.diseno_reto;
    if (reto?.descripcion || reto?.pregunta_reto) {
      addSectionTitle('El reto', GREEN);

      if (reto.pregunta_reto) {
        // Font ANTES de splitTextToSize para que el wrapping sea correcto
        setFont(10, 'bold', DARK);
        const pregLines = doc.splitTextToSize(`"${reto.pregunta_reto}"`, CONTENT_W - 14);
        const boxH = pregLines.length * 5.2 + 13;
        checkBreak(boxH + 6);
        doc.setFillColor(240, 253, 244);
        doc.roundedRect(MARGIN, s.y, CONTENT_W, boxH, 2, 2, 'F');
        doc.setFillColor(...GREEN);
        doc.rect(MARGIN, s.y, 2.5, boxH, 'F');
        setFont(6.5, 'bold', GREEN);
        doc.text('PREGUNTA DEL RETO', MARGIN + 6, s.y + 5.5);
        setFont(10, 'bold', DARK);
        doc.text(pregLines, MARGIN + 6, s.y + 11);
        s.y += boxH + 6;
      }

      if (reto.descripcion) addParagraph(reto.descripcion);

      if (reto.entregables) {
        checkBreak(6);
        setFont(7, 'bold', GRAY);
        doc.text('ENTREGABLES', MARGIN, s.y);
        s.y += 4;
        addParagraph(reto.entregables);
      }
    }

    // ── MÓDULOS ──────────────────────────────────────────────────────────────
    if (p.modulos_seleccionados?.length) {
      addSectionTitle(`Módulos (${p.modulos_seleccionados.length})`, DARK);
      const nombres = p.modulos_seleccionados.map(m => m.nombre || m).filter(Boolean);
      addBulletList(nombres, GREEN);
    }

    // ── RA/CE ────────────────────────────────────────────────────────────────
    if (p.ra_ce) {
      addSectionTitle('Resultados de aprendizaje y criterios de evaluación', DARK);
      addParagraph(p.ra_ce);
    }

    // ── FASES DEL PROYECTO ───────────────────────────────────────────────────
    const fases = p.diseno_microproyecto?.fases;
    if (fases?.length) {
      addSectionTitle('Fases del proyecto', BLUE);
      const clases = p.diseno_microproyecto?.clases;
      fases.forEach((f, i) => {
        const nombre     = f.nombre || `Fase ${i + 1}`;
        const numClases  = duracionPorFase(clases, i);
        const duracion   = numClases ? ` (${numClases} clase${numClases > 1 ? 's' : ''})` : '';

        // Calcular descripción con el font correcto ANTES de splitTextToSize
        setFont(7.5, 'normal', LGRAY);
        const descLines = f.descripcion ? doc.splitTextToSize(f.descripcion, CONTENT_W - 14) : [];

        const HEADER_H = 13;
        const boxH = HEADER_H + (descLines.length ? descLines.length * 4.2 + 6 : 0);
        checkBreak(boxH + 4);

        // Caja completa
        doc.setFillColor(249, 250, 251);
        doc.setDrawColor(229, 231, 235);
        doc.setLineWidth(0.2);
        doc.roundedRect(MARGIN, s.y, CONTENT_W, boxH, 2, 2, 'FD');

        // Círculo del número — anclado en la cabecera, siempre arriba
        const cx = MARGIN + 6.5;
        const cy = s.y + HEADER_H / 2;
        doc.setFillColor(209, 250, 229);
        doc.circle(cx, cy, 4, 'F');
        setFont(7.5, 'bold', GREEN);
        const numStr = String(i + 1);
        const numW = doc.getTextWidth(numStr);
        doc.text(numStr, cx - numW / 2, cy + 2.5);

        // Nombre y duración — centrado verticalmente en la cabecera
        setFont(9, 'bold', DARK);
        doc.text(`${nombre}${duracion}`, MARGIN + 15, cy + 2.5);

        // Separador y descripción
        if (descLines.length) {
          doc.setDrawColor(229, 231, 235);
          doc.setLineWidth(0.15);
          doc.line(MARGIN + 4, s.y + HEADER_H, MARGIN + CONTENT_W - 4, s.y + HEADER_H);
          setFont(7.5, 'normal', LGRAY);
          doc.text(descLines, MARGIN + 6, s.y + HEADER_H + 5);
        }

        s.y += boxH + 4;
      });
      s.y += 2;
    }

    // ── OBJETIVOS ────────────────────────────────────────────────────────────
    if (p.objetivos?.lista?.length) {
      addSectionTitle('Objetivos', GREEN);
      addBulletList(p.objetivos.lista, GREEN);
    }

    // ── KPIs ─────────────────────────────────────────────────────────────────
    if (p.kpis?.lista?.length) {
      addSectionTitle('KPIs', DARK);
      p.kpis.lista.forEach(kpi => {
        setFont(8.5, 'normal', LGRAY); // fijar la fuente ANTES de medir el wrap (ver addBulletList en pdfHelpers.js)
        const lines = doc.splitTextToSize(String(kpi), CONTENT_W - 6);
        checkBreak(5);
        setFont(8.5, 'bold', GREEN);
        doc.text('✓', MARGIN, s.y);
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
    }

    // ── VALIDACIÓN EMPRESA ───────────────────────────────────────────────────
    const val = p.validacion_empresa;
    if (val?.respuestas && Object.keys(val.respuestas).length) {
      addSectionTitle('Validación empresa', GREEN);
      const entries = Object.entries(val.respuestas);
      entries.forEach(([key, v]) => {
        checkBreak(8);
        const label = key.replace(/_/g, ' ');
        setFont(7, 'bold', GRAY);
        doc.text(label.toUpperCase(), MARGIN, s.y);
        const valColor = v === 'Sí' ? GREEN : v === 'No' ? [220, 38, 38] : [161, 128, 0];
        setFont(8, 'bold', valColor);
        doc.text(String(v), MARGIN + doc.getTextWidth(label.toUpperCase()) + 4, s.y);
        s.y += 5;
      });
      if (val.comentarios) {
        checkBreak(6);
        s.y += 2;
        setFont(7, 'bold', GRAY);
        doc.text('COMENTARIOS', MARGIN, s.y);
        s.y += 4.5;
        addParagraph(val.comentarios);
      }
      s.y += 2;
    }

    // ── RESUMEN EJECUTIVO ────────────────────────────────────────────────────
    if (p.resumen?.texto) {
      addSectionTitle('Resumen ejecutivo', DARK);
      addParagraph(p.resumen.texto);
    }
  };

  return { renderProyecto };
}

export function useMicroproyectoPdfExport() {
  const descargarPDF = (proyecto) => {
    const doc = crearDocumento();
    const { renderProyecto } = makeRenderer(doc);
    renderProyecto(proyecto);
    addFooters(doc);
    doc.save(`microproyecto-${slugify(proyecto.titulo)}.pdf`);
  };

  return { descargarPDF };
}
