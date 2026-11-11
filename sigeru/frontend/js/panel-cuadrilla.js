const sesion = exigirSesion(['cuadrilla']);
let mapaGlobal = null;
let idRutaActual = null;

function iconoNumerado(numero, atendido) {
  const color = atendido ? '#1b4b43' : '#c97f24';
  return L.divIcon({
    className: 'icono-mapa-numerado',
    html: `<div style="background:${color}; color:white; width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; font-family:'IBM Plex Mono', monospace; font-size:13px; border: 2.5px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3);">${numero}</div>`,
    iconSize: [30, 30],
    iconAnchor: [15, 15]
  });
}

function pintarMapaRuta(paradas) {
  if (mapaGlobal) mapaGlobal.remove();

  const centro = paradas.length > 0 && paradas[0].latitud
    ? [paradas[0].latitud, paradas[0].longitud]
    : [-34.9011, -56.1645];

  mapaGlobal = L.map('mapa-ruta').setView(centro, 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors', maxZoom: 18
  }).addTo(mapaGlobal);

  const puntos = [];
  paradas.forEach(function (p) {
    if (!p.latitud || !p.longitud) return;
    puntos.push([p.latitud, p.longitud]);

    L.marker([p.latitud, p.longitud], { icon: iconoNumerado(p.orden, p.atendido) })
      .addTo(mapaGlobal)
      .bindPopup(`<strong>Parada ${p.orden}</strong><br>${escaparHtml(p.ubicacion)}<br>${escaparHtml(p.tipo_residuo)}<br>Estado: <b>${escaparHtml(p.estado)}</b> · Nivel ${Number(p.nivel_llenado)} %`);
  });

  if (puntos.length > 1) {
    L.polyline(puntos, { color: '#1b4b43', weight: 3, opacity: 0.5, dashArray: '6, 8' }).addTo(mapaGlobal);
    mapaGlobal.fitBounds(puntos, { padding: [40, 40] });
  }
}

function pintarChecklist(paradas) {
  const contenedor = document.getElementById('lista-paradas');
  if (!paradas || paradas.length === 0) {
    contenedor.innerHTML = '<p class="mono" style="color:var(--ink-faint); font-size:13px;">Sin paradas cargadas.</p>';
    return;
  }

  contenedor.innerHTML = paradas.map(function (p) {
    return `
      <div class="tarjeta-item">
        <div style="display:flex; align-items:center; gap:14px; flex:1;">
          <span class="num-parada">${p.orden}</span>
          <div>
            <strong style="${p.atendido ? 'text-decoration: line-through; opacity: 0.6;' : ''}">${escaparHtml(p.ubicacion)}</strong>
            <div class="mono" style="font-size:12px; color:var(--ink-soft); margin-top:2px;">
              ${escaparHtml(p.tipo_residuo)} · ${insigniaEstado(p.estado, 'contenedor')} · ${Number(p.nivel_llenado)} %
            </div>
          </div>
        </div>
        <label class="checkbox-parada">
          <input type="checkbox" data-id="${p.id_contenedor}" ${p.atendido ? 'checked' : ''}>
          <span>${p.atendido ? 'Atendido' : 'Marcar'}</span>
        </label>
      </div>
    `;
  }).join('');

  // Al marcar una parada se registra la recolección (el contenedor queda vacío
  // y se resuelven sus desbordes pendientes).
  contenedor.querySelectorAll('input[type="checkbox"]').forEach(function (chk) {
    chk.addEventListener('change', async function () {
      let kg = null;
      if (chk.checked) {
        const respuesta = prompt('Kilos recolectados (opcional, dejá vacío si no sabés):', '');
        if (respuesta === null) { chk.checked = false; return; }
        kg = respuesta.trim() === '' ? null : Number(respuesta.replace(',', '.'));
      }
      const { ok, datos } = await pedirJson(`${API_URL}/panel_cuadrilla.php?accion=marcar_parada`, {
        method: 'POST',
        cuerpo: {
          id_ruta: idRutaActual,
          id_contenedor: parseInt(chk.dataset.id),
          atendido: chk.checked,
          kg_recolectados: kg
        }
      });
      mostrarMensaje('mensaje-ruta', datos.mensaje || 'No se pudo actualizar la parada', ok ? 'exito' : 'error');
      cargarPanel();
    });
  });
}

function pintarIncidencias(lista, contenedorId, sonMias) {
  const contenedor = document.getElementById(contenedorId);
  if (lista.length === 0) {
    contenedor.innerHTML = '<p class="mono" style="color:var(--ink-faint); font-size:13px;">Nada por aquí.</p>';
    return;
  }

  contenedor.innerHTML = lista.map(function (i) {
    return `
      <div class="tarjeta-item">
        <div>
          <a href="incidencias.html?id=${i.id_incidencia}" style="text-decoration:none;"><strong>${escaparHtml(i.contenedor_ubicacion)}</strong></a>
          <div class="mono" style="font-size:12px; color:var(--ink-soft); margin-top:2px;">
            #${i.id_incidencia} · ${escaparHtml(etiqueta('tipo', i.tipo))} · ${insigniaPrioridad(i.prioridad)} ${insigniaEstado(i.estado)}
          </div>
        </div>
        ${sonMias
          ? `<select data-id="${i.id_incidencia}" class="select-estado">
               <option value="en_curso" ${i.estado === 'en_curso' ? 'selected' : ''}>En curso</option>
               <option value="resuelta" ${i.estado === 'resuelta' ? 'selected' : ''}>Resuelta</option>
             </select>`
          : `<button data-id="${i.id_incidencia}" class="btn-tomar boton-primario" style="padding: 6px 14px; font-size:12px;">Tomar</button>`
        }
      </div>
    `;
  }).join('');

  contenedor.querySelectorAll('.btn-tomar').forEach(function (b) {
    b.addEventListener('click', async function () {
      const { ok, datos } = await pedirJson(`${API_URL}/incidencias.php?id=${b.dataset.id}&accion=tomar`, { method: 'PUT' });
      if (!ok) alert(datos.mensaje);
      cargarPanel();
    });
  });

  contenedor.querySelectorAll('.select-estado').forEach(function (s) {
    s.addEventListener('change', async function () {
      const { ok, datos } = await pedirJson(`${API_URL}/incidencias.php?id=${s.dataset.id}&accion=estado`, {
        method: 'PUT',
        cuerpo: { estado: s.value }
      });
      if (!ok) alert(datos.mensaje);
      cargarPanel();
    });
  });
}

async function cargarPanel() {
  const respuesta = await fetchAutenticado(`${API_URL}/panel_cuadrilla.php`);
  const datos = await respuesta.json();

  document.getElementById('stat-paradas').textContent = datos.total_paradas || '–';
  document.getElementById('stat-atendidas').textContent = datos.paradas_atendidas || '0';
  document.getElementById('stat-restantes').textContent = datos.restantes || '0';
  document.getElementById('stat-tiempo').textContent = datos.restantes ? `~${datos.restantes * 10}min` : '–';

  const camion = document.getElementById('info-camion');
  if (datos.ruta && datos.ruta.camion_matricula) {
    camion.innerHTML = `<strong>🚛 Matrícula: <span class="mono">${escaparHtml(datos.ruta.camion_matricula)}</span></strong>`;
  } else if (datos.ruta) {
    camion.innerHTML = `<p style="color:var(--ink-soft);">Ruta ${escaparHtml(datos.ruta.nombre)} · sin camión asignado</p>`;
  } else {
    camion.innerHTML = `
      <p style="color:var(--ink-soft); margin-bottom: 14px;">No tenés ruta asignada para hoy.</p>
      <button id="btn-generar-demo" class="boton-primario" style="padding: 10px 20px;">Generar ruta de demo</button>
    `;
    document.getElementById('btn-generar-demo').addEventListener('click', async function () {
      await fetchAutenticado(`${API_URL}/panel_cuadrilla.php?accion=generar_demo`, { method: 'POST' });
      cargarPanel();
    });
  }

  if (datos.ruta) {
    idRutaActual = datos.ruta.id_ruta;
    pintarMapaRuta(datos.ruta.paradas || []);
    pintarChecklist(datos.ruta.paradas || []);
  } else {
    document.getElementById('mapa-ruta').innerHTML = '<div style="padding:40px; text-align:center; color:var(--ink-faint);">Sin ruta cargada</div>';
    document.getElementById('lista-paradas').innerHTML = '';
  }

  const mias = (datos.incidencias || []).filter(i => i.id_cuadrilla == sesion.usuario.id && i.estado !== 'resuelta');
  const disponibles = (datos.incidencias || []).filter(i => !i.id_cuadrilla);

  pintarIncidencias(mias, 'lista-mias', true);
  pintarIncidencias(disponibles, 'lista-disponibles', false);
}

cargarPanel();
