// ============================================================
// mapa.js — mapa interactivo con contenedores e incidencias
// Capas que se pueden prender y apagar, filtros y acciones rápidas.
// ============================================================

const sesion = exigirSesion(['administrador', 'cuadrilla', 'vecino']);
const rol = sesion ? sesion.usuario.tipo : null;
const esPersonal = rol === 'administrador' || rol === 'cuadrilla';

const CENTRO_MONTEVIDEO = [-34.8730, -56.1650];
const COLOR_PRIORIDAD = { alta: '#ad4d38', media: '#c97f24', baja: '#5f6f69' };
const LETRA_TIPO = { desborde: 'D', rotura: 'R', falta_recoleccion: 'F', otro: '?' };

let mapa = null;
let capas = {};
let marcadoresContenedor = {};
let marcadoresIncidencia = {};
let datosMapa = null;
let primeraCarga = true;

function crearMapa() {
  mapa = L.map('mapa', { scrollWheelZoom: true }).setView(CENTRO_MONTEVIDEO, 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19
  }).addTo(mapa);

  capas = {
    contenedores: L.layerGroup().addTo(mapa),
    incidencias: L.layerGroup().addTo(mapa),
    camiones: L.layerGroup().addTo(mapa),
    centros: L.layerGroup().addTo(mapa)
  };

  Object.keys(capas).forEach(function (nombre) {
    const casilla = document.getElementById(`capa-${nombre}`);
    if (!casilla) return;
    casilla.addEventListener('change', function () {
      if (this.checked) capas[nombre].addTo(mapa);
      else mapa.removeLayer(capas[nombre]);
    });
  });
}

// ------------------------------------------------------------
// Íconos
// ------------------------------------------------------------

function iconoContenedor(c) {
  const nivel = Number(c.nivel_llenado);
  const tamano = 22 + Math.round(nivel / 100 * 12);
  return L.divIcon({
    className: '',
    html: `<div class="marcador-contenedor" style="width:${tamano}px;height:${tamano}px;background:${colorSegunEstado(c.estado)};">${nivel}</div>`,
    iconSize: [tamano, tamano],
    iconAnchor: [tamano / 2, tamano / 2],
    popupAnchor: [0, -tamano / 2]
  });
}

// La incidencia se dibuja arriba a la derecha de su contenedor (y corrida si hay
// varias) para que no tape el círculo con el nivel de llenado.
function iconoIncidencia(i, indice) {
  const x = -14 - indice * 22;
  const y = 40;
  return L.divIcon({
    className: '',
    html: `<div class="marcador-incidencia" style="background:${COLOR_PRIORIDAD[i.prioridad] || '#5f6f69'};"><span>${LETRA_TIPO[i.tipo] || '?'}</span></div>`,
    iconSize: [26, 26],
    iconAnchor: [x, y],
    popupAnchor: [-x + 13, -y]
  });
}

function iconoEmoji(emoji) {
  return L.divIcon({
    className: '',
    html: `<div style="font-size:24px; text-shadow:0 2px 4px rgba(0,0,0,0.4);">${emoji}</div>`,
    iconSize: [28, 28],
    iconAnchor: [14, 14]
  });
}

function colorNivel(nivel) {
  if (nivel >= 100) return 'var(--clay)';
  if (nivel >= 80) return 'var(--amber)';
  return 'var(--petrol)';
}

// ------------------------------------------------------------
// Popups
// ------------------------------------------------------------

function popupContenedor(c) {
  const nivel = Number(c.nivel_llenado);
  const pendientes = Number(c.incidencias_pendientes);
  let acciones = `<a class="boton-contorno" href="incidencias.html?contenedor=${c.id_contenedor}">Reportar problema</a>`;
  if (esPersonal) {
    acciones += ` <a class="boton-chico" href="recolecciones.html?contenedor=${c.id_contenedor}" style="color:white;">Registrar recolección</a>`;
  }
  return `
    <div class="popup-mapa">
      <strong>${escaparHtml(c.ubicacion)}</strong>
      <div class="meta">${escaparHtml(c.tipo_residuo)} · ${escaparHtml(c.nombre_zona || 'Sin zona')}</div>
      ${insigniaEstado(c.estado, 'contenedor')}
      <div class="nivel-barra"><div style="width:${nivel}%; background:${colorNivel(nivel)};"></div></div>
      <div class="meta">Nivel ${nivel} % · ${pendientes} incidencia(s) pendiente(s)<br>
        Última recolección: ${escaparHtml(c.ultima_recoleccion ? tiempoRelativo(c.ultima_recoleccion) : 'sin registro')}</div>
      <div class="grupo-botones">${acciones}</div>
    </div>`;
}

function popupIncidencia(i) {
  const cuadrilla = i.cuadrilla_nombre ? `${i.cuadrilla_nombre} ${i.cuadrilla_apellido}` : 'sin asignar';
  const enlace = (esPersonal || Number(i.reportado_por) === Number(sesion.usuario.id))
    ? `<div class="grupo-botones"><a class="boton-chico" href="incidencias.html?id=${i.id_incidencia}" style="color:white;">Ver seguimiento</a></div>`
    : '';
  return `
    <div class="popup-mapa">
      <strong>#${i.id_incidencia} · ${escaparHtml(etiqueta('tipo', i.tipo))}</strong>
      <div class="meta">${escaparHtml(i.contenedor_ubicacion)}<br>${escaparHtml(tiempoRelativo(i.fecha_apertura))} · cuadrilla ${escaparHtml(cuadrilla)}</div>
      <div class="grupo-botones">${insigniaEstado(i.estado)} ${insigniaPrioridad(i.prioridad)}</div>
      ${i.descripcion ? `<div class="meta" style="margin-top:8px;">${escaparHtml(i.descripcion)}</div>` : ''}
      ${enlace}
    </div>`;
}

// ------------------------------------------------------------
// Pintado
// ------------------------------------------------------------

function pintarCapas(datos) {
  Object.values(capas).forEach(capa => capa.clearLayers());
  marcadoresContenedor = {};
  marcadoresIncidencia = {};
  const puntos = [];

  datos.contenedores.forEach(function (c) {
    if (c.latitud === null || c.longitud === null) return;
    const posicion = [Number(c.latitud), Number(c.longitud)];
    puntos.push(posicion);
    marcadoresContenedor[c.id_contenedor] = L.marker(posicion, { icon: iconoContenedor(c), zIndexOffset: Number(c.nivel_llenado) })
      .bindPopup(popupContenedor(c))
      .addTo(capas.contenedores);
  });

  const porContenedor = {};
  datos.incidencias.forEach(function (i) {
    const indice = (porContenedor[i.id_contenedor] = (porContenedor[i.id_contenedor] || 0) + 1) - 1;
    const posicion = [Number(i.latitud), Number(i.longitud)];
    marcadoresIncidencia[i.id_incidencia] = L.marker(posicion, { icon: iconoIncidencia(i, indice), zIndexOffset: 1000 })
      .bindPopup(popupIncidencia(i))
      .addTo(capas.incidencias);
  });

  datos.camiones.forEach(function (cam) {
    L.marker([Number(cam.latitud), Number(cam.longitud)], { icon: iconoEmoji('🚛') })
      .bindPopup(`<div class="popup-mapa"><strong>Camión ${escaparHtml(cam.matricula)}</strong><div class="meta">${escaparHtml(cam.estado.replace('_', ' '))}</div></div>`)
      .addTo(capas.camiones);
  });

  datos.centros.forEach(function (centro) {
    L.marker([Number(centro.latitud), Number(centro.longitud)], { icon: iconoEmoji('🏭') })
      .bindPopup(`<div class="popup-mapa"><strong>${escaparHtml(centro.nombre)}</strong><div class="meta">${escaparHtml(centro.ubicacion)}<br>Capacidad ${escaparHtml(centro.capacidad)} m³</div></div>`)
      .addTo(capas.centros);
  });

  if (primeraCarga && puntos.length > 1) {
    mapa.fitBounds(puntos, { padding: [40, 40] });
  }
}

function pintarResumen(datos) {
  const c = datos.contenedores;
  const desbordados = c.filter(x => x.estado === 'desbordado').length;
  const casiLlenos = c.filter(x => Number(x.nivel_llenado) >= 80).length;
  const alta = datos.incidencias.filter(i => i.prioridad === 'alta').length;
  document.getElementById('resumen-mapa').innerHTML = `
    <span class="chip">Contenedores <strong>${c.length}</strong></span>
    <span class="chip">Desbordados <strong>${desbordados}</strong></span>
    <span class="chip">Al 80 % o más <strong>${casiLlenos}</strong></span>
    <span class="chip">Incidencias pendientes <strong>${datos.incidencias.length}</strong></span>
    <span class="chip">Prioridad alta <strong>${alta}</strong></span>`;

  const criticos = c
    .filter(x => x.estado === 'desbordado' || Number(x.nivel_llenado) >= 80 || Number(x.incidencias_pendientes) > 0)
    .sort((a, b) => Number(b.nivel_llenado) - Number(a.nivel_llenado))
    .slice(0, 8);

  document.getElementById('lista-criticos').innerHTML = criticos.length
    ? criticos.map(x => `
        <div class="tarjeta-item">
          <div style="flex:1; min-width:0;">
            <strong>${escaparHtml(x.ubicacion)}</strong>
            <div class="medidor-nivel" style="margin-top:6px;">
              <div class="nivel-barra"><div style="width:${Number(x.nivel_llenado)}%; background:${colorNivel(Number(x.nivel_llenado))};"></div></div>
              <span>${Number(x.nivel_llenado)} %</span>
            </div>
          </div>
          ${insigniaEstado(x.estado, 'contenedor')}
          <button type="button" class="boton-contorno" data-ver="${x.id_contenedor}">Ver en el mapa</button>
        </div>`).join('')
    : textoVacio('No hay contenedores críticos con estos filtros. ¡Todo en orden!');

  document.querySelectorAll('[data-ver]').forEach(function (boton) {
    boton.addEventListener('click', function () {
      enfocarContenedor(boton.dataset.ver);
      document.getElementById('mapa').scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  });
}

function enfocarContenedor(id) {
  const marcador = marcadoresContenedor[id];
  if (!marcador) return;
  mapa.setView(marcador.getLatLng(), 16);
  marcador.openPopup();
}

function enfocarIncidencia(id) {
  const marcador = marcadoresIncidencia[id];
  if (!marcador) return;
  mapa.setView(marcador.getLatLng(), 16);
  marcador.openPopup();
}

// ------------------------------------------------------------
// Carga de datos
// ------------------------------------------------------------

function parametros() {
  const params = new URLSearchParams();
  const valores = {
    id_zona: document.getElementById('m-zona').value,
    estado_contenedor: document.getElementById('m-estado').value,
    nivel_minimo: document.getElementById('m-nivel').value,
    tipo: document.getElementById('m-tipo').value,
    prioridad: document.getElementById('m-prioridad').value
  };
  Object.entries(valores).forEach(([clave, valor]) => { if (valor) params.set(clave, valor); });
  return params;
}

async function cargar() {
  const { ok, datos } = await pedirJson(`${API_URL}/mapa.php?${parametros()}`);
  if (!ok) {
    document.getElementById('resumen-mapa').innerHTML = `<div class="mensaje error">${escaparHtml(datos.mensaje)}</div>`;
    return;
  }
  datosMapa = datos;

  const selectZona = document.getElementById('m-zona');
  if (selectZona.options.length <= 1) {
    selectZona.innerHTML = '<option value="">Todas</option>' +
      datos.zonas.map(z => `<option value="${z.id_zona}">${escaparHtml(z.nombre_zona)}</option>`).join('');
  }

  const popupAbierto = mapa._popup && mapa.hasLayer(mapa._popup);
  if (!popupAbierto || primeraCarga) {
    pintarCapas(datos);
  }
  pintarResumen(datos);
  document.getElementById('actualizado').textContent = 'Actualizado ' + new Date().toLocaleTimeString('es-UY', { hour: '2-digit', minute: '2-digit' });

  if (primeraCarga) {
    const params = new URLSearchParams(window.location.search);
    if (params.get('contenedor')) enfocarContenedor(params.get('contenedor'));
    if (params.get('incidencia')) enfocarIncidencia(params.get('incidencia'));
  }
  primeraCarga = false;
}

['m-zona', 'm-estado', 'm-nivel', 'm-tipo', 'm-prioridad'].forEach(function (id) {
  document.getElementById(id).addEventListener('change', function () {
    primeraCarga = true;
    cargar();
  });
});

if (sesion) {
  if (esPersonal) document.querySelectorAll('.solo-personal').forEach(e => e.hidden = false);
  // Filtros que pueden venir en la URL (ej. mapa.html?nivel=80 desde estadísticas)
  const params = new URLSearchParams(window.location.search);
  if (params.get('nivel')) document.getElementById('m-nivel').value = params.get('nivel');
  if (params.get('estado')) document.getElementById('m-estado').value = params.get('estado');
  crearMapa();
  cargar();
  // Refresco automático cada minuto (no pisa un popup que el usuario esté leyendo)
  setInterval(cargar, 60000);
}
