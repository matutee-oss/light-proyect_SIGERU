function obtenerSesion() {
  const token = sessionStorage.getItem('token');
  const usuarioJson = sessionStorage.getItem('usuario');
  if (!token || !usuarioJson) return null;
  return { token, usuario: JSON.parse(usuarioJson) };
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

function cerrarSesion() {
  sessionStorage.clear();
  window.location.href = 'index.html';
}

function fetchAutenticado(url, opciones = {}) {
  const sesion = obtenerSesion();
  const headers = Object.assign({}, opciones.headers, sesion ? { Authorization: `Bearer ${sesion.token}` } : {});
  return fetch(url, Object.assign({}, opciones, { headers }));
}

function pintarNavSesion() {
  const sesion = obtenerSesion();
  const enlaces = document.querySelector('.enlaces');
  if (!enlaces || !sesion) return;

  enlaces.innerHTML = `
    <a href="${rutaPanelSegunTipo(sesion.usuario.tipo)}">Mi panel</a>
    <a href="perfil.html">Mi perfil</a>
    <a href="#" id="link-cerrar-sesion">Cerrar sesión</a>
  `;

  document.getElementById('link-cerrar-sesion').addEventListener('click', function (evento) {
    evento.preventDefault();
    cerrarSesion();
  });
}

document.addEventListener('DOMContentLoaded', pintarNavSesion);
