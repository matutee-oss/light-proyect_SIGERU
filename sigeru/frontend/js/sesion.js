// ============================================================
// sesion.js — funciones comunes a todas las páginas con login
// (sesión, menú según rol, campana de notificaciones y utilidades)
// ============================================================

function obtenerSesion() {
  const token = sessionStorage.getItem('token');
  const usuarioJson = sessionStorage.getItem('usuario');
  if (!token || !usuarioJson) return null;
  try {
    return { token, usuario: JSON.parse(usuarioJson) };
  } catch (e) {
    return null;
  }
}

function rutaPanelSegunTipo(tipo) {
  const rutas = {
    administrador: 'panel-admin.html',
    cuadrilla: 'panel-cuadrilla.html',
    operario: 'panel-operario.html',
    vecino: 'panel-vecino.html'
  };
  return rutas[tipo] || 'index.html';
}

function exigirSesion(rolesPermitidos) {
  const sesion = obtenerSesion();

  if (!sesion) {
    window.location.href = 'login.html';
    return null;
  }

  if (rolesPermitidos && !rolesPermitidos.includes(sesion.usuario.tipo)) {
    alert('No tenés permiso para ver esta sección.');
    window.location.href = rutaPanelSegunTipo(sesion.usuario.tipo);
    return null;
  }

  return sesion;
}

async function cerrarSesion() {
  // Corrección: antes solo se borraba el navegador; ahora también se invalida el token
  try {
    await fetchAutenticado(`${API_URL}/logout.php`, { method: 'POST' });
  } catch (e) { /* si el servidor no responde igual cerramos localmente */ }
  sessionStorage.clear();
  window.location.href = 'index.html';
}

function fetchAutenticado(url, opciones = {}) {
  const sesion = obtenerSesion();
  const headers = Object.assign({}, opciones.headers, sesion ? { Authorization: `Bearer ${sesion.token}` } : {});
  return fetch(url, Object.assign({}, opciones, { headers })).then(function (respuesta) {
    // Token vencido o inválido: volver al login en lugar de mostrar errores raros
    if (respuesta.status === 401 && sesion && !url.includes('logout.php')) {
      sessionStorage.clear();
      window.location.href = 'login.html';
    }
    return respuesta;
  });
}

/** fetch + JSON en un paso. Devuelve { ok, estado, datos }. */
async function pedirJson(url, opciones = {}) {
  if (opciones.cuerpo !== undefined) {
    opciones = Object.assign({}, opciones, {
      headers: Object.assign({ 'Content-Type': 'application/json' }, opciones.headers),
      body: JSON.stringify(opciones.cuerpo)
    });
    delete opciones.cuerpo;
  }
  try {
    const respuesta = await fetchAutenticado(url, opciones);
    let datos = {};
    try { datos = await respuesta.json(); } catch (e) { datos = { mensaje: 'Respuesta inválida del servidor' }; }
    return { ok: respuesta.ok && datos.exito !== false, estado: respuesta.status, datos };
  } catch (error) {
    return { ok: false, estado: 0, datos: { mensaje: 'No se pudo conectar con el servidor' } };
  }
}

// ------------------------------------------------------------
// Utilidades de presentación
// ------------------------------------------------------------

/** Escapa texto antes de meterlo en innerHTML (evita XSS con datos de usuarios). */
function escaparHtml(valor) {
  if (valor === null || valor === undefined) return '';
  return String(valor)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

const ETIQUETAS = {
  tipo: { rotura: 'Rotura', desborde: 'Desborde', falta_recoleccion: 'Falta de recolección', otro: 'Otro' },
  estado: { abierta: 'Abierta', en_curso: 'En curso', resuelta: 'Resuelta' },
  prioridad: { alta: 'Alta', media: 'Media', baja: 'Baja' },
  contenedor: { funcional: 'Funcional', roto: 'Roto', desbordado: 'Desbordado' },
  rol: { administrador: 'Administrador', cuadrilla: 'Cuadrilla', operario: 'Operario', vecino: 'Vecino' }
};

function etiqueta(grupo, valor) {
  return (ETIQUETAS[grupo] && ETIQUETAS[grupo][valor]) || valor || '—';
}

/** "2026-11-06 14:30:00" → Date (Safari no entiende el espacio). */
function aFecha(texto) {
  if (!texto) return null;
  return new Date(String(texto).replace(' ', 'T'));
}

function formatearFecha(texto, conHora = true) {
  const fecha = aFecha(texto);
  if (!fecha || isNaN(fecha)) return '—';
  const opciones = conHora
    ? { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }
    : { day: '2-digit', month: '2-digit', year: 'numeric' };
  return fecha.toLocaleString('es-UY', opciones);
}

function tiempoRelativo(texto) {
  const fecha = aFecha(texto);
  if (!fecha || isNaN(fecha)) return '';
  const segundos = Math.round((Date.now() - fecha.getTime()) / 1000);
  if (segundos < 60) return 'hace un momento';
  const minutos = Math.round(segundos / 60);
  if (minutos < 60) return `hace ${minutos} min`;
  const horas = Math.round(minutos / 60);
  if (horas < 24) return `hace ${horas} h`;
  const dias = Math.round(horas / 24);
  if (dias < 30) return `hace ${dias} ${dias === 1 ? 'día' : 'días'}`;
  return formatearFecha(texto, false);
}

function fechaISO(fecha) {
  const d = fecha || new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function insigniaEstado(estado, grupo = 'estado') {
  return `<span class="etiqueta-estado estado-${escaparHtml(estado)}">${escaparHtml(etiqueta(grupo, estado))}</span>`;
}

function insigniaPrioridad(prioridad) {
  return `<span class="etiqueta-prioridad prioridad-${escaparHtml(prioridad)}">${escaparHtml(etiqueta('prioridad', prioridad))}</span>`;
}

function mostrarMensaje(idContenedor, texto, tipo = 'exito') {
  const div = document.getElementById(idContenedor);
  if (!div) return;
  div.innerHTML = `<div class="mensaje ${tipo}">${escaparHtml(texto)}</div>`;
  if (tipo === 'exito') {
    setTimeout(function () { if (div) div.innerHTML = ''; }, 5000);
  }
}

function textoVacio(texto) {
  return `<p class="texto-vacio mono">${escaparHtml(texto)}</p>`;
}

/** Color para cada estado de contenedor (mapas). */
function colorSegunEstado(estado) {
  if (estado === 'desbordado') return '#ad4d38';
  if (estado === 'roto') return '#c97f24';
  return '#1b4b43';
}

// ------------------------------------------------------------
// Menú según el rol
// ------------------------------------------------------------

const MENU_POR_ROL = {
  administrador: [
    ['panel-admin.html', 'Panel'],
    ['estadisticas.html', 'Estadísticas'],
    ['mapa.html', 'Mapa'],
    ['incidencias.html', 'Incidencias'],
    ['recolecciones.html', 'Recolección'],
    ['informes.html', 'Informes'],
    ['gestion', 'Gestión', [
      ['usuarios.html', 'Usuarios'],
      ['contenedores.html', 'Contenedores'],
      ['camiones.html', 'Camiones'],
      ['centros-acopio.html', 'Centros de acopio']
    ]]
  ],
  cuadrilla: [
    ['panel-cuadrilla.html', 'Mi ruta'],
    ['mapa.html', 'Mapa'],
    ['incidencias.html', 'Incidencias'],
    ['recolecciones.html', 'Recolección']
  ],
  operario: [
    ['panel-operario.html', 'Mi centro']
  ],
  vecino: [
    ['panel-vecino.html', 'Mi barrio'],
    ['mapa.html', 'Mapa'],
    ['incidencias.html', 'Mis reportes']
  ]
};

function pintarNavSesion() {
  const sesion = obtenerSesion();
  const enlaces = document.querySelector('.enlaces');
  if (!enlaces || !sesion) return;

  const paginaActual = window.location.pathname.split('/').pop() || 'index.html';
  const items = MENU_POR_ROL[sesion.usuario.tipo] || [];

  const html = items.map(function (item) {
    const [ruta, texto, submenu] = item;
    if (submenu) {
      const activo = submenu.some(s => s[0] === paginaActual);
      return `
        <div class="menu-desplegable">
          <button type="button" class="menu-boton ${activo ? 'activo' : ''}" aria-haspopup="true" aria-expanded="false">${texto} ▾</button>
          <div class="menu-lista">
            ${submenu.map(s => `<a href="${s[0]}" class="${s[0] === paginaActual ? 'activo' : ''}">${s[1]}</a>`).join('')}
          </div>
        </div>`;
    }
    return `<a href="${ruta}" class="${ruta === paginaActual ? 'activo' : ''}">${texto}</a>`;
  }).join('');

  enlaces.innerHTML = `
    ${html}
    <div class="campana" id="campana">
      <button type="button" class="campana-boton" id="campana-boton" aria-label="Notificaciones" aria-expanded="false">
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 0 0-5.5-6.84V3.5a1.5 1.5 0 0 0-3 0v.66A7 7 0 0 0 5 11v5l-2 2v1h18v-1Z"/></svg>
        <span class="campana-contador" id="campana-contador" hidden>0</span>
      </button>
      <div class="campana-panel" id="campana-panel" hidden>
        <div class="campana-cabecera">
          <strong>Notificaciones</strong>
          <button type="button" class="enlace-boton" id="campana-leer-todas">Marcar todas como leídas</button>
        </div>
        <div class="campana-lista" id="campana-lista">${textoVacio('Cargando…')}</div>
        <a href="notificaciones.html" class="campana-pie">Ver todas</a>
      </div>
    </div>
    <div class="menu-desplegable menu-usuario">
      <button type="button" class="menu-boton" aria-haspopup="true" aria-expanded="false">${escaparHtml(sesion.usuario.nombre.split(' ')[0])} ▾</button>
      <div class="menu-lista menu-lista-derecha">
        <span class="menu-rol mono">${escaparHtml(etiqueta('rol', sesion.usuario.tipo))}</span>
        <a href="perfil.html">Mi perfil</a>
        <a href="notificaciones.html">Notificaciones</a>
        <a href="#" id="link-cerrar-sesion">Cerrar sesión</a>
      </div>
    </div>
  `;

  enlaces.querySelectorAll('.menu-desplegable').forEach(function (menu) {
    const boton = menu.querySelector('.menu-boton');
    boton.addEventListener('click', function (evento) {
      evento.stopPropagation();
      const abierto = menu.classList.toggle('abierto');
      boton.setAttribute('aria-expanded', abierto);
      document.querySelectorAll('.menu-desplegable.abierto').forEach(m => { if (m !== menu) m.classList.remove('abierto'); });
      cerrarPanelCampana();
    });
  });
  document.addEventListener('click', function () {
    document.querySelectorAll('.menu-desplegable.abierto').forEach(m => m.classList.remove('abierto'));
  });

  document.getElementById('link-cerrar-sesion').addEventListener('click', function (evento) {
    evento.preventDefault();
    cerrarSesion();
  });

  iniciarCampana();
}

// ------------------------------------------------------------
// Campana de notificaciones + avisos emergentes (toasts)
// ------------------------------------------------------------

const INTERVALO_NOTIFICACIONES_MS = 30000;
let ultimoIdNotificacion = 0;

function iconoNivel(nivel) {
  if (nivel === 'critica') return '!';
  if (nivel === 'advertencia') return '▲';
  return 'i';
}

function htmlNotificacion(n, compacta = true) {
  return `
    <a class="notificacion nivel-${escaparHtml(n.nivel)} ${Number(n.leida) ? '' : 'no-leida'}"
       href="${n.enlace ? escaparHtml(n.enlace) : '#'}" data-id="${n.id_notificacion}">
      <span class="notificacion-icono" aria-hidden="true">${iconoNivel(n.nivel)}</span>
      <span class="notificacion-texto">
        <strong>${escaparHtml(n.titulo)}</strong>
        <span>${escaparHtml(n.mensaje)}</span>
        <span class="mono notificacion-fecha">${escaparHtml(compacta ? tiempoRelativo(n.creada_en) : formatearFecha(n.creada_en))}</span>
      </span>
    </a>`;
}

function actualizarContador(cantidad) {
  const contador = document.getElementById('campana-contador');
  if (!contador) return;
  contador.textContent = cantidad > 99 ? '99+' : cantidad;
  contador.hidden = cantidad === 0;
  document.title = document.title.replace(/^\(\d+\+?\)\s/, '');
  if (cantidad > 0) document.title = `(${cantidad}) ${document.title}`;
}

async function cargarCampana() {
  const { ok, datos } = await pedirJson(`${API_URL}/notificaciones.php?limite=8`);
  if (!ok) return;

  actualizarContador(datos.no_leidas);
  const lista = document.getElementById('campana-lista');
  lista.innerHTML = datos.notificaciones.length
    ? datos.notificaciones.map(n => htmlNotificacion(n)).join('')
    : textoVacio('No tenés notificaciones.');

  lista.querySelectorAll('.notificacion').forEach(function (a) {
    a.addEventListener('click', async function (evento) {
      evento.preventDefault();
      await marcarNotificacionLeida(a.dataset.id);
      const destino = a.getAttribute('href');
      if (destino && destino !== '#') window.location.href = destino;
      else cargarCampana();
    });
  });

  if (datos.notificaciones.length) {
    ultimoIdNotificacion = Math.max(ultimoIdNotificacion, ...datos.notificaciones.map(n => Number(n.id_notificacion)));
  }
}

async function marcarNotificacionLeida(id) {
  await pedirJson(`${API_URL}/notificaciones.php?id=${encodeURIComponent(id)}`, { method: 'PUT' });
}

async function consultarNuevas() {
  if (document.hidden) return;
  const { ok, datos } = await pedirJson(`${API_URL}/notificaciones.php?desde=${ultimoIdNotificacion}`);
  if (!ok) return;
  actualizarContador(datos.no_leidas);
  (datos.nuevas || []).forEach(function (n) {
    ultimoIdNotificacion = Math.max(ultimoIdNotificacion, Number(n.id_notificacion));
    mostrarToast(n);
  });
  if ((datos.nuevas || []).length && !document.getElementById('campana-panel').hidden) {
    cargarCampana();
  }
}

function mostrarToast(n) {
  let zona = document.getElementById('zona-toasts');
  if (!zona) {
    zona = document.createElement('div');
    zona.id = 'zona-toasts';
    zona.className = 'zona-toasts';
    zona.setAttribute('aria-live', 'polite');
    document.body.appendChild(zona);
  }
  const toast = document.createElement('div');
  toast.className = `toast nivel-${n.nivel}`;
  toast.innerHTML = `
    <span class="notificacion-icono" aria-hidden="true">${iconoNivel(n.nivel)}</span>
    <div><strong>${escaparHtml(n.titulo)}</strong><p>${escaparHtml(n.mensaje)}</p></div>
    <button type="button" aria-label="Cerrar">×</button>`;
  toast.querySelector('button').addEventListener('click', () => toast.remove());
  if (n.enlace) {
    toast.style.cursor = 'pointer';
    toast.addEventListener('click', async function (evento) {
      if (evento.target.tagName === 'BUTTON') return;
      await marcarNotificacionLeida(n.id_notificacion);
      window.location.href = n.enlace;
    });
  }
  zona.appendChild(toast);
  setTimeout(() => toast.classList.add('saliendo'), 7000);
  setTimeout(() => toast.remove(), 7600);
}

function cerrarPanelCampana() {
  const panel = document.getElementById('campana-panel');
  if (panel) {
    panel.hidden = true;
    document.getElementById('campana-boton').setAttribute('aria-expanded', 'false');
  }
}

function iniciarCampana() {
  const boton = document.getElementById('campana-boton');
  const panel = document.getElementById('campana-panel');

  boton.addEventListener('click', function (evento) {
    evento.stopPropagation();
    panel.hidden = !panel.hidden;
    boton.setAttribute('aria-expanded', String(!panel.hidden));
    if (!panel.hidden) cargarCampana();
  });
  panel.addEventListener('click', e => e.stopPropagation());
  document.addEventListener('click', cerrarPanelCampana);

  document.getElementById('campana-leer-todas').addEventListener('click', async function () {
    await pedirJson(`${API_URL}/notificaciones.php?accion=todas`, { method: 'PUT' });
    cargarCampana();
    if (typeof alMarcarTodasLeidas === 'function') alMarcarTodasLeidas();
  });

  cargarCampana();
  setInterval(consultarNuevas, INTERVALO_NOTIFICACIONES_MS);
}

document.addEventListener('DOMContentLoaded', pintarNavSesion);
