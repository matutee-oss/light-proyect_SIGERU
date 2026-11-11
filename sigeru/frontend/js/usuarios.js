const sesion = exigirSesion(['administrador']);

let todosLosUsuarios = [];
let modoEdicion = false;

// Mostrar/ocultar campo centro según tipo
document.getElementById('u-tipo').addEventListener('change', function () {
  const campoCentro = document.getElementById('campo-centro-usuario');
  campoCentro.style.display = this.value === 'operario' ? 'block' : 'none';
});

// Filtros
document.getElementById('filtro-rol').addEventListener('change', aplicarFiltros);
document.getElementById('filtro-estado').addEventListener('change', aplicarFiltros);

function aplicarFiltros() {
  const rol = document.getElementById('filtro-rol').value;
  const estado = document.getElementById('filtro-estado').value;

  const filtrados = todosLosUsuarios.filter(u => {
    return (!rol || u.tipo_usuario === rol) && (!estado || u.estado === estado);
  });

  renderizarTabla(filtrados);
}

function renderizarTabla(usuarios) {
  const tabla = document.getElementById('tabla-usuarios');
  const sinUsuarios = document.getElementById('sin-usuarios');

  if (!usuarios.length) {
    tabla.innerHTML = '';
    sinUsuarios.style.display = 'block';
    return;
  }

  sinUsuarios.style.display = 'none';
  tabla.innerHTML = usuarios.map(function (u) {
    return `
      <tr>
        <td class="mono" style="font-size:12px;">${u.id_usuario}</td>
        <td><strong>${escaparHtml(u.nombre)} ${escaparHtml(u.apellido)}</strong></td>
        <td>${escaparHtml(u.email)}</td>
        <td class="mono" style="font-size:12px;">${escaparHtml(u.ci)}</td>
        <td><span class="mono" style="font-size:11px; background:var(--surface-raised); padding:2px 8px; border-radius:4px;">${escaparHtml(u.tipo_usuario)}</span></td>
        <td><span class="etiqueta-estado estado-${escaparHtml(u.estado)}">${escaparHtml(u.estado)}</span></td>
        <td>
          <div style="display:flex; gap:6px; flex-wrap:wrap;">
            <button data-id="${u.id_usuario}" class="btn-editar-usuario" style="padding:4px 12px; font-size:12px; background:var(--petrol); color:#fff; border:none; border-radius:var(--radio); cursor:pointer;">Editar</button>
            <button data-id="${u.id_usuario}" class="btn-eliminar-usuario" style="padding:4px 12px; font-size:12px; background:none; border:1px solid var(--clay); color:var(--clay); border-radius:var(--radio); cursor:pointer;">Eliminar</button>
          </div>
        </td>
      </tr>
    `;
  }).join('');

  document.querySelectorAll('.btn-editar-usuario').forEach(b => {
    b.addEventListener('click', () => activarEdicion(parseInt(b.dataset.id)));
  });
  document.querySelectorAll('.btn-eliminar-usuario').forEach(b => {
    b.addEventListener('click', () => eliminarUsuario(parseInt(b.dataset.id)));
  });
}

function activarEdicion(id) {
  const usuario = todosLosUsuarios.find(u => u.id_usuario === id);
  if (!usuario) return;

  modoEdicion = true;
  document.getElementById('titulo-form-usuario').textContent = 'Editar usuario';
  document.getElementById('btn-guardar-usuario').textContent = 'Actualizar usuario';
  document.getElementById('btn-cancelar-usuario').style.display = 'inline-block';
  document.getElementById('label-password-hint').style.display = 'inline';

  document.getElementById('usuario-id').value = usuario.id_usuario;
  document.getElementById('u-nombre').value = usuario.nombre;
  document.getElementById('u-apellido').value = usuario.apellido;
  document.getElementById('u-ci').value = usuario.ci;
  document.getElementById('u-telefono').value = usuario.telefono || '';
  document.getElementById('u-email').value = usuario.email;
  document.getElementById('u-password').value = '';
  document.getElementById('u-password').required = false;
  document.getElementById('u-tipo').value = usuario.tipo_usuario;
  document.getElementById('u-estado').value = usuario.estado;

  document.getElementById('campo-centro-usuario').style.display =
    usuario.tipo_usuario === 'operario' ? 'block' : 'none';
  document.getElementById('u-id-centro').value = usuario.id_centro || '';

  document.querySelector('section.tarjeta').scrollIntoView({ behavior: 'smooth' });
}

function cancelarEdicion() {
  modoEdicion = false;
  document.getElementById('titulo-form-usuario').textContent = 'Nuevo usuario';
  document.getElementById('btn-guardar-usuario').textContent = 'Guardar usuario';
  document.getElementById('btn-cancelar-usuario').style.display = 'none';
  document.getElementById('usuario-id').value = '';
  document.getElementById('u-password').required = true;
  document.getElementById('form-usuario').reset();
  document.getElementById('campo-centro-usuario').style.display = 'none';
  document.getElementById('mensaje-usuario').innerHTML = '';
}

document.getElementById('btn-cancelar-usuario').addEventListener('click', cancelarEdicion);

document.getElementById('form-usuario').addEventListener('submit', async function (evento) {
  evento.preventDefault();

  const id = document.getElementById('usuario-id').value;
  const datos = {
    nombre:       document.getElementById('u-nombre').value,
    apellido:     document.getElementById('u-apellido').value,
    ci:           document.getElementById('u-ci').value,
    telefono:     document.getElementById('u-telefono').value,
    email:        document.getElementById('u-email').value,
    tipo_usuario: document.getElementById('u-tipo').value,
    estado:       document.getElementById('u-estado').value,
  };

  const pass = document.getElementById('u-password').value;
  if (pass) datos.password = pass;
  if (datos.tipo_usuario === 'operario') {
    datos.id_centro = document.getElementById('u-id-centro').value;
  }

  const divMensaje = document.getElementById('mensaje-usuario');
  const url = id
    ? `${API_URL}/usuarios.php?id=${id}`
    : `${API_URL}/usuarios.php`;
  const metodo = id ? 'PUT' : 'POST';

  if (!id) datos.password = pass; // nuevo usuario requiere contraseña

  try {
    const respuesta = await fetchAutenticado(url, {
      method: metodo,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(datos)
    });

    const resultado = await respuesta.json();

    if (respuesta.ok && resultado.exito) {
      divMensaje.innerHTML = `<div class="mensaje exito">${escaparHtml(resultado.mensaje)}</div>`;
      cancelarEdicion();
      cargarUsuarios();
    } else {
      divMensaje.innerHTML = `<div class="mensaje error">${escaparHtml(resultado.mensaje)}</div>`;
    }
  } catch (error) {
    divMensaje.innerHTML = `<div class="mensaje error">Error de conexión con el servidor</div>`;
    console.error(error);
  }
});

async function eliminarUsuario(id) {
  const usuario = todosLosUsuarios.find(u => u.id_usuario === id);
  if (!usuario) return;

  if (!confirm(`¿Eliminar al usuario ${usuario.nombre} ${usuario.apellido}? Esta acción no se puede deshacer.`)) return;

  try {
    const respuesta = await fetchAutenticado(`${API_URL}/usuarios.php?id=${id}`, { method: 'DELETE' });
    const resultado = await respuesta.json();

    if (respuesta.ok && resultado.exito) {
      cargarUsuarios();
    } else {
      alert(resultado.mensaje);
    }
  } catch (error) {
    alert('Error de conexión con el servidor');
    console.error(error);
  }
}

async function cargarUsuarios() {
  const tabla = document.getElementById('tabla-usuarios');
  tabla.innerHTML = '<tr><td colspan="7">Cargando...</td></tr>';

  try {
    const respuesta = await fetchAutenticado(`${API_URL}/usuarios.php`);
    todosLosUsuarios = await respuesta.json();
    aplicarFiltros();
  } catch (error) {
    tabla.innerHTML = '<tr><td colspan="7">Error al cargar usuarios</td></tr>';
    console.error(error);
  }
}

cargarUsuarios();
