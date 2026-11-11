const sesion = exigirSesion(['vecino']);
let mapaGlobal = null;

function pintarMotivacional(cantidad) {
  document.getElementById('numero-resueltos').textContent = cantidad;

  const texto = document.getElementById('motivacional-texto');
  if (cantidad === 0) {
    texto.textContent = 'Todavía no tenés reportes resueltos. Cuando reportes un problema y una cuadrilla lo atienda, aparecerá acá.';
  } else if (cantidad === 1) {
    texto.textContent = 'Tu primer reporte hizo la diferencia. Cada aviso ayuda a mantener el orden urbano.';
  } else {
    texto.innerHTML = `Gracias a vos, se resolvieron <strong>${cantidad}</strong> problemas en la ciudad. Seguí reportando lo que veas.`;
  }
}

function pintarSelectZonas(zonas, seleccionada) {
  const opciones = zonas.map(z =>
    `<option value="${z.id_zona}" ${z.id_zona == seleccionada ? 'selected' : ''}>${escaparHtml(z.nombre_zona)}</option>`
  ).join('');
  document.getElementById('select-zona').innerHTML = opciones;
}

function pintarAvisos(avisos) {
  const contenedor = document.getElementById('lista-avisos');
  if (!avisos || avisos.length === 0) {
    contenedor.innerHTML = '<p class="mono" style="color:var(--ink-faint); font-size:13px;">No hay avisos vigentes.</p>';
    return;
  }
  contenedor.innerHTML = avisos.map(function (a) {
    const zona = a.nombre_zona ? a.nombre_zona : 'Toda la ciudad';
    return `
      <div class="tarjeta-item" style="flex-direction:column; align-items:flex-start;">
        <strong>${escaparHtml(a.titulo)}</strong>
        <p style="margin: 4px 0; color: var(--ink-soft); font-size: 14px;">${escaparHtml(a.mensaje)}</p>
        <span class="mono" style="font-size:11px; color:var(--ink-faint);">${escaparHtml(zona)} · ${escaparHtml(formatearFecha(a.publicado_en))}</span>
      </div>
    `;
  }).join('');
}

function pintarMapa(contenedores) {
  if (mapaGlobal) mapaGlobal.remove();

  const centro = contenedores.length > 0 && contenedores[0].latitud
    ? [contenedores[0].latitud, contenedores[0].longitud]
    : [-34.9011, -56.1645];

  mapaGlobal = L.map('mapa-vecino').setView(centro, 14);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors', maxZoom: 18
  }).addTo(mapaGlobal);

  const puntos = [];
  contenedores.forEach(function (c) {
    if (!c.latitud || !c.longitud) return;
    puntos.push([c.latitud, c.longitud]);

    const color = colorSegunEstado(c.estado);
    L.circleMarker([c.latitud, c.longitud], {
      radius: 10, color: color, fillColor: color, fillOpacity: 0.85, weight: 2
    })
      .addTo(mapaGlobal)
      .bindPopup(`
        <strong>${escaparHtml(c.ubicacion)}</strong><br>
        ${escaparHtml(c.tipo_residuo)}<br>
        Estado: <b>${escaparHtml(c.estado)}</b> · Nivel ${Number(c.nivel_llenado)} %<br>
        <button onclick="seleccionarContenedorParaReportar(${c.id_contenedor})" style="margin-top:8px; padding: 6px 12px; background: var(--petrol, #1b4b43); color: white; border: none; border-radius: 3px; cursor: pointer;">Reportar este contenedor</button>
      `);
  });

  if (puntos.length > 1) {
    mapaGlobal.fitBounds(puntos, { padding: [40, 40] });
  }
}

function seleccionarContenedorParaReportar(id) {
  document.getElementById('id_contenedor').value = id;
  document.getElementById('form-reporte').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

window.seleccionarContenedorParaReportar = seleccionarContenedorParaReportar;

function pintarSelectContenedores(contenedores) {
  document.getElementById('id_contenedor').innerHTML = contenedores
    .map(c => `<option value="${c.id_contenedor}">${escaparHtml(c.ubicacion)}</option>`)
    .join('');
}

function pintarReportes(reportes) {
  const contenedor = document.getElementById('lista-reportes');
  if (!reportes || reportes.length === 0) {
    contenedor.innerHTML = '<p class="mono" style="color:var(--ink-faint); font-size:13px;">Todavía no reportaste nada.</p>';
    return;
  }
  contenedor.innerHTML = reportes.map(function (r) {
    return `
      <a class="tarjeta-item" href="incidencias.html?id=${r.id_incidencia}" style="text-decoration:none; color:inherit;">
        <div>
          <strong>${escaparHtml(r.contenedor_ubicacion)}</strong>
          <div class="mono" style="font-size:12px; color:var(--ink-soft); margin-top:2px;">
            #${r.id_incidencia} · ${escaparHtml(etiqueta('tipo', r.tipo))} · ${escaparHtml(formatearFecha(r.fecha_apertura, false))} · ver seguimiento →
          </div>
        </div>
        ${insigniaEstado(r.estado)}
      </a>
    `;
  }).join('');
}

async function cargarPanel() {
  const respuesta = await fetchAutenticado(`${API_URL}/panel_vecino.php`);
  const datos = await respuesta.json();

  pintarMotivacional(datos.reportes_resueltos || 0);
  pintarSelectZonas(datos.zonas_disponibles, datos.id_zona);
  pintarAvisos(datos.avisos);
  pintarMapa(datos.contenedores_zona);
  pintarSelectContenedores(datos.contenedores_zona);
  pintarReportes(datos.reportes_propios);

  document.getElementById('tarjeta-zona').style.display = datos.id_zona ? 'none' : 'block';
}

document.getElementById('form-zona').addEventListener('submit', async function (evento) {
  evento.preventDefault();
  const idZona = document.getElementById('select-zona').value;

  await fetchAutenticado(`${API_URL}/panel_vecino.php?accion=elegir_zona`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id_zona: parseInt(idZona) })
  });

  cargarPanel();
});

document.getElementById('form-reporte').addEventListener('submit', async function (evento) {
  evento.preventDefault();

  const datos = {
    id_contenedor: document.getElementById('id_contenedor').value,
    tipo: document.getElementById('tipo').value,
    descripcion: document.getElementById('descripcion').value.trim()
  };

  const divMensaje = document.getElementById('mensaje-reporte');

  const respuesta = await fetchAutenticado(`${API_URL}/incidencias.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(datos)
  });

  const resultado = await respuesta.json();

  if (respuesta.ok && resultado.exito) {
    divMensaje.innerHTML = `<div class="mensaje exito">${escaparHtml(resultado.mensaje)}</div>`;
    document.getElementById('descripcion').value = '';
    cargarPanel();
  } else {
    divMensaje.innerHTML = `<div class="mensaje error">${escaparHtml(resultado.mensaje)}</div>`;
  }
});

cargarPanel();
