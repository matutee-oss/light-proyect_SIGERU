const selectTipo = document.getElementById('tipo_usuario');
const campoCentro = document.getElementById('campo-centro');
const campoCodigo = document.getElementById('campo-codigo');
const inputCodigo = document.getElementById('codigo_funcionario');

selectTipo.addEventListener('change', function () {
  const esRolInterno = this.value !== 'vecino';

  campoCodigo.style.display = esRolInterno ? 'block' : 'none';
  inputCodigo.required = esRolInterno;

  campoCentro.style.display = this.value === 'operario' ? 'block' : 'none';
  document.getElementById('campo-zona').style.display = this.value === 'vecino' ? '' : 'none';
});

// Zonas para que el vecino elija su barrio al registrarse
fetch(`${API_URL}/zonas.php`)
  .then(r => r.ok ? r.json() : [])
  .then(function (zonas) {
    document.getElementById('id_zona').innerHTML = '<option value="">Elegir después</option>' +
      zonas.map(z => `<option value="${z.id_zona}">${escaparHtml(z.nombre_zona)}</option>`).join('');
  })
  .catch(function () {});

document.getElementById('form-registro').addEventListener('submit', async function (evento) {
  evento.preventDefault();

  const datos = {
    nombre: document.getElementById('nombre').value,
    apellido: document.getElementById('apellido').value,
    ci: document.getElementById('ci').value,
    telefono: document.getElementById('telefono').value,
    email: document.getElementById('email').value,
    password: document.getElementById('password').value,
    tipo_usuario: selectTipo.value
  };

  if (selectTipo.value !== 'vecino') {
    datos.codigo_funcionario = inputCodigo.value;
  }

  if (selectTipo.value === 'vecino' && document.getElementById('id_zona').value) {
    datos.id_zona = document.getElementById('id_zona').value;
  }

  if (selectTipo.value === 'operario') {
    datos.id_centro = document.getElementById('id_centro').value;
  }

  const divMensaje = document.getElementById('mensaje-registro');

  try {
    const respuesta = await fetch(`${API_URL}/registro.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(datos)
    });

    const resultado = await respuesta.json();

    if (respuesta.ok && resultado.exito) {
      divMensaje.innerHTML = `<div class="mensaje exito">${escaparHtml(resultado.mensaje)}</div>`;
      document.getElementById('form-registro').reset();
      campoCodigo.style.display = 'none';
      campoCentro.style.display = 'none';
    } else {
      divMensaje.innerHTML = `<div class="mensaje error">${escaparHtml(resultado.mensaje)}</div>`;
    }
  } catch (error) {
    divMensaje.innerHTML = `<div class="mensaje error">Error de conexión con el servidor</div>`;
    console.error('Error en registro:', error);
  }
});
