// ============================================================
// informes.js — informes simples de incidencias y recolecciones
// ============================================================

const sesion = exigirSesion(['administrador']);

function tipoInforme() {
  return document.getElementById('i-tipo').value;
}

function parametros() {
  const params = new URLSearchParams({
    tipo: tipoInforme(),
    desde: document.getElementById('i-desde').value,
    hasta: document.getElementById('i-hasta').value
  });
  const zona = document.getElementById('i-zona').value;
  if (zona) params.set('id_zona', zona);
  if (tipoInforme() === 'incidencias') {
    params.set('situacion', document.getElementById('i-situacion').value);
    const tipo = document.getElementById('i-tipo-incidencia').value;
    if (tipo) params.set('tipo_incidencia', tipo);
  }
  return params;
}

function aplicarPeriodo(periodo) {
  const hoy = new Date();
  let desde;
  let hasta = hoy;
  if (periodo === 'mes') {
    desde = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
  } else if (periodo === 'anterior') {
    desde = new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1);
    hasta = new Date(hoy.getFullYear(), hoy.getMonth(), 0);
  } else {
    desde = new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate() - (Number(periodo) - 1));
  }
  document.getElementById('i-desde').value = fechaISO(desde);
  document.getElementById('i-hasta').value = fechaISO(hasta);
}

function tablaConteo(titulo, filas, traducir) {
  if (!filas.length) return '';
  const total = filas.reduce((s, f) => s + f.total, 0);
  return `
    <section class="tarjeta">
      <h3 style="font-size:20px; margin-bottom:12px;">${escaparHtml(titulo)}</h3>
      ${filas.map(f => {
        const ancho = Math.round(f.total / total * 100);
        return `
          <div style="margin-bottom:12px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
              <span>${escaparHtml(traducir ? traducir(f.clave) : f.clave)}</span>
              <span class="mono" style="color:var(--ink-soft);">${f.total} · ${ancho} %</span>
            </div>
            <div class="barra-mini"><div class="barra-mini-fill" style="width:${ancho}%"></div></div>
          </div>`;
      }).join('')}
    </section>`;
}

function encabezadoImpresion(titulo, periodo) {
  return `
    <div class="solo-impresion" style="margin-bottom:12px;">
      <strong style="font-size:18px;">SiGeRU — ${escaparHtml(titulo)}</strong><br>
      <span>Período: ${escaparHtml(formatearFecha(periodo.desde + ' 00:00:00', false))} al ${escaparHtml(formatearFecha(periodo.hasta + ' 00:00:00', false))}
      · Generado el ${escaparHtml(new Date().toLocaleString('es-UY'))} por ${escaparHtml(sesion.usuario.nombre)}</span>
    </div>`;
}

function pintarInformeIncidencias(r) {
  const titulos = { todas: 'Incidencias abiertas y cerradas', abiertas: 'Incidencias abiertas', cerradas: 'Incidencias cerradas' };
  const s = r.resumen;
  const filas = r.filas.length
    ? r.filas.map(i => `
        <tr>
          <td class="mono">#${i.id_incidencia}</td>
          <td class="mono" style="font-size:12px;">${escaparHtml(formatearFecha(i.fecha_apertura))}</td>
          <td>${escaparHtml(etiqueta('tipo', i.tipo))}</td>
          <td>${escaparHtml(i.contenedor_ubicacion)}<div style="font-size:12px; color:var(--ink-soft);">${escaparHtml(i.nombre_zona || '')}</div></td>
          <td>${insigniaPrioridad(i.prioridad)}</td>
          <td>${insigniaEstado(i.estado)}</td>
          <td>${escaparHtml(i.cuadrilla || '—')}</td>
          <td class="mono" style="font-size:12px;">${i.fecha_resolucion ? escaparHtml(formatearFecha(i.fecha_resolucion)) : '—'}</td>
          <td class="mono">${i.horas_resolucion !== null ? escaparHtml(i.horas_resolucion) + ' h' : '—'}</td>
        </tr>`).join('')
    : `<tr><td colspan="9">${textoVacio('No hay incidencias para esos filtros.')}</td></tr>`;

  document.getElementById('resultado-informe').innerHTML = `
    ${encabezadoImpresion(titulos[r.situacion], r.periodo)}
    <div class="grilla-kpi">
      <div class="kpi"><div class="kpi-etiqueta">Total del período</div><div class="kpi-valor">${s.total}</div></div>
      <div class="kpi"><div class="kpi-etiqueta">Abiertas</div><div class="kpi-valor" style="color:var(--clay);">${s.abiertas}</div>
        <div class="kpi-detalle">${s.sin_asignar} sin asignar · ${s.en_curso} en curso</div></div>
      <div class="kpi"><div class="kpi-etiqueta">Cerradas</div><div class="kpi-valor">${s.cerradas}</div>
        <div class="kpi-detalle">${s.porcentaje_cerradas} % del total</div></div>
      <div class="kpi"><div class="kpi-etiqueta">Tiempo promedio de resolución</div>
        <div class="kpi-valor">${s.horas_promedio_resolucion !== null ? escaparHtml(s.horas_promedio_resolucion) : '—'}<small>h</small></div></div>
    </div>
    <div class="grid-doble">
      ${tablaConteo('Por tipo', r.por_tipo, v => etiqueta('tipo', v))}
      ${tablaConteo('Por zona', r.por_zona)}
    </div>
    <section class="tarjeta">
      <h3 style="font-size:20px; margin-bottom:12px;">${escaparHtml(titulos[r.situacion])} <span class="mono" style="font-size:13px; color:var(--ink-faint);">(${r.filas.length})</span></h3>
      <div class="tabla-desplazable">
        <table class="listado">
          <thead><tr><th>N°</th><th>Apertura</th><th>Tipo</th><th>Contenedor</th><th>Prioridad</th><th>Estado</th><th>Cuadrilla</th><th>Resolución</th><th>Demora</th></tr></thead>
          <tbody>${filas}</tbody>
        </table>
      </div>
    </section>`;
}

function pintarInformeRecolecciones(r) {
  const s = r.resumen;
  const filas = r.filas.length
    ? r.filas.map(x => `
        <tr>
          <td class="mono" style="font-size:12px;">${escaparHtml(formatearFecha(x.recolectado_en))}</td>
          <td>${escaparHtml(x.contenedor_ubicacion)}</td>
          <td>${escaparHtml(x.nombre_zona || '—')}</td>
          <td class="mono">${escaparHtml(x.camion_matricula || '—')}</td>
          <td>${escaparHtml(x.cuadrilla || '—')}</td>
          <td class="mono">${x.nivel_previo !== null ? Number(x.nivel_previo) + ' %' : '—'}</td>
          <td class="mono">${x.kg_recolectados !== null ? Number(x.kg_recolectados).toLocaleString('es-UY') : '—'}</td>
        </tr>`).join('')
    : `<tr><td colspan="7">${textoVacio('No hay recolecciones en ese período.')}</td></tr>`;

  document.getElementById('resultado-informe').innerHTML = `
    ${encabezadoImpresion('Recolecciones', r.periodo)}
    <div class="grilla-kpi">
      <div class="kpi"><div class="kpi-etiqueta">Recolecciones</div><div class="kpi-valor">${s.recolecciones}</div></div>
      <div class="kpi"><div class="kpi-etiqueta">Kilos totales</div><div class="kpi-valor">${Math.round(s.kg).toLocaleString('es-UY')}<small>kg</small></div></div>
      <div class="kpi"><div class="kpi-etiqueta">Contenedores atendidos</div><div class="kpi-valor">${s.contenedores_atendidos}</div></div>
      <div class="kpi"><div class="kpi-etiqueta">Promedio por recolección</div><div class="kpi-valor">${escaparHtml(s.kg_promedio)}<small>kg</small></div></div>
    </div>
    ${tablaConteo('Recolecciones por zona', r.por_zona)}
    <section class="tarjeta">
      <h3 style="font-size:20px; margin-bottom:12px;">Detalle <span class="mono" style="font-size:13px; color:var(--ink-faint);">(${r.filas.length})</span></h3>
      <div class="tabla-desplazable">
        <table class="listado">
          <thead><tr><th>Fecha</th><th>Contenedor</th><th>Zona</th><th>Camión</th><th>Cuadrilla</th><th>Nivel previo</th><th>Kg</th></tr></thead>
          <tbody>${filas}</tbody>
        </table>
      </div>
    </section>`;
}

async function generar() {
  document.getElementById('mensaje-informe').innerHTML = '';
  document.getElementById('resultado-informe').innerHTML = `<section class="tarjeta">${textoVacio('Generando…')}</section>`;
  const { ok, datos } = await pedirJson(`${API_URL}/informes.php?${parametros()}`);
  if (!ok) {
    document.getElementById('resultado-informe').innerHTML = '';
    return mostrarMensaje('mensaje-informe', datos.mensaje || 'No se pudo generar el informe', 'error');
  }
  if (tipoInforme() === 'incidencias') pintarInformeIncidencias(datos);
  else pintarInformeRecolecciones(datos);
}

/** El CSV necesita el token, así que se descarga con fetch y se guarda como archivo. */
async function descargarCsv() {
  const params = parametros();
  params.set('formato', 'csv');
  try {
    const respuesta = await fetchAutenticado(`${API_URL}/informes.php?${params}`);
    if (!respuesta.ok) {
      const error = await respuesta.json().catch(() => ({}));
      return mostrarMensaje('mensaje-informe', error.mensaje || 'No se pudo descargar', 'error');
    }
    const archivo = await respuesta.blob();
    const enlace = document.createElement('a');
    enlace.href = URL.createObjectURL(archivo);
    enlace.download = `sigeru_${params.get('tipo')}_${params.get('desde')}_${params.get('hasta')}.csv`;
    document.body.appendChild(enlace);
    enlace.click();
    enlace.remove();
    URL.revokeObjectURL(enlace.href);
  } catch (e) {
    mostrarMensaje('mensaje-informe', 'No se pudo conectar con el servidor', 'error');
  }
}

async function cargarZonas() {
  const respuesta = await fetch(`${API_URL}/zonas.php`).catch(() => null);
  if (!respuesta || !respuesta.ok) return;
  const zonas = await respuesta.json();
  document.getElementById('i-zona').innerHTML = '<option value="">Todas</option>' +
    zonas.map(z => `<option value="${z.id_zona}">${escaparHtml(z.nombre_zona)}</option>`).join('');
}

document.getElementById('i-tipo').addEventListener('change', function () {
  document.querySelectorAll('.solo-incidencias').forEach(e => e.hidden = this.value !== 'incidencias');
  generar();
});
['i-situacion', 'i-desde', 'i-hasta', 'i-zona', 'i-tipo-incidencia'].forEach(id =>
  document.getElementById(id).addEventListener('change', generar));
document.getElementById('btn-generar').addEventListener('click', generar);
document.getElementById('btn-csv').addEventListener('click', descargarCsv);
document.getElementById('btn-imprimir').addEventListener('click', () => window.print());
document.querySelectorAll('[data-periodo]').forEach(function (boton) {
  boton.addEventListener('click', function () {
    aplicarPeriodo(boton.dataset.periodo);
    generar();
  });
});

if (sesion) {
  aplicarPeriodo('mes');
  const situacion = new URLSearchParams(window.location.search).get('situacion');
  if (situacion) document.getElementById('i-situacion').value = situacion;
  cargarZonas();
  generar();
}
