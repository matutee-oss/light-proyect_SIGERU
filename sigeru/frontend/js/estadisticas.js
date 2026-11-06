// ============================================================
// estadisticas.js — panel de control con estadísticas (Chart.js)
// Paleta validada para daltonismo: reportadas (rojo arcilla) y
// resueltas (verde petróleo). Un solo eje por gráfico.
// ============================================================

const sesion = exigirSesion(['administrador']);

const COLOR = {
  reportadas: '#b8533c',
  resueltas: '#0f8a72',
  serieUnica: '#0f8a72',
  grilla: 'rgba(22, 36, 31, 0.08)',
  texto: '#4a5852'
};

const graficos = {};

function configurarChart() {
  if (typeof Chart === 'undefined') return false;
  Chart.defaults.font.family = "'Inter', Arial, sans-serif";
  Chart.defaults.font.size = 12;
  Chart.defaults.color = COLOR.texto;
  Chart.defaults.plugins.tooltip.backgroundColor = '#0f332c';
  Chart.defaults.plugins.tooltip.padding = 10;
  Chart.defaults.plugins.tooltip.cornerRadius = 6;
  Chart.defaults.plugins.legend.labels.usePointStyle = true;
  Chart.defaults.plugins.legend.labels.boxWidth = 8;
  return true;
}

function ejes(opciones = {}) {
  return {
    x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, autoSkipPadding: 12 }, ...opciones.x },
    y: { beginAtZero: true, grid: { color: COLOR.grilla }, border: { display: false }, ticks: { precision: 0 }, ...opciones.y }
  };
}

function dibujar(id, configuracion) {
  if (graficos[id]) graficos[id].destroy();
  const lienzo = document.getElementById(id);
  if (!lienzo || typeof Chart === 'undefined') return;
  graficos[id] = new Chart(lienzo, configuracion);
}

function tablaSimple(idDestino, encabezados, filas) {
  document.getElementById(idDestino).innerHTML = `
    <table class="listado" style="margin-top:10px;">
      <thead><tr>${encabezados.map(e => `<th>${escaparHtml(e)}</th>`).join('')}</tr></thead>
      <tbody>${filas.map(f => `<tr>${f.map(c => `<td>${escaparHtml(c)}</td>`).join('')}</tr>`).join('')}</tbody>
    </table>`;
}

function diaCorto(dia) {
  return aFecha(dia + ' 12:00:00').toLocaleDateString('es-UY', { day: '2-digit', month: '2-digit' });
}

/** "▲ 12 %" en rojo si sube algo malo, en verde si baja, etc. */
function textoVariacion(valor, subirEsBueno) {
  if (valor === null || valor === undefined) return '<span>sin datos del mes anterior</span>';
  if (valor === 0) return '<span>igual que el mes anterior</span>';
  const sube = valor > 0;
  const clase = sube === subirEsBueno ? (sube ? 'variacion-sube-bien' : 'variacion-baja-bien') : (sube ? 'variacion-sube-mal' : 'variacion-baja-mal');
  return `<span class="${clase}">${sube ? '▲' : '▼'} ${Math.abs(valor)} %</span> vs. mes anterior`;
}

// ------------------------------------------------------------
// Secciones
// ------------------------------------------------------------

function pintarKpis(d) {
  const k = d.indicadores;
  const c = d.contenedores;
  document.getElementById('kpis').innerHTML = `
    <div class="kpi"><div class="kpi-etiqueta">Incidencias del mes</div><div class="kpi-valor">${k.incidencias_mes}</div>
      <div class="kpi-detalle">${textoVariacion(k.incidencias_variacion, false)}</div></div>
    <div class="kpi"><div class="kpi-etiqueta">Pendientes ahora</div><div class="kpi-valor" style="color:var(--clay);">${k.incidencias_pendientes}</div>
      <div class="kpi-detalle"><a href="incidencias.html">Ver incidencias →</a></div></div>
    <div class="kpi"><div class="kpi-etiqueta">Resueltas en el mes</div><div class="kpi-valor">${k.porcentaje_resueltas}<small>%</small></div>
      <div class="kpi-detalle">${k.horas_promedio_resolucion !== null ? `en ${escaparHtml(k.horas_promedio_resolucion)} h promedio` : 'sin resoluciones todavía'}</div></div>
    <div class="kpi"><div class="kpi-etiqueta">Desbordes del mes</div><div class="kpi-valor">${k.desbordes_mes}</div>
      <div class="kpi-detalle">${textoVariacion(k.desbordes_variacion, false)}</div></div>
    <div class="kpi"><div class="kpi-etiqueta">Recolecciones</div><div class="kpi-valor">${k.recolecciones_mes}</div>
      <div class="kpi-detalle">${Math.round(k.kg_mes).toLocaleString('es-UY')} kg · ${textoVariacion(k.kg_variacion, true)}</div></div>
    <div class="kpi"><div class="kpi-etiqueta">Contenedores críticos</div><div class="kpi-valor" style="color:var(--amber);">${c.casi_llenos}</div>
      <div class="kpi-detalle">al 80 % o más · <a href="mapa.html?nivel=80">ver mapa →</a></div></div>`;
}

function pintarTop(top) {
  const destino = document.getElementById('top-desbordados');
  if (!top.length) {
    destino.innerHTML = textoVacio('No hubo desbordes reportados en este mes.');
    return;
  }
  destino.innerHTML = '<ol class="top-lista">' + top.map(t => `
    <li>
      <div>
        <strong>${escaparHtml(t.ubicacion)}</strong>
        <div class="mono" style="font-size:11.5px; color:var(--ink-soft);">${escaparHtml(t.nombre_zona || 'Sin zona')} · último ${escaparHtml(tiempoRelativo(t.ultimo_desborde))} · <a href="mapa.html?contenedor=${t.id_contenedor}">mapa</a></div>
      </div>
      <span class="top-cantidad">${t.desbordes}<small>${Number(t.desbordes) === 1 ? 'vez' : 'veces'}</small></span>
    </li>`).join('') + '</ol>';
}

function pintarEstadoContenedores(c) {
  const filas = [
    ['Funcionales', c.funcionales, 'var(--petrol)'],
    ['Desbordados', c.desbordados, 'var(--clay)'],
    ['Rotos', c.rotos, 'var(--amber)'],
    ['Al 80 % o más', c.casi_llenos, 'var(--amber)']
  ];
  document.getElementById('estado-contenedores').innerHTML = `
    <div class="kpi" style="margin-bottom:18px; box-shadow:none;">
      <div class="kpi-etiqueta">Nivel promedio de llenado</div>
      <div class="kpi-valor">${c.nivel_promedio}<small>%</small></div>
      <div class="nivel-barra"><div style="width:${c.nivel_promedio}%; background:var(--petrol);"></div></div>
    </div>
    ${filas.map(([texto, valor, color]) => `
      <div style="margin-bottom:12px;">
        <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
          <span>${texto}</span><span class="mono" style="color:var(--ink-soft);">${valor} de ${c.total}</span>
        </div>
        <div class="barra-mini"><div class="barra-mini-fill" style="width:${c.total ? Math.round(valor / c.total * 100) : 0}%; background:${color};"></div></div>
      </div>`).join('')}`;
}

function pintarEvolucion(serie) {
  dibujar('g-evolucion', {
    type: 'line',
    data: {
      labels: serie.map(s => diaCorto(s.dia)),
      datasets: [
        { label: 'Reportadas', data: serie.map(s => s.reportadas), borderColor: COLOR.reportadas, backgroundColor: COLOR.reportadas, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, tension: 0 },
        { label: 'Resueltas', data: serie.map(s => s.resueltas), borderColor: COLOR.resueltas, backgroundColor: COLOR.resueltas, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, tension: 0, borderDash: [5, 4] }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { position: 'top', align: 'end' } },
      scales: ejes()
    }
  });
  tablaSimple('t-evolucion', ['Día', 'Reportadas', 'Resueltas'], serie.map(s => [diaCorto(s.dia), s.reportadas, s.resueltas]));
}

function pintarZonas(zonas) {
  const etiquetas = zonas.map(z => z.nombre_zona.replace('Municipio ', ''));
  const resueltas = zonas.map(z => Number(z.total) - Number(z.pendientes));
  const pendientes = zonas.map(z => Number(z.pendientes));
  dibujar('g-zonas', {
    type: 'bar',
    data: {
      labels: etiquetas,
      datasets: [
        { label: 'Pendientes', data: pendientes, backgroundColor: COLOR.reportadas, borderRadius: 4, borderColor: '#ffffff', borderWidth: { top: 2 }, borderSkipped: false },
        { label: 'Resueltas', data: resueltas, backgroundColor: COLOR.resueltas, borderRadius: 4, borderColor: '#ffffff', borderWidth: { top: 2 }, borderSkipped: false }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'top', align: 'end' },
        tooltip: { callbacks: { title: items => 'Municipio ' + items[0].label } }
      },
      scales: ejes({ x: { stacked: true }, y: { stacked: true } })
    }
  });
  tablaSimple('t-zonas', ['Zona', 'Total', 'Pendientes', 'Resueltas'],
    zonas.map((z, i) => [z.nombre_zona, z.total, pendientes[i], resueltas[i]]));
}

function pintarTipos(tipos) {
  dibujar('g-tipos', {
    type: 'bar',
    data: {
      labels: tipos.map(t => etiqueta('tipo', t.tipo)),
      datasets: [{ label: 'Incidencias', data: tipos.map(t => Number(t.total)), backgroundColor: COLOR.serieUnica, borderRadius: 4, barThickness: 22 }]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { beginAtZero: true, grid: { color: COLOR.grilla }, border: { display: false }, ticks: { precision: 0 } },
        y: { grid: { display: false } }
      }
    }
  });
  tablaSimple('t-tipos', ['Tipo', 'Cantidad'], tipos.map(t => [etiqueta('tipo', t.tipo), t.total]));
}

function pintarKg(serie) {
  dibujar('g-kg', {
    type: 'bar',
    data: {
      labels: serie.map(s => diaCorto(s.dia)),
      datasets: [{ label: 'Kilos', data: serie.map(s => s.kg), backgroundColor: COLOR.serieUnica, borderRadius: 4, maxBarThickness: 26 }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: ctx => `${Math.round(ctx.parsed.y).toLocaleString('es-UY')} kg · ${serie[ctx.dataIndex].recolecciones} recolección(es)` } }
      },
      scales: ejes({ y: { ticks: { callback: v => v.toLocaleString('es-UY') + ' kg' } } })
    }
  });
  tablaSimple('t-kg', ['Día', 'Recolecciones', 'Kilos'], serie.map(s => [diaCorto(s.dia), s.recolecciones, Math.round(s.kg)]));
}

function pintarCentros(centros) {
  document.getElementById('centros').innerHTML = centros.length
    ? centros.map(c => {
        const color = c.porcentaje >= 100 ? 'var(--clay)' : c.porcentaje >= 80 ? 'var(--amber)' : 'var(--petrol)';
        return `
          <div style="margin-bottom:18px;">
            <div style="display:flex; justify-content:space-between; gap:12px;">
              <strong>${escaparHtml(c.nombre)}</strong>
              <span class="mono" style="color:var(--ink-soft);">${c.porcentaje} %</span>
            </div>
            <div class="nivel-barra" style="height:12px;"><div style="width:${Math.min(100, c.porcentaje)}%; background:${color};"></div></div>
            <div class="mono" style="font-size:11.5px; color:var(--ink-faint);">${Number(c.toneladas_30d).toLocaleString('es-UY')} t ingresadas · capacidad ${Number(c.capacidad).toLocaleString('es-UY')} m³</div>
          </div>`;
      }).join('')
    : textoVacio('No hay centros de acopio cargados.');
}

function pintarCuadrillas(filas) {
  const tabla = document.getElementById('tabla-cuadrillas');
  tabla.innerHTML = filas.length
    ? filas.map(c => `
        <tr>
          <td>${escaparHtml(c.nombre + ' ' + c.apellido)}</td>
          <td class="mono">${c.resueltas}</td>
          <td class="mono">${c.horas_promedio !== null ? escaparHtml(c.horas_promedio) + ' h' : '—'}</td>
        </tr>`).join('')
    : `<tr><td colspan="3">${textoVacio('No hay cuadrillas registradas.')}</td></tr>`;
}

// ------------------------------------------------------------
// Carga
// ------------------------------------------------------------

async function cargar() {
  const mes = document.getElementById('e-mes').value;
  const { ok, datos } = await pedirJson(`${API_URL}/estadisticas.php?mes=${encodeURIComponent(mes)}`);
  if (!ok) {
    document.getElementById('kpis').innerHTML = `<div class="mensaje error">${escaparHtml(datos.mensaje)}</div>`;
    return;
  }

  pintarKpis(datos);
  pintarTop(datos.top_desbordados);
  pintarEstadoContenedores(datos.contenedores);
  pintarCentros(datos.centros);
  pintarCuadrillas(datos.rendimiento_cuadrillas);

  if (configurarChart()) {
    pintarEvolucion(datos.evolucion_incidencias);
    pintarZonas(datos.incidencias_por_zona);
    pintarTipos(datos.incidencias_por_tipo);
    pintarKg(datos.kg_por_dia);
  }
}

if (sesion) {
  const hoy = new Date();
  const selector = document.getElementById('e-mes');
  selector.value = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}`;
  selector.max = selector.value;
  selector.addEventListener('change', cargar);
  cargar();
}
