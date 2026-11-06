const sesion = exigirSesion();

const IMAGEN_POR_DEFECTO =
  'data:image/svg+xml;utf8,' + encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">' +
    '<rect width="100" height="100" fill="#e3ece9"/>' +
    '<circle cx="50" cy="38" r="18" fill="#1b4b43"/>' +
    '<path d="M15 92c5-22 24-32 35-32s30 10 35 32z" fill="#1b4b43"/>' +
    '</svg>'
  );

async function cargarPerfil() {
  const respuesta = await fetchAutenticado(`${API_URL}/perfil.php`);
  const datos = await respuesta.json();

  if (!respuesta.ok) {
    window.location.href = 'login.html';
    return;
  }

  document.getElementById('nombre-completo').textContent = `${datos.nombre} ${datos.apellido}`;
  document.getElementById('email-usuario').textContent = datos.email;

  const chip = document.getElementById('chip-tipo');
  chip.textContent = datos.tipo_usuario;
  chip.className = 'etiqueta-estado estado-activo mono';

  document.getElementById('nombre').value = datos.nombre;
  document.getElementById('apellido').value = datos.apellido;
  document.getElementById('telefono').value = datos.telefono || '';

  document.getElementById('foto-perfil').src = datos.foto
    ? `${API_URL}/${datos.foto}`
    : IMAGEN_POR_DEFECTO;
}

document.getElementById('form-perfil').addEventListener('submit', async function (evento) {
  evento.preventDefault();

  const datos = {
    nombre: document.getElementById('nombre').value,
    apellido: document.getElementById('apellido').value,
    telefono: document.getElementById('telefono').value
  };

  const divMensaje = document.getElementById('mensaje-perfil');

  const respuesta = await fetchAutenticado(`${API_URL}/perfil.php`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(datos)
  });

  const resultado = await respuesta.json();

  if (respuesta.ok && resultado.exito) {
    divMensaje.innerHTML = `<div class="mensaje exito">${escaparHtml(resultado.mensaje)}</div>`;
    cargarPerfil();
  } else {
    divMensaje.innerHTML = `<div class="mensaje error">${resultado.mensaje || 'No se pudo actualizar'}</div>`;
  }
});

document.getElementById('input-foto').addEventListener('change', async function () {
  const archivo = this.files[0];
  if (!archivo) return;

  const formData = new FormData();
  formData.append('foto', archivo);

  const divMensaje = document.getElementById('mensaje-foto');
  divMensaje.innerHTML = '<div class="mensaje">Subiendo...</div>';

  try {
    const respuesta = await fetchAutenticado(`${API_URL}/perfil_foto.php`, {
      method: 'POST',
      body: formData
    });

    const resultado = await respuesta.json();

    if (respuesta.ok && resultado.exito) {
      document.getElementById('foto-perfil').src = `${API_URL}/${resultado.foto}?t=${Date.now()}`;
      divMensaje.innerHTML = '<div class="mensaje exito">Foto actualizada</div>';
    } else {
      divMensaje.innerHTML = `<div class="mensaje error">${escaparHtml(resultado.mensaje)}</div>`;
    }
  } catch (error) {
    divMensaje.innerHTML = '<div class="mensaje error">Error de conexión con el servidor</div>';
    console.error(error);
  }
});

cargarPerfil();
