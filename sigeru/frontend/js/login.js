document.getElementById('form-login').addEventListener('submit', async function (evento) {
  evento.preventDefault();

  const email = document.getElementById('email').value;
  const password = document.getElementById('password').value;
  const divMensaje = document.getElementById('mensaje-login');

  try {
    const respuesta = await fetch(`${API_URL}/login.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password })
    });

    const datos = await respuesta.json();

    if (respuesta.ok && datos.exito) {

      sessionStorage.setItem('token', datos.token);
      sessionStorage.setItem('usuario', JSON.stringify(datos.usuario));

      const rutasPorTipo = {
        administrador: 'panel-admin.html',
        cuadrilla: 'panel-cuadrilla.html',
        operario: 'panel-operario.html',
        vecino: 'panel-vecino.html'
      };

      divMensaje.innerHTML = `<div class="mensaje exito">Bienvenido, ${datos.usuario.nombre}</div>`;
      setTimeout(() => window.location.href = rutasPorTipo[datos.usuario.tipo] || 'index.html', 700);
    } else {
      divMensaje.innerHTML = `<div class="mensaje error">${datos.mensaje}</div>`;
    }
  } catch (error) {
    divMensaje.innerHTML = `<div class="mensaje error">Error de conexión con el servidor</div>`;
    console.error('Error en login:', error);
  }
});
