const sesion = exigirSesion(['administrador']);

function colorSegunEstado(estado) {
  if (estado === 'desbordado') return '#ad4d38';
  if (estado === 'roto') return '#c97f24';
  return '#1b4b43';
}

function iconoEmojiCamion(estado) {
  return L.divIcon({
    className: 'icono-mapa',
    html: `<div style="font-size:22px; text-shadow: 0 2px 4px rgba(0,0,0,0.4);">🚛</div>`,
    iconSize: [28, 28],
    iconAnchor: [14, 14]
  });
}

function iconoCentroAcopio() {
  return L.divIcon({
    className: 'icono-mapa',
    html: `<div style="font-size:22px; text-shadow: 0 2px 4px rgba(0,0,0,0.4);">🏭</div>`,
    iconSize: [28, 28],
    iconAnchor: [14, 14]
  });
}

let mapaGlobal = null;

function pintarMapa(datos) {
  if (mapaGlobal) mapaGlobal.remove();

  mapaGlobal = L.map('mapa-contenedores').setView([-34.9011, -56.1645], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors', maxZoom: 18
  }).addTo(mapaGlobal);

  (datos.contenedores || []).forEach(function (c) {
    if (!c.latitud || !c.longitud) return;
    const color = colorSegunEstado(c.estado);
    L.circleMarker([c.latitud, c.longitud], {
      radius: 9, color: color, fillColor: color, fillOpacity: 0.85, weight: 2
    })
      .addTo(mapaGlobal)
      .bindPopup(`<strong>${c.ubicacion}</strong><br>${c.tipo_residuo}<br>Estado: <b>${c.estado}</b>`);
  });

  (datos.camiones_mapa || []).forEach(function (cam) {
    L.marker([cam.latitud, cam.longitud], { icon: iconoEmojiCamion(cam.estado) })
      .addTo(mapaGlobal)
      .bindPopup(`<strong>🚛 Camión ${cam.matricula}</strong><br>Estado: <b>${cam.estado}</b>`);
  });

  (datos.centros || []).forEach(function (centro) {

  });
}

function pintarProgresoSemanal(progreso) {
  document.getElementById('barra-semanal').style.width = progreso.porcentaje + '%';
  document.getElementById('texto-semanal').innerHTML =
    `<strong>${progreso.resueltas}</strong> de <strong>${progreso.total}</strong> incidencias resueltas · <span class="mono">${progreso.porcentaje}%</span>`;
}

function pintarRankingZonas(ranking) {
  const contenedor = document.getElementById('ranking-zonas');
  if (!ranking || ranking.length === 0) {
    contenedor.innerHTML = '<p class="mono" style="color:var(--ink-faint); font-size:13px;">Sin incidencias en los últimos 30 días.</p>';
    return;
  }

  const max = Math.max(...ranking.map(r => parseInt(r.total_abiertas)));
  contenedor.innerHTML = ranking.map(function (r, i) {
    const ancho = Math.round((r.total_abiertas / max) * 100);
    return `
      <div class="fila-ranking">
        <span class="pos-ranking">${i + 1}</span>
        <div style="flex:1;">
          <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
            <span>${r.nombre_zona}</span>
            <span class="mono" style="color:var(--ink-soft);">${r.total_abiertas}</span>
          </div>
          <div class="barra-mini"><div class="barra-mini-fill" style="width:${ancho}%"></div></div>
        </div>
      </div>
    `;
  }).join('');
}

function pintarGraficoTipos(conteo) {
  const contenedor = document.getElementById('grafico-tipos');
  if (!conteo || conteo.length === 0) {
    contenedor.innerHTML = '<p class="mono" style="color:var(--ink-faint); font-size:13px;">Sin datos.</p>';
    return;
  }

  const etiquetas = { rotura: 'Roturas', desborde: 'Desbordes', falta_recoleccion: 'Falta recolección', otro: 'Otros' };
  const colores = { rotura: 'var(--amber)', desborde: 'var(--clay)', falta_recoleccion: 'var(--petrol)', otro: 'var(--ink-soft)' };
  const max = Math.max(...conteo.map(c => parseInt(c.total)));

  contenedor.innerHTML = conteo.map(function (c) {
    const ancho = Math.round((c.total / max) * 100);
    return `
      <div style="margin-bottom:14px;">
        <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
          <span>${etiquetas[c.tipo] || c.tipo}</span>
          <span class="mono" style="color:var(--ink-soft);">${c.total}</span>
        </div>
        <div class="barra-mini"><div class="barra-mini-fill" style="width:${ancho}%; background:${colores[c.tipo] || 'var(--petrol)'};"></div></div>
      </div>
    `;
  }).join('');
}

function pintarFeed(feed) {
  const contenedor = document.getElementById('feed-actividad');
  if (!feed || feed.length === 0) {
    contenedor.innerHTML = '<p class="mono" style="color:var(--ink-faint); font-size:13px;">Sin actividad reciente.</p>';
    return;
  }

  contenedor.innerHTML = feed.map(function (i) {
    let texto = '';
    let color = 'var(--petrol)';
    if (i.estado === 'resuelta') {
      texto = `<strong>Resuelta</strong> · ${i.contenedor_ubicacion}`;
      color = 'var(--petrol)';
      if (i.cuadrilla_nombre) texto += ` · por ${i.cuadrilla_nombre} ${i.cuadrilla_apellido}`;
    } else if (i.id_cuadrilla) {
      texto = `<strong>En curso</strong> · ${i.contenedor_ubicacion}`;
      color = 'var(--amber)';
    } else {
      texto = `<strong>Reportada</strong> · ${i.contenedor_ubicacion}`;
      color = 'var(--clay)';
      if (i.vecino_nombre) texto += ` · por ${i.vecino_nombre} ${i.vecino_apellido}`;
    }
    const fecha = i.fecha_resolucion || i.fecha_apertura;
    return `
      <div class="fila-feed">
        <div class="feed-punto" style="background:${color}"></div>
        <div style="flex:1;">
          <div>${texto}</div>
          <div class="mono" style="font-size:11px; color:var(--ink-faint); margin-top:2px;">${i.tipo} · ${fecha}</div>
        </div>
      </div>
    `;
  }).join('');
}

function pintarPendientes(pendientes) {
  const contenedor = document.getElementById('lista-pendientes');
  if (!pendientes || pendientes.length === 0) {
    contenedor.innerHTML = '<p class="mono" style="color:var(--ink-faint); font-size:13px;">No hay usuarios pendientes.</p>';
    return;
  }

  contenedor.innerHTML = pendientes.map(function (u) {
    return `
      <div class="tarjeta-item">
        <div>
          <strong>${u.nombre} ${u.apellido}</strong>
          <div class="mono" style="font-size:12px; color:var(--ink-soft); margin-top:2px;">
            ${u.email} · <span class="etiqueta-estado estado-activo mono">${u.tipo_usuario}</span>
          </div>
        </div>
        <div style="display:flex; gap:8px;">
          <button data-id="${u.id_usuario}" class="btn-aprobar boton-primario" style="padding: 6px 14px; font-size:12px;">Aprobar</button>
          <button data-id="${u.id_usuario}" class="btn-rechazar" style="padding: 6px 14px; font-size:12px; border: 1px solid var(--clay); background: none; color: var(--clay); border-radius: var(--radio); cursor: pointer;">Rechazar</button>
        </div>
      </div>
    `;
  }).join('');

  contenedor.querySelectorAll('.btn-aprobar').forEach(function (b) {
    b.addEventListener('click', () => accionUsuario('aprobar_usuario', b.dataset.id));
  });
  contenedor.querySelectorAll('.btn-rechazar').forEach(function (b) {
    b.addEventListener('click', () => accionUsuario('rechazar_usuario', b.dataset.id));
  });
}

async function accionUsuario(accion, idUsuario) {
  await fetchAutenticado(`${API_URL}/panel_admin.php?accion=${accion}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id_usuario: parseInt(idUsuario) })
  });
  cargarPanel();
}

function pintarUsuarios(usuarios) {
  const tabla = document.getElementById('tabla-usuarios');
  tabla.innerHTML = usuarios.map(function (u) {
    return `
      <tr>
        <td>${u.nombre} ${u.apellido}</td>
        <td>${u.email}</td>
        <td><span class="mono" style="font-size:12px;">${u.tipo_usuario}</span></td>
        <td><span class="etiqueta-estado estado-${u.estado}">${u.estado}</span></td>
      </tr>
    `;
  }).join('');
}

function pintarAvisos(avisos) {
  const contenedor = document.getElementById('lista-avisos');
  if (!avisos || avisos.length === 0) {
    contenedor.innerHTML = '<p class="mono" style="color:var(--ink-faint); font-size:13px;">Sin avisos.</p>';
    return;
  }
  contenedor.innerHTML = avisos.map(function (a) {
    const zona = a.nombre_zona ? `${a.nombre_zona}` : 'Toda la ciudad';
    return `
      <div class="tarjeta-item" style="flex-direction:column; align-items:flex-start;">
        <strong>${a.titulo}</strong>
        <p style="margin: 4px 0; color: var(--ink-soft); font-size: 14px;">${a.mensaje}</p>
        <span class="mono" style="font-size:11px; color:var(--ink-faint);">${zona} · ${a.publicado_en}</span>
      </div>
    `;
  }).join('');
}

function pintarSelectZonas(zonas) {
  document.getElementById('aviso-zona').innerHTML =
    '<option value="">Toda la ciudad</option>' +
    zonas.map(z => `<option value="${z.id_zona}">${z.nombre_zona}</option>`).join('');
}

async function cargarPanel() {
  const respuesta = await fetchAutenticado(`${API_URL}/panel_admin.php`);
  const datos = await respuesta.json();

  if (!respuesta.ok) {
    document.querySelector('main').innerHTML = `<div class="mensaje error">${datos.mensaje}</div>`;
    return;
  }

  document.getElementById('stat-zonas').textContent = datos.estadisticas.zonas;
  document.getElementById('stat-contenedores').textContent = datos.estadisticas.contenedores;
  document.getElementById('stat-camiones').textContent = datos.estadisticas.camiones;
  document.getElementById('stat-usuarios').textContent = datos.estadisticas.usuarios;
  document.getElementById('stat-incidencias').textContent = datos.estadisticas.incidencias_abiertas;

  pintarProgresoSemanal(datos.progreso_semanal);
  pintarMapa(datos);
  pintarRankingZonas(datos.ranking_zonas);
  pintarGraficoTipos(datos.conteo_tipos);
  pintarFeed(datos.feed_actividad);
  pintarPendientes(datos.pendientes);
  pintarUsuarios(datos.usuarios);
  pintarAvisos(datos.avisos);
  pintarSelectZonas(datos.zonas);
}

document.getElementById('form-aviso').addEventListener('submit', async function (evento) {
  evento.preventDefault();

  const datos = {
    titulo: document.getElementById('aviso-titulo').value,
    mensaje: document.getElementById('aviso-mensaje').value,
    id_zona: document.getElementById('aviso-zona').value || null
  };

  const respuesta = await fetchAutenticado(`${API_URL}/panel_admin.php?accion=publicar_aviso`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(datos)
  });

  const resultado = await respuesta.json();
  const divMensaje = document.getElementById('mensaje-aviso');

  if (respuesta.ok && resultado.exito) {
    divMensaje.innerHTML = `<div class="mensaje exito">${resultado.mensaje}</div>`;
    document.getElementById('form-aviso').reset();
    cargarPanel();
  } else {
    divMensaje.innerHTML = `<div class="mensaje error">${resultado.mensaje}</div>`;
  }
});

cargarPanel();
