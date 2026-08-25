import {
  GREEN, DARK, GRAY, LGRAY, YELLOW, RED, BLUE, PURPLE, ORANGE,
  PAGE_W, MARGIN, CONTENT_W,
  slugify, crearDocumento, addFooters, makeBaseRenderer,
} from './pdfHelpers.js';

function makeRenderer(doc) {
  const { s, checkBreak, setFont, addSectionTitle, addParagraph, addBulletList, drawBadge, addHighlightedCard } = makeBaseRenderer(doc);

  // ── renderReto ───────────────────────────────────────────────────────────────

  const renderReto = (reto) => {
    s.y = 0;

    // Banda verde superior
    doc.setFillColor(...GREEN);
    doc.rect(0, 0, PAGE_W, 8, 'F');
    setFont(6, 'bold', [255, 255, 255]);
    doc.text('DUALAB · FICHA DE MICRORETO', MARGIN, 5.5);

    s.y = 13;

    // Título
    setFont(18, 'bold', DARK);
    const titleLines = doc.splitTextToSize(reto.titulo || 'Sin título', CONTENT_W);
    doc.text(titleLines, MARGIN, s.y);
    s.y += titleLines.length * 7 + 1;

    // Subtítulo
    if (reto.subtitulo) {
      setFont(9.5, 'normal', GRAY);
      const subLines = doc.splitTextToSize(reto.subtitulo, CONTENT_W);
      doc.text(subLines, MARGIN, s.y);
      s.y += subLines.length * 4.8 + 2;
    }

    s.y += 3;

    // Badges (se omiten si sobrepasan el margen derecho)
    let bx = MARGIN;
    if (reto.empresa_nombre) bx += drawBadge(reto.empresa_nombre, DARK, [255, 255, 255], bx, s.y);
    if (reto.familia)        bx += drawBadge(reto.familia,        [229,231,235], DARK,   bx, s.y);
    if (reto.ciclo)          bx += drawBadge(reto.ciclo,          [209,250,229], GREEN,  bx, s.y);
    if (reto.nivel_grupo)    bx += drawBadge(`Nivel ${reto.nivel_grupo}`, [243,244,246], GRAY, bx, s.y);
    if (reto.empresa?.sector) bx += drawBadge(reto.empresa.sector, [243,244,246], GRAY, bx, s.y);
    if (reto.empresa?.tamano) bx += drawBadge(reto.empresa.tamano, [243,244,246], GRAY, bx, s.y);
    if (reto.centro_educativo || reto.centro)
      drawBadge(reto.centro_educativo || reto.centro, [243,244,246], GRAY, bx, s.y);

    s.y += 10;

    // Separador
    doc.setDrawColor(229, 231, 235);
    doc.setLineWidth(0.3);
    doc.line(MARGIN, s.y, MARGIN + CONTENT_W, s.y);
    s.y += 6;

    // ── DATOS RECOGIDOS DE LA EMPRESA ────────────────────────────────────────
    // Tarjeta destacada (fondo + borde naranja), igual que la tarjeta en pantalla — no un
    // simple título de sección. Diagnóstico crudo, antes del resumen que redacta la IA debajo.
    const emp = reto.empresa;
    if (emp) {
      addHighlightedCard('Datos recogidos de la empresa', ORANGE, [255, 247, 237], [253, 186, 116], [
        { label: 'Su día a día', text: emp.dia_a_normal },
        { label: 'Fricciones de la empresa', text: [emp.friccion_area, emp.friccion_problema].filter(Boolean).join('\n') },
        { label: 'Consecuencias', text: emp.consecuencias },
        { label: 'Restricciones', text: emp.restricciones },
        { label: 'Lo que no quieren', text: emp.lo_que_no_quieren },
      ]);
    }

    // ── RESUMEN DE DIAGNÓSTICO (lectura de la IA a partir de los datos de arriba) ──
    checkBreak(10);
    setFont(7.5, 'bold', DARK);
    doc.text('RESUMEN DE DIAGNÓSTICO', MARGIN, s.y);
    doc.setDrawColor(...DARK);
    doc.setLineWidth(0.3);
    doc.line(MARGIN, s.y + 1.5, MARGIN + CONTENT_W, s.y + 1.5);
    s.y += 7;

    // ── QUIÉN ES / DÍA A DÍA ────────────────────────────────────────────────
    if (reto.quien_es) {
      addSectionTitle(`¿Quién es ${reto.empresa_nombre || 'la empresa'}?`, GREEN);
      addParagraph(reto.quien_es);
    }

    if (reto.dia_a_dia) {
      addSectionTitle('Su día a día', GREEN);
      addParagraph(reto.dia_a_dia);
    }

    // ── DIFICULTADES ─────────────────────────────────────────────────────────
    if (reto.dificultades?.length) {
      addSectionTitle('Dificultades', YELLOW);
      addBulletList(reto.dificultades, YELLOW);
    }

    // ── PREGUNTA DEL RETO ────────────────────────────────────────────────────
    if (reto.pregunta_reto) {
      // Font ANTES de splitTextToSize para que el wrapping sea correcto
      setFont(10, 'bold', DARK);
      const pregLines = doc.splitTextToSize(reto.pregunta_reto, CONTENT_W - 14);
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
      s.y += boxH + 7;
    }

    // ── QUÉ NECESITAN / LIMITACIONES ────────────────────────────────────────
    if (reto.que_necesitan?.length) {
      addSectionTitle('Qué necesitan', GREEN);
      addBulletList(reto.que_necesitan, GREEN);
    }

    if (reto.limitaciones?.length) {
      addSectionTitle('Limitaciones', RED);
      addBulletList(reto.limitaciones, RED);
    }

    // ── PROTOTIPOS / ODS ─────────────────────────────────────────────────────
    if (reto.prototipos?.length) {
      addSectionTitle('Ejemplos de Prototipos', GREEN);
      addBulletList(reto.prototipos, GREEN);
    }

    if (reto.ods_sugeridos?.length) {
      addSectionTitle('ODS Sugeridos', BLUE);
      reto.ods_sugeridos.forEach(ods => {
        checkBreak(6);
        setFont(8.5, 'bold', DARK);
        doc.text(String(ods), MARGIN + 2, s.y);
        s.y += 5;
      });
      s.y += 3;
    }

    // ── SOFT SKILLS ──────────────────────────────────────────────────────────
    if (reto.soft_skills?.length) {
      addSectionTitle('Soft Skills', PURPLE);
      reto.soft_skills.forEach(skill => {
        checkBreak(5);
        setFont(8.5, 'bold', PURPLE);
        doc.text('•', MARGIN + 2, s.y);
        setFont(8.5, 'normal', DARK);
        doc.text(String(skill), MARGIN + 7, s.y);
        s.y += 5;
      });
      s.y += 3;
    }

    // ── RA / CE ──────────────────────────────────────────────────────────────
    if (reto.evaluacion_oficial?.length) {
      addSectionTitle('RA/CE Seleccionados', DARK);

      reto.evaluacion_oficial.forEach((evalObj, idx) => {
        // La fuente se fija ANTES de medir con splitTextToSize (no solo antes de
        // imprimir) — jsPDF mide con la fuente activa en ese instante, y medir con
        // una más estrecha que la usada al imprimir hace que el texto desborde el margen.
        setFont(8.5, 'bold', DARK);
        const modLines = doc.splitTextToSize(evalObj.modulo || '', CONTENT_W - 6);
        setFont(8.5, 'normal', LGRAY);
        const raLines  = doc.splitTextToSize(evalObj.ra || '', CONTENT_W - 6);
        const ceItems  = evalObj.ce || [];
        setFont(7.5, 'italic', LGRAY);
        const aplLines = evalObj.aplicacion ? doc.splitTextToSize(evalObj.aplicacion, CONTENT_W - 6) : [];

        // Cabecera del módulo (altura acotada, nunca desborda)
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
        s.y += modH + 3;

        // Resultado de Aprendizaje — línea a línea
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

        // Criterios de Evaluación — cada CE con su propio checkBreak
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

        // Aplicación
        if (aplLines.length) {
          s.y += 2;
          checkBreak(aplLines.length * 4.5 + 5);
          setFont(7.5, 'bold', DARK);
          const aplLabelW = doc.getTextWidth('Aplicación: ');
          doc.text('Aplicación: ', MARGIN + 2, s.y);
          setFont(7.5, 'italic', LGRAY);
          doc.text(aplLines, MARGIN + 2 + aplLabelW, s.y);
          s.y += aplLines.length * 4.5 + 2;
        }

        // Separador entre bloques RA/CE (excepto el último)
        s.y += 4;
        if (idx < reto.evaluacion_oficial.length - 1) {
          checkBreak(6);
          doc.setDrawColor(229, 231, 235);
          doc.setLineWidth(0.2);
          doc.line(MARGIN + 4, s.y, MARGIN + CONTENT_W - 4, s.y);
          s.y += 5;
        }
      });
    }

    // ── VARIANTES ────────────────────────────────────────────────────────────
    if (reto.variantes?.length) {
      addSectionTitle('Variantes', GREEN);
      reto.variantes.forEach(varItem => {
        const hasColon  = varItem.includes(':');
        const label     = hasColon ? varItem.split(':')[0] : null;
        const body      = hasColon ? varItem.substring(varItem.indexOf(':') + 1).trim() : varItem;
        setFont(8.5, 'normal', LGRAY);
        const bodyLines = doc.splitTextToSize(body, CONTENT_W - 8);

        // Cabecera con etiqueta en box pequeño
        if (label) {
          checkBreak(9);
          doc.setFillColor(249, 250, 251);
          doc.setDrawColor(229, 231, 235);
          doc.setLineWidth(0.25);
          doc.roundedRect(MARGIN, s.y, CONTENT_W, 9, 2, 2, 'FD');
          setFont(8, 'bold', DARK);
          doc.text(label, MARGIN + 4, s.y + 5.5);
          s.y += 10;
        }

        // Cuerpo línea a línea con margen izquierdo
        setFont(8.5, 'normal', LGRAY);
        for (const line of bodyLines) {
          checkBreak(5);
          doc.text(line, MARGIN + 4, s.y);
          s.y += 4.5;
        }
        s.y += 5;
      });
      s.y += 2;
    }

    // ── TIPS PROFESORADO ─────────────────────────────────────────────────────
    if (reto.tips_profesorado?.length) {
      checkBreak(14);
      setFont(6.5, 'bold', YELLOW);
      doc.text('USO EXCLUSIVO DOCENTE', MARGIN, s.y);
      s.y += 5;
      addSectionTitle('Guía de Implementación', YELLOW);

      reto.tips_profesorado.forEach(tip => {
        const hasColon  = tip.includes(':');
        const label     = hasColon ? tip.split(':')[0] : null;
        const body      = hasColon ? tip.substring(tip.indexOf(':') + 1).trim() : tip;
        setFont(8.5, 'normal', LGRAY);
        const bodyLines = doc.splitTextToSize(body, CONTENT_W - 8);

        // Cabecera con etiqueta en box pequeño
        if (label) {
          checkBreak(9);
          doc.setFillColor(255, 255, 255);
          doc.setDrawColor(229, 231, 235);
          doc.setLineWidth(0.25);
          doc.roundedRect(MARGIN, s.y, CONTENT_W, 9, 2, 2, 'FD');
          setFont(7.5, 'bold', GREEN);
          doc.text(label.toUpperCase(), MARGIN + 4, s.y + 5.5);
          s.y += 10;
        }

        // Cuerpo línea a línea
        setFont(8.5, 'normal', LGRAY);
        for (const line of bodyLines) {
          checkBreak(5);
          doc.text(line, MARGIN + 4, s.y);
          s.y += 4.5;
        }
        s.y += 5;
      });
    }
  };

  // ── renderCoverPage ──────────────────────────────────────────────────────────

  const renderCoverPage = (retos, titulo, subtitulo) => {
    s.y = 0;

    doc.setFillColor(...GREEN);
    doc.rect(0, 0, PAGE_W, 8, 'F');
    setFont(6, 'bold', [255, 255, 255]);
    doc.text('DUALAB · BIBLIOTECA DE MICRORETOS', MARGIN, 5.5);

    s.y = 22;

    setFont(22, 'bold', DARK);
    const titleLines = doc.splitTextToSize(titulo, CONTENT_W);
    doc.text(titleLines, MARGIN, s.y);
    s.y += titleLines.length * 9 + 3;

    if (subtitulo) {
      setFont(10, 'normal', GRAY);
      const subLines = doc.splitTextToSize(subtitulo, CONTENT_W);
      doc.text(subLines, MARGIN, s.y);
      s.y += subLines.length * 5.5 + 4;
    }

    s.y += 6;

    // Cajas de estadísticas
    const stats = {
      total: retos.length,
      Bajo:  retos.filter(r => r.nivel_grupo === 'Bajo').length,
      Medio: retos.filter(r => r.nivel_grupo === 'Medio').length,
      Alto:  retos.filter(r => r.nivel_grupo === 'Alto').length,
    };
    const statsItems = [
      { label: 'Total',  value: stats.total, color: DARK   },
      { label: 'Bajo',   value: stats.Bajo,  color: GREEN  },
      { label: 'Medio',  value: stats.Medio, color: YELLOW },
      { label: 'Alto',   value: stats.Alto,  color: RED    },
    ];
    const boxW = (CONTENT_W - 3 * 3) / 4;
    statsItems.forEach((item, i) => {
      const bx = MARGIN + i * (boxW + 3);
      doc.setFillColor(249, 250, 251);
      doc.setDrawColor(229, 231, 235);
      doc.setLineWidth(0.25);
      doc.roundedRect(bx, s.y, boxW, 20, 2, 2, 'FD');
      setFont(17, 'bold', item.color);
      const valW = doc.getTextWidth(String(item.value));
      doc.text(String(item.value), bx + (boxW - valW) / 2, s.y + 11);
      setFont(6, 'bold', GRAY);
      const labelW = doc.getTextWidth(item.label.toUpperCase());
      doc.text(item.label.toUpperCase(), bx + (boxW - labelW) / 2, s.y + 17);
    });
    s.y += 27;

    // Lista de ciclos formativos
    const ciclos = [...new Set(retos.map(r => r.ciclo).filter(Boolean))].sort();
    if (ciclos.length > 0) {
      checkBreak(8);
      setFont(6.5, 'bold', GRAY);
      doc.text(`${ciclos.length} CICLO${ciclos.length !== 1 ? 'S' : ''} FORMATIVO${ciclos.length !== 1 ? 'S' : ''}`, MARGIN, s.y);
      s.y += 5;
      ciclos.forEach(ciclo => {
        checkBreak(5);
        setFont(8, 'normal', LGRAY);
        const cLines = doc.splitTextToSize(`• ${ciclo}`, CONTENT_W - 4);
        doc.text(cLines, MARGIN + 2, s.y);
        s.y += cLines.length * 4.2 + 0.8;
      });
      s.y += 4;
    }

    // Separador
    checkBreak(12);
    doc.setDrawColor(229, 231, 235);
    doc.setLineWidth(0.5);
    doc.line(MARGIN, s.y, MARGIN + CONTENT_W, s.y);
    s.y += 8;

    // Índice de micro-retos
    setFont(7.5, 'bold', DARK);
    doc.text('ÍNDICE DE MICRO-RETOS', MARGIN, s.y);
    s.y += 8;

    const MAX_INDEX = 45;
    const displayRetos = retos.slice(0, MAX_INDEX);

    if (ciclos.length > 1) {
      ciclos.forEach(ciclo => {
        const cicloRetos = displayRetos.filter(r => r.ciclo === ciclo);
        if (!cicloRetos.length) return;

        checkBreak(12);
        setFont(6.5, 'bold', GREEN);
        const cicloW = doc.getTextWidth(ciclo.toUpperCase());
        doc.text(ciclo.toUpperCase(), MARGIN, s.y);
        doc.setDrawColor(...GREEN);
        doc.setLineWidth(0.2);
        doc.line(MARGIN, s.y + 1.5, MARGIN + cicloW, s.y + 1.5);
        s.y += 6;

        cicloRetos.forEach(reto => {
          const num = retos.indexOf(reto) + 1;
          checkBreak(9);
          setFont(7.5, 'bold', DARK);
          doc.text(`${num}.`, MARGIN + 2, s.y);
          setFont(7.5, 'normal', DARK);
          const tLine = doc.splitTextToSize(reto.titulo || 'Sin título', CONTENT_W - 14)[0];
          doc.text(tLine, MARGIN + 9, s.y);
          s.y += 4.5;
          const meta = [
            reto.empresa_nombre,
            reto.nivel_grupo ? `Nivel ${reto.nivel_grupo}` : null,
          ].filter(Boolean).join(' · ');
          if (meta) {
            checkBreak(5);
            setFont(6.5, 'normal', GRAY);
            doc.text(meta, MARGIN + 9, s.y);
            s.y += 4;
          }
        });
        s.y += 3;
      });
    } else {
      displayRetos.forEach((reto, i) => {
        checkBreak(9);
        setFont(7.5, 'bold', DARK);
        doc.text(`${i + 1}.`, MARGIN + 2, s.y);
        setFont(7.5, 'normal', DARK);
        const tLine = doc.splitTextToSize(reto.titulo || 'Sin título', CONTENT_W - 14)[0];
        doc.text(tLine, MARGIN + 9, s.y);
        s.y += 4.5;
        const meta = [
          reto.empresa_nombre,
          reto.nivel_grupo ? `Nivel ${reto.nivel_grupo}` : null,
        ].filter(Boolean).join(' · ');
        if (meta) {
          checkBreak(5);
          setFont(6.5, 'normal', GRAY);
          doc.text(meta, MARGIN + 9, s.y);
          s.y += 4;
        }
      });
    }

    if (retos.length > MAX_INDEX) {
      checkBreak(8);
      setFont(7.5, 'italic', GRAY);
      doc.text(`... y ${retos.length - MAX_INDEX} micro-retos más`, MARGIN + 2, s.y);
    }
  };

  return { renderReto, renderCoverPage };
}

// ── API pública ───────────────────────────────────────────────────────────────

export function usePdfExport() {
  const descargarPDF = (reto) => {
    const doc = crearDocumento();
    const { renderReto } = makeRenderer(doc);
    renderReto(reto);
    addFooters(doc);
    doc.save(`microreto-${slugify(reto.titulo)}.pdf`);
  };

  const descargarPDFGrupo = (retos, titulo, subtitulo = '') => {
    if (!retos?.length) return;
    const doc = crearDocumento();
    const { renderReto, renderCoverPage } = makeRenderer(doc);

    renderCoverPage(retos, titulo, subtitulo);

    retos.forEach(reto => {
      doc.addPage();
      renderReto(reto);
    });

    addFooters(doc);
    doc.save(`microretos-${slugify(titulo)}.pdf`);
  };

  return { descargarPDF, descargarPDFGrupo };
}
