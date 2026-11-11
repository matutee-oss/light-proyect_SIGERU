// ============================================================
// notificaciones.js — listado completo de notificaciones del usuario
// ============================================================

const sesion = exigirSesion();

async function cargarNotificaciones() {
  const soloNoLeidas = document.getElementById('n-filtro').value;
  const { ok, datos } = await pedirJson(`${API_URL}/notificaciones.php?limite=100${soloNoLeidas ? '&no_leidas=1' : ''}`);
  const lista = document.getElementById('lista-notificaciones');

  if (!ok) {
    lista.innerHTML = `<div class="mensaje error">${escaparHtml(datos.mensaje)}</div>`;
    return;
  }
  if (datos.notificaciones.length === 0) {
    lista.innerHTML = textoVacio(soloNoLeidas ? 'No tenés notificaciones sin leer.' : 'Todavía no recibiste notificaciones.');
    return;
  }

  lista.innerHTML = datos.notificaciones.map(n => `
    <div class="tarjeta-item" style="align-items:flex-start;">
      ${htmlNotificacion(n, false)}
      <div class="grupo-botones" style="flex-shrink:0;">
        ${Number(n.leida) ? '' : `<button type="button" class="boton-contorno" data-leer="${n.id_notificacion}">Marcar leída</button>`}
        <button type="button" class="boton-contorno boton-peligro" data-borrar="${n.id_notificacion}" aria-label="Eliminar notificación">Eliminar</button>
      </div>
    </div>`).join('');

  lista.querySelectorAll('.notificacion').forEach(function (a) {
    a.style.flex = '1';
    a.addEventListener('click', async function (evento) {
      evento.preventDefault();
      await marcarNotificacionLeida(a.dataset.id);
      const destino = a.getAttribute('href');
      if (destino && destino !== '#') window.location.href = destino;
      else refrescar();
    });
  });
  lista.querySelectorAll('[data-leer]').forEach(function (boton) {
    boton.addEventListener('click', async function () {
      await marcarNotificacionLeida(boton.dataset.leer);
      refrescar();
    });
  });
  lista.querySelectorAll('[data-borrar]').forEach(function (boton) {
    boton.addEventListener('click', async function () {
      await pedirJson(`${API_URL}/notificaciones.php?id=${boton.dataset.borrar}`, { method: 'DELETE' });
      refrescar();
    });
  });
}

function refrescar() {
  cargarNotificaciones();
  if (typeof cargarCampana === 'function' && document.getElementById('campana-lista')) cargarCampana();
}

// sesion.js llama a esta función cuando se usa "Marcar todas" desde la campana
function alMarcarTodasLeidas() {
  cargarNotificaciones();
}

document.getElementById('n-filtro').addEventListener('change', cargarNotificaciones);
document.getElementById('btn-todas').addEventListener('click', async function () {
  await pedirJson(`${API_URL}/notificaciones.php?accion=todas`, { method: 'PUT' });
  refrescar();
});

if (sesion) cargarNotificaciones();
