// ============================================================
// incidencias.js — registro y seguimiento de incidencias
// Administrador: ve todas, asigna cuadrillas, cambia estado y prioridad.
// Cuadrilla: toma incidencias disponibles y las resuelve.
// Vecino: reporta y sigue el estado de sus propios reportes.
// ============================================================

const sesion = exigirSesion(['administrador', 'cuadrilla', 'vecino']);
const rol = sesion ? sesion.usuario.tipo : null;
const esAdmin = rol === 'administrador';
const esCuadrilla = rol === 'cuadrilla';

let incidencias = [];
let cuadrillas = [];
let idAbierta = null;

// ------------------------------------------------------------
// Configuración inicial según el rol
// ------------------------------------------------------------

function configurarSegunRol() {
  if (rol === 'vecino') {
    document.getElementById('titulo-pagina').textContent = 'Mis reportes';
    document.getElementById('subtitulo-pagina').textContent =
      'Seguí el estado de cada problema que reportaste. Te avisamos por notificación cuando una cuadrilla lo toma y cuando queda resuelto.';
    document.getElementById('campo-prioridad').hidden = true;
  }
  if (esCuadrilla) {
    document.getElementById('f-vista-campo').hidden = false;
  }
  if (esAdmin) {
    document.querySelectorAll('.solo-admin').forEach(e => e.hidden = false);
  }
}

async function cargarContenedores() {
  // mapa.php devuelve los contenedores a cualquier rol con acceso a esta página
  const { ok, datos } = await pedirJson(`${API_URL}/mapa.php`);
  if (!ok) return;

  const select = document.getElementById('n-contenedor');
  select.innerHTML = datos.contenedores
    .slice()
    .sort((a, b) => a.ubicacion.localeCompare(b.ubicacion))
    .map(c => `<option value="${c.id_contenedor}">${escaparHtml(c.ubicacion)} · ${escaparHtml(c.nombre_zona || 'sin zona')} (${escaparHtml(etiqueta('contenedor', c.estado))})</option>`)
    .join('');

  if (esAdmin) {
    document.getElementById('f-zona').innerHTML = '<option value="">Todas</option>' +
      datos.zonas.map(z => `<option value="${z.id_zona}">${escaparHtml(z.nombre_zona)}</option>`).join('');
  }

  const params = new URLSearchParams(window.location.search);
  if (params.get('contenedor')) {
    select.value = params.get('contenedor');
    abrirFormularioNuevo();
  }
}

async function cargarCuadrillas() {
  if (!esAdmin) return;
  const { ok, datos } = await pedirJson(`${API_URL}/incidencias.php?accion=cuadrillas`);
  if (ok) cuadrillas = datos;
}

// ------------------------------------------------------------
// Listado
// ------------------------------------------------------------

function parametrosFiltro() {
  const params = new URLSearchParams();
  const valores = {
    estado: document.getElementById('f-estado').value,
    tipo: document.getElementById('f-tipo').value,
    prioridad: document.getElementById('f-prioridad').value,
    texto: document.getElementById('f-texto').value.trim()
  };
  if (esAdmin) {
    valores.id_zona = document.getElementById('f-zona').value;
    valores.desde = document.getElementById('f-desde').value;
    valores.hasta = document.getElementById('f-hasta').value;
  }
  Object.entries(valores).forEach(([clave, valor]) => { if (valor) params.set(clave, valor); });
  return params;
}

async function cargarIncidencias() {
  const { ok, datos } = await pedirJson(`${API_URL}/incidencias.php?${parametrosFiltro()}`);
  const contenedor = document.getElementById('lista-incidencias');
  if (!ok) {
    contenedor.innerHTML = `<div class="mensaje error">${escaparHtml(datos.mensaje)}</div>`;
    return;
  }
  incidencias = datos;
  pintarLista();
}

function filtrarLocal(lista) {
  const texto = document.getElementById('f-texto').value.trim().toLowerCase();
  const vista = esCuadrilla ? document.getElementById('f-vista').value : 'todas';

  return lista.filter(function (i) {
    if (texto && !(`${i.contenedor_ubicacion} ${i.descripcion || ''}`.toLowerCase().includes(texto))) return false;
    if (vista === 'mias' && Number(i.id_cuadrilla) !== Number(sesion.usuario.id)) return false;
    if (vista === 'disponibles' && i.id_cuadrilla) return false;
    return true;
  });
}

function pintarLista() {
  const lista = filtrarLocal(incidencias);
  const contenedor = document.getElementById('lista-incidencias');

  const abiertas = lista.filter(i => i.estado === 'abierta').length;
  const enCurso = lista.filter(i => i.estado === 'en_curso').length;
  const resueltas = lista.filter(i => i.estado === 'resuelta').length;
  document.getElementById('resumen-lista').innerHTML = `
    <span class="chip">Total <strong>${lista.length}</strong></span>
    <span class="chip">Abiertas <strong>${abiertas}</strong></span>
    <span class="chip">En curso <strong>${enCurso}</strong></span>
    <span class="chip">Resueltas <strong>${resueltas}</strong></span>`;

  if (lista.length === 0) {
    contenedor.innerHTML = `<section class="tarjeta">${textoVacio(
      rol === 'vecino' ? 'Todavía no reportaste nada. Usá «Reportar incidencia» cuando veas un problema.' : 'No hay incidencias con esos filtros.'
    )}</section>`;
    return;
  }

  contenedor.innerHTML = lista.map(function (i) {
    const cuadrilla = i.cuadrilla_nombre ? `Cuadrilla: ${i.cuadrilla_nombre} ${i.cuadrilla_apellido}` : 'Sin cuadrilla asignada';
    return `
      <button type="button" class="fila-incidencia" data-id="${i.id_incidencia}">
        <span class="numero-incidencia">#${i.id_incidencia}</span>
        <span>
          <span class="titulo-incidencia">${escaparHtml(etiqueta('tipo', i.tipo))} · ${escaparHtml(i.contenedor_ubicacion)}</span>
          <span class="meta" style="display:block;">
            ${escaparHtml(i.nombre_zona || 'Sin zona')} · ${escaparHtml(tiempoRelativo(i.fecha_apertura))} · ${escaparHtml(cuadrilla)}
          </span>
        </span>
        <span class="insignias">${insigniaPrioridad(i.prioridad)} ${insigniaEstado(i.estado)}</span>
      </button>`;
  }).join('');

  contenedor.querySelectorAll('.fila-incidencia').forEach(function (fila) {
    fila.addEventListener('click', () => abrirDetalle(Number(fila.dataset.id)));
  });
}

// ------------------------------------------------------------
// Detalle + seguimiento
// ------------------------------------------------------------

function textoSeguimiento(s) {
  switch (s.accion) {
    case 'creada': return 'Reportó la incidencia';
    case 'asignada': return 'Asignación de cuadrilla';
    case 'estado': return `Cambió el estado: ${etiqueta('estado', s.estado_anterior)} → ${etiqueta('estado', s.estado_nuevo)}`;
    default: return 'Comentario';
  }
}

function htmlSeguimiento(seguimiento) {
  if (!seguimiento || seguimiento.length === 0) return textoVacio('Sin movimientos.');
  return `<ol class="linea-tiempo">${seguimiento.map(s => `
    <li class="accion-${escaparHtml(s.accion)}">
      <div class="quien">${escaparHtml(s.nombre ? `${s.nombre} ${s.apellido}` : 'Sistema')}
        <span class="mono" style="font-size:11px; color:var(--ink-faint); font-weight:400;">${escaparHtml(s.tipo_usuario ? etiqueta('rol', s.tipo_usuario) : '')}</span>
      </div>
      <div class="cuando">${escaparHtml(formatearFecha(s.creado_en))} · ${escaparHtml(textoSeguimiento(s))}</div>
      ${s.comentario ? `<div class="que">${escaparHtml(s.comentario)}</div>` : ''}
    </li>`).join('')}</ol>`;
}

function htmlAcciones(i) {
  const resuelta = i.estado === 'resuelta';
  const esMia = Number(i.id_cuadrilla) === Number(sesion.usuario.id);
  let html = '';

  if (esCuadrilla && !resuelta && !i.id_cuadrilla) {
    html += `
      <div class="panel-acciones">
        <h4>Disponible</h4>
        <div class="fila-control">
          <span style="flex:1; color:var(--ink-soft); font-size:14px;">Nadie tomó esta incidencia todavía.</span>
          <button type="button" class="boton-chico" data-accion="tomar">Tomar incidencia</button>
        </div>
      </div>`;
  }

  if (esAdmin || (esCuadrilla && esMia)) {
    const opciones = ['abierta', 'en_curso', 'resuelta']
      .filter(e => e !== i.estado && !(e === 'en_curso' && !i.id_cuadrilla) && !(esCuadrilla && e === 'abierta'))
      .map(e => `<option value="${e}">${etiqueta('estado', e)}</option>`).join('');
    if (opciones) {
      html += `
        <div class="panel-acciones">
          <h4>Cambiar estado</h4>
          <div class="fila-control">
            <select class="control" id="d-estado">${opciones}</select>
            <input type="text" class="control" id="d-estado-comentario" maxlength="500" placeholder="Comentario (opcional)">
            <button type="button" class="boton-chico" data-accion="estado">Guardar</button>
          </div>
        </div>`;
    }
  }

  if (esAdmin && !resuelta) {
    const opcionesCuadrilla = cuadrillas
      .map(c => `<option value="${c.id_usuario}" ${Number(c.id_usuario) === Number(i.id_cuadrilla) ? 'selected' : ''}>${escaparHtml(c.nombre + ' ' + c.apellido)}</option>`)
      .join('');
    html += `
      <div class="panel-acciones">
        <h4>Gestión</h4>
        <div class="fila-control">
          <select class="control" id="d-cuadrilla"><option value="">Elegir cuadrilla…</option>${opcionesCuadrilla}</select>
          <button type="button" class="boton-contorno" data-accion="asignar">Asignar</button>
        </div>
        <div class="fila-control">
          <select class="control" id="d-prioridad">
            ${['alta', 'media', 'baja'].map(p => `<option value="${p}" ${p === i.prioridad ? 'selected' : ''}>Prioridad ${etiqueta('prioridad', p).toLowerCase()}</option>`).join('')}
          </select>
          <button type="button" class="boton-contorno" data-accion="prioridad">Cambiar prioridad</button>
        </div>
      </div>`;
  }

  html += `
    <div class="panel-acciones">
      <h4>Agregar comentario</h4>
      <div class="fila-control">
        <input type="text" class="control" id="d-comentario" maxlength="500" placeholder="Ej.: se coordinó con el camión de la tarde">
        <button type="button" class="boton-contorno" data-accion="comentar">Comentar</button>
      </div>
    </div>`;

  if (esAdmin) {
    html += `<div class="grupo-botones" style="justify-content:flex-end;"><button type="button" class="boton-contorno boton-peligro" data-accion="eliminar">Eliminar incidencia</button></div>`;
  }

  return html;
}

async function abrirDetalle(id) {
  idAbierta = id;
  const modal = document.getElementById('modal-incidencia');
  const cuerpo = document.getElementById('modal-cuerpo');
  cuerpo.innerHTML = textoVacio('Cargando…');
  modal.hidden = false;
  document.body.style.overflow = 'hidden';

  const { ok, datos: i } = await pedirJson(`${API_URL}/incidencias.php?id=${id}`);
  if (!ok) {
    cuerpo.innerHTML = `<div class="mensaje error">${escaparHtml(i.mensaje || 'Incidencia no encontrada')}</div>`;
    return;
  }

  const reporto = i.reporta_nombre ? `${i.reporta_nombre} ${i.reporta_apellido}` : '—';
  const cuadrilla = i.cuadrilla_nombre ? `${i.cuadrilla_nombre} ${i.cuadrilla_apellido}` : 'Sin asignar';

  cuerpo.innerHTML = `
    <div class="eyebrow">Incidencia #${i.id_incidencia}</div>
    <h2 id="modal-titulo" style="font-size:28px;">${escaparHtml(etiqueta('tipo', i.tipo))} · ${escaparHtml(i.contenedor_ubicacion)}</h2>
    <div class="grupo-botones" style="margin-top:10px;">${insigniaEstado(i.estado)} ${insigniaPrioridad(i.prioridad)}</div>
    ${i.descripcion ? `<p style="margin:16px 0 0; color:var(--ink-soft);">${escaparHtml(i.descripcion)}</p>` : ''}
    <dl class="detalle-grilla">
      <div><dt>Zona</dt><dd>${escaparHtml(i.nombre_zona || '—')}</dd></div>
      <div><dt>Contenedor</dt><dd>${insigniaEstado(i.contenedor_estado, 'contenedor')}</dd></div>
      <div><dt>Reportó</dt><dd>${escaparHtml(reporto)}</dd></div>
      <div><dt>Cuadrilla</dt><dd>${escaparHtml(cuadrilla)}</dd></div>
      <div><dt>Apertura</dt><dd>${escaparHtml(formatearFecha(i.fecha_apertura))}</dd></div>
      <div><dt>Resolución</dt><dd>${escaparHtml(i.fecha_resolucion ? formatearFecha(i.fecha_resolucion) : 'Pendiente')}</dd></div>
    </dl>
    ${i.latitud ? `<p style="margin:0 0 6px;"><a href="mapa.html?incidencia=${i.id_incidencia}">Ver en el mapa →</a></p>` : ''}
    <div id="mensaje-detalle"></div>
    ${htmlAcciones(i)}
    <h3 style="font-size:20px; margin-top:22px;">Seguimiento</h3>
    ${htmlSeguimiento(i.seguimiento)}
  `;

  cuerpo.querySelectorAll('[data-accion]').forEach(function (boton) {
    boton.addEventListener('click', () => ejecutarAccion(boton.dataset.accion, i, boton));
  });
}

async function ejecutarAccion(accion, i, boton) {
  const base = `${API_URL}/incidencias.php?id=${i.id_incidencia}`;
  let peticion;

  switch (accion) {
    case 'tomar':
      peticion = pedirJson(`${base}&accion=tomar`, { method: 'PUT' });
      break;
    case 'estado':
      peticion = pedirJson(`${base}&accion=estado`, {
        method: 'PUT',
        cuerpo: { estado: document.getElementById('d-estado').value, comentario: document.getElementById('d-estado-comentario').value }
      });
      break;
    case 'asignar': {
      const idCuadrilla = document.getElementById('d-cuadrilla').value;
      if (!idCuadrilla) return mostrarMensaje('mensaje-detalle', 'Elegí una cuadrilla.', 'error');
      peticion = pedirJson(`${base}&accion=asignar`, { method: 'PUT', cuerpo: { id_cuadrilla: Number(idCuadrilla) } });
      break;
    }
    case 'prioridad':
      peticion = pedirJson(`${base}&accion=prioridad`, { method: 'PUT', cuerpo: { prioridad: document.getElementById('d-prioridad').value } });
      break;
    case 'comentar': {
      const comentario = document.getElementById('d-comentario').value.trim();
      if (!comentario) return mostrarMensaje('mensaje-detalle', 'Escribí un comentario.', 'error');
      peticion = pedirJson(`${base}&accion=comentar`, { method: 'POST', cuerpo: { comentario } });
      break;
    }
    case 'eliminar':
      if (!confirm(`¿Eliminar la incidencia #${i.id_incidencia}? También se borra su historial.`)) return;
      peticion = pedirJson(base, { method: 'DELETE' });
      break;
    default:
      return;
  }

  boton.disabled = true;
  const { ok, datos } = await peticion;
  boton.disabled = false;

  if (!ok) {
    mostrarMensaje('mensaje-detalle', datos.mensaje || 'No se pudo completar la acción', 'error');
    return;
  }

  await cargarIncidencias();
  if (accion === 'eliminar') {
    cerrarDetalle();
  } else {
    await abrirDetalle(i.id_incidencia);
    mostrarMensaje('mensaje-detalle', datos.mensaje, 'exito');
  }
}

function cerrarDetalle() {
  document.getElementById('modal-incidencia').hidden = true;
  document.body.style.overflow = '';
  idAbierta = null;
  // Quita ?id= de la URL para que al recargar no vuelva a abrirse
  if (window.location.search.includes('id=')) {
    history.replaceState(null, '', window.location.pathname);
  }
}

// ------------------------------------------------------------
// Nuevo reporte
// ------------------------------------------------------------

function abrirFormularioNuevo() {
  document.getElementById('tarjeta-nueva').hidden = false;
  document.getElementById('tarjeta-nueva').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

document.getElementById('btn-nueva').addEventListener('click', abrirFormularioNuevo);
document.getElementById('btn-cancelar-nueva').addEventListener('click', function () {
  document.getElementById('form-nueva').reset();
  document.getElementById('tarjeta-nueva').hidden = true;
});

document.getElementById('form-nueva').addEventListener('submit', async function (evento) {
  evento.preventDefault();
  const datos = {
    id_contenedor: Number(document.getElementById('n-contenedor').value),
    tipo: document.getElementById('n-tipo').value,
    descripcion: document.getElementById('n-descripcion').value.trim()
  };
  const prioridad = document.getElementById('n-prioridad').value;
  if (prioridad && rol !== 'vecino') datos.prioridad = prioridad;

  if (datos.tipo === 'otro' && !datos.descripcion) {
    return mostrarMensaje('mensaje-nueva', 'Contanos qué pasa en la descripción.', 'error');
  }

  const { ok, datos: resultado } = await pedirJson(`${API_URL}/incidencias.php`, { method: 'POST', cuerpo: datos });
  if (!ok) {
    return mostrarMensaje('mensaje-nueva', resultado.mensaje || 'No se pudo enviar el reporte', 'error');
  }

  mostrarMensaje('mensaje-nueva', resultado.mensaje, 'exito');
  this.reset();
  await cargarIncidencias();
  await cargarContenedores();
});

// ------------------------------------------------------------
// Eventos
// ------------------------------------------------------------

let temporizadorBusqueda = null;
document.getElementById('f-texto').addEventListener('input', function () {
  clearTimeout(temporizadorBusqueda);
  temporizadorBusqueda = setTimeout(esAdmin ? cargarIncidencias : pintarLista, 300);
});
['f-estado', 'f-tipo', 'f-prioridad', 'f-zona', 'f-desde', 'f-hasta'].forEach(function (id) {
  document.getElementById(id).addEventListener('change', cargarIncidencias);
});
document.getElementById('f-vista').addEventListener('change', pintarLista);

document.getElementById('modal-cerrar').addEventListener('click', cerrarDetalle);
document.getElementById('modal-incidencia').addEventListener('click', function (evento) {
  if (evento.target === this) cerrarDetalle();
});
document.addEventListener('keydown', function (evento) {
  if (evento.key === 'Escape' && idAbierta !== null) cerrarDetalle();
});

async function iniciar() {
  if (!sesion) return;
  configurarSegunRol();
  await Promise.all([cargarContenedores(), cargarCuadrillas()]);
  await cargarIncidencias();

  const idUrl = new URLSearchParams(window.location.search).get('id');
  if (idUrl) abrirDetalle(Number(idUrl));
}

iniciar();
