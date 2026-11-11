// ============================================================
// recolecciones.js — API de recolección (administradores y cuadrillas)
// ============================================================

const sesion = exigirSesion(['administrador', 'cuadrilla']);
const esAdmin = sesion && sesion.usuario.tipo === 'administrador';

let contenedores = [];

function colorNivel(nivel) {
  if (nivel >= 100) return 'var(--clay)';
  if (nivel >= 80) return 'var(--amber)';
  return 'var(--petrol)';
}

// ------------------------------------------------------------
// Contenedores y niveles
// ------------------------------------------------------------

async function cargarContenedores() {
  const { ok, datos } = await pedirJson(`${API_URL}/recolecciones.php?accion=contenedores`);
  if (!ok) return;
  contenedores = datos;

  const select = document.getElementById('r-contenedor');
  const seleccionado = select.value || new URLSearchParams(window.location.search).get('contenedor') || '';
  select.innerHTML = contenedores
    .map(c => `<option value="${c.id_contenedor}">${escaparHtml(c.ubicacion)} — ${Number(c.nivel_llenado)} %${c.estado === 'desbordado' ? ' (desbordado)' : ''}</option>`)
    .join('');
  if (seleccionado) select.value = seleccionado;

  const zonas = {};
  contenedores.forEach(c => { if (c.id_zona) zonas[c.id_zona] = c.nombre_zona; });
  const selectZona = document.getElementById('h-zona');
  if (selectZona.options.length <= 1) {
    selectZona.innerHTML = '<option value="">Todas</option>' +
      Object.entries(zonas).sort((a, b) => a[0] - b[0]).map(([id, nombre]) => `<option value="${id}">${escaparHtml(nombre)}</option>`).join('');
  }

  pintarNiveles();
}

function pintarNiveles() {
  const tabla = document.getElementById('tabla-niveles');
  tabla.innerHTML = contenedores.map(function (c) {
    const nivel = Number(c.nivel_llenado);
    return `
      <tr>
        <td><strong>${escaparHtml(c.ubicacion)}</strong><div class="mono" style="font-size:11px; color:var(--ink-faint);">${escaparHtml(c.tipo_residuo)}</div></td>
        <td>${escaparHtml(c.nombre_zona || '—')}</td>
        <td>${insigniaEstado(c.estado, 'contenedor')}</td>
        <td>
          <div class="medidor-nivel">
            <div class="nivel-barra"><div style="width:${nivel}%; background:${colorNivel(nivel)};"></div></div>
            <span>${nivel} %</span>
          </div>
        </td>
        <td style="white-space:nowrap;">
          <input type="number" class="input-recurso" min="0" max="100" value="${nivel}" data-nivel="${c.id_contenedor}" aria-label="Nuevo nivel para ${escaparHtml(c.ubicacion)}">
          <button type="button" class="boton-contorno" data-guardar-nivel="${c.id_contenedor}" style="color:var(--petrol); border-color:var(--petrol);">Guardar</button>
        </td>
        <td><button type="button" class="boton-chico" data-recolectar="${c.id_contenedor}" style="background:var(--petrol); color:white; border-color:var(--petrol);">Recolectar</button></td>
      </tr>`;
  }).join('');

  tabla.querySelectorAll('[data-guardar-nivel]').forEach(function (boton) {
    boton.addEventListener('click', async function () {
      const id = boton.dataset.guardarNivel;
      const valor = tabla.querySelector(`[data-nivel="${id}"]`).value;
      const { ok, datos } = await pedirJson(`${API_URL}/recolecciones.php?accion=nivel&id_contenedor=${id}`, {
        method: 'PUT',
        cuerpo: { nivel: Number(valor) }
      });
      mostrarMensaje('mensaje-niveles', datos.mensaje || 'No se pudo actualizar', ok ? 'exito' : 'error');
      if (ok) cargarContenedores();
    });
  });

  tabla.querySelectorAll('[data-recolectar]').forEach(function (boton) {
    boton.addEventListener('click', function () {
      document.getElementById('r-contenedor').value = boton.dataset.recolectar;
      document.getElementById('form-recoleccion').scrollIntoView({ behavior: 'smooth', block: 'center' });
      document.getElementById('r-kg').focus();
    });
  });
}

async function cargarCamiones() {
  const { ok, datos } = await pedirJson(`${API_URL}/recolecciones.php?accion=camiones`);
  if (!ok) return;
  document.getElementById('r-camion').innerHTML = '<option value="">Sin indicar</option>' +
    datos.map(c => `<option value="${c.id_camion}">${escaparHtml(c.matricula)} (${escaparHtml(c.estado.replace('_', ' '))})</option>`).join('');
}

// ------------------------------------------------------------
// Resumen e historial
// ------------------------------------------------------------

async function cargarResumen() {
  const { ok, datos } = await pedirJson(`${API_URL}/recolecciones.php?accion=resumen`);
  if (!ok) return;

  const mes = datos.mes_actual;
  const criticos = contenedores.filter(c => Number(c.nivel_llenado) >= 80 || c.estado === 'desbordado').length;
  document.getElementById('kpis-recoleccion').innerHTML = `
    <div class="kpi"><div class="kpi-etiqueta">Recolecciones del mes</div><div class="kpi-valor">${mes.recolecciones}</div></div>
    <div class="kpi"><div class="kpi-etiqueta">Kilos del mes</div><div class="kpi-valor">${Math.round(mes.kg).toLocaleString('es-UY')}<small>kg</small></div></div>
    <div class="kpi"><div class="kpi-etiqueta">Contenedores atendidos</div><div class="kpi-valor">${mes.contenedores_atendidos}</div></div>
    <div class="kpi"><div class="kpi-etiqueta">Para recolectar ya</div><div class="kpi-valor" style="color:var(--clay);">${criticos}</div><div class="kpi-detalle">80 % o más / desbordados</div></div>`;

  const maximo = Math.max(1, ...datos.por_dia.map(d => d.kg));
  document.getElementById('grafico-kg').innerHTML = datos.por_dia.map(function (d) {
    const altura = Math.round(d.kg / maximo * 100);
    const fecha = aFecha(d.dia + ' 12:00:00');
    const etiquetaDia = fecha.toLocaleDateString('es-UY', { day: '2-digit', month: '2-digit' });
    return `
      <div class="grafico-columna" title="${escaparHtml(etiquetaDia)}: ${d.kg} kg en ${d.recolecciones} recolección(es)">
        <div class="grafico-valor">${d.kg ? Math.round(d.kg) : ''}</div>
        <div class="grafico-barra" style="height:${altura}%"></div>
        <div class="grafico-etiqueta">${escaparHtml(etiquetaDia)}</div>
      </div>`;
  }).join('');
}

async function cargarHistorial() {
  const params = new URLSearchParams();
  const desde = document.getElementById('h-desde').value;
  const hasta = document.getElementById('h-hasta').value;
  const zona = document.getElementById('h-zona').value;
  if (desde) params.set('desde', desde);
  if (hasta) params.set('hasta', hasta);
  if (zona) params.set('id_zona', zona);

  const { ok, datos } = await pedirJson(`${API_URL}/recolecciones.php?${params}`);
  const tabla = document.getElementById('tabla-historial');
  if (!ok) {
    tabla.innerHTML = `<tr><td colspan="8">${escaparHtml(datos.mensaje)}</td></tr>`;
    return;
  }
  if (datos.length === 0) {
    tabla.innerHTML = `<tr><td colspan="8">${textoVacio('No hay recolecciones en ese período.')}</td></tr>`;
    return;
  }

  tabla.innerHTML = datos.map(r => `
    <tr>
      <td class="mono" style="font-size:12px;">${escaparHtml(formatearFecha(r.recolectado_en))}</td>
      <td>${escaparHtml(r.contenedor_ubicacion)}${r.observaciones ? `<div style="font-size:12px; color:var(--ink-soft);">${escaparHtml(r.observaciones)}</div>` : ''}</td>
      <td>${escaparHtml(r.nombre_zona || '—')}</td>
      <td class="mono" style="font-size:12px;">${escaparHtml(r.camion_matricula || '—')}</td>
      <td>${escaparHtml(r.cuadrilla_nombre ? `${r.cuadrilla_nombre} ${r.cuadrilla_apellido}` : '—')}</td>
      <td class="mono">${r.nivel_previo !== null ? Number(r.nivel_previo) + ' %' : '—'}</td>
      <td class="mono">${r.kg_recolectados !== null ? Number(r.kg_recolectados).toLocaleString('es-UY') : '—'}</td>
      <td>${esAdmin ? `<button type="button" data-borrar="${r.id_recoleccion}">Eliminar</button>` : ''}</td>
    </tr>`).join('');

  tabla.querySelectorAll('[data-borrar]').forEach(function (boton) {
    boton.addEventListener('click', async function () {
      if (!confirm('¿Eliminar este registro de recolección?')) return;
      const { ok: borrado, datos: res } = await pedirJson(`${API_URL}/recolecciones.php?id=${boton.dataset.borrar}`, { method: 'DELETE' });
      if (!borrado) return alert(res.mensaje);
      cargarHistorial();
      cargarResumen();
    });
  });
}

// ------------------------------------------------------------
// Registro
// ------------------------------------------------------------

document.getElementById('form-recoleccion').addEventListener('submit', async function (evento) {
  evento.preventDefault();
  const boton = this.querySelector('button[type="submit"]');
  const datos = {
    id_contenedor: Number(document.getElementById('r-contenedor').value),
    kg_recolectados: document.getElementById('r-kg').value,
    id_camion: document.getElementById('r-camion').value || null,
    observaciones: document.getElementById('r-observaciones').value
  };

  boton.disabled = true;
  const { ok, datos: resultado } = await pedirJson(`${API_URL}/recolecciones.php`, { method: 'POST', cuerpo: datos });
  boton.disabled = false;

  mostrarMensaje('mensaje-recoleccion', resultado.mensaje || 'No se pudo registrar', ok ? 'exito' : 'error');
  if (ok) {
    document.getElementById('r-kg').value = '';
    document.getElementById('r-observaciones').value = '';
    await cargarContenedores();
    cargarResumen();
    cargarHistorial();
  }
});

['h-desde', 'h-hasta', 'h-zona'].forEach(id => document.getElementById(id).addEventListener('change', cargarHistorial));

async function iniciar() {
  if (!sesion) return;
  if (!esAdmin) document.getElementById('titulo-historial').textContent = 'Mis recolecciones';
  const hoy = new Date();
  document.getElementById('h-desde').value = fechaISO(new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate() - 13));
  document.getElementById('h-hasta').value = fechaISO(hoy);
  await Promise.all([cargarContenedores(), cargarCamiones()]);
  cargarResumen();
  cargarHistorial();
}

iniciar();
