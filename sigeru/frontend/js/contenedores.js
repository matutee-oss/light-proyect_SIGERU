const sesion = exigirSesion(['administrador']);

let todosLosContenedores = [];
let modoEdicionContenedor = false;

async function cargarContenedores() {
  const tabla = document.getElementById('tabla-contenedores');
  tabla.innerHTML = '<tr><td colspan="6">Cargando...</td></tr>';

  try {
    const respuesta = await fetchAutenticado(`${API_URL}/contenedores.php`);
    todosLosContenedores = await respuesta.json();
    renderizarContenedores(todosLosContenedores);
  } catch (error) {
    tabla.innerHTML = '<tr><td colspan="6">Error al cargar contenedores</td></tr>';
    console.error(error);
  }
}

function renderizarContenedores(contenedores) {
  const tabla = document.getElementById('tabla-contenedores');
  if (!contenedores.length) {
    tabla.innerHTML = '<tr><td colspan="6" style="color:var(--ink-faint);">Sin contenedores registrados.</td></tr>';
    return;
  }

  tabla.innerHTML = '';
  contenedores.forEach(function (contenedor) {
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td class="mono" style="font-size:12px;">${contenedor.id_contenedor}</td>
      <td>${contenedor.ubicacion}</td>
      <td>${contenedor.tipo_residuo}</td>
      <td><span class="etiqueta-estado estado-${contenedor.estado}">${contenedor.estado}</span></td>
      <td class="mono" style="font-size:12px;">${contenedor.id_zona || '—'}</td>
      <td>
        <div style="display:flex; gap:6px; flex-wrap:wrap;">
          <button data-id="${contenedor.id_contenedor}" class="btn-editar-contenedor" style="padding:4px 12px; font-size:12px; background:var(--petrol); color:#fff; border:none; border-radius:var(--radio); cursor:pointer;">Editar</button>
          <button data-id="${contenedor.id_contenedor}" class="btn-eliminar" style="padding:4px 12px; font-size:12px; background:none; border:1px solid var(--clay); color:var(--clay); border-radius:var(--radio); cursor:pointer;">Eliminar</button>
        </div>
      </td>
    `;
    tabla.appendChild(fila);
  });

  document.querySelectorAll('.btn-editar-contenedor').forEach(boton => {
    boton.addEventListener('click', () => activarEdicionContenedor(parseInt(boton.dataset.id)));
  });
  document.querySelectorAll('.btn-eliminar').forEach(boton => {
    boton.addEventListener('click', () => eliminarContenedor(boton.dataset.id));
  });
}

function activarEdicionContenedor(id) {
  const contenedor = todosLosContenedores.find(c => c.id_contenedor === id);
  if (!contenedor) return;

  modoEdicionContenedor = true;
  document.getElementById('titulo-form-contenedor').textContent = 'Editar contenedor';
  document.getElementById('btn-guardar-contenedor').textContent = 'Actualizar contenedor';
  document.getElementById('btn-cancelar-contenedor').style.display = 'inline-block';

  document.getElementById('contenedor-id').value = contenedor.id_contenedor;
  document.getElementById('ubicacion').value = contenedor.ubicacion;
  document.getElementById('tipo_residuo').value = contenedor.tipo_residuo;
  document.getElementById('estado').value = contenedor.estado;
  document.getElementById('id_zona').value = contenedor.id_zona || '';
  document.getElementById('latitud').value = contenedor.latitud || '';
  document.getElementById('longitud').value = contenedor.longitud || '';

  document.querySelector('section.tarjeta').scrollIntoView({ behavior: 'smooth' });
}

function cancelarEdicionContenedor() {
  modoEdicionContenedor = false;
  document.getElementById('titulo-form-contenedor').textContent = 'Nuevo contenedor';
  document.getElementById('btn-guardar-contenedor').textContent = 'Guardar contenedor';
  document.getElementById('btn-cancelar-contenedor').style.display = 'none';
  document.getElementById('contenedor-id').value = '';
  document.getElementById('form-contenedor').reset();
  document.getElementById('mensaje-contenedor').innerHTML = '';
}

document.getElementById('btn-cancelar-contenedor').addEventListener('click', cancelarEdicionContenedor);

async function eliminarContenedor(id) {
  if (!confirm('¿Eliminar este contenedor?')) return;

  try {
    const respuesta = await fetchAutenticado(`${API_URL}/contenedores.php?id=${id}`, { method: 'DELETE' });
    const resultado = await respuesta.json();
    if (respuesta.ok && resultado.exito) {
      cargarContenedores();
    } else {
      alert(resultado.mensaje);
    }
  } catch (error) {
    alert('Error de conexión');
    console.error(error);
  }
}

document.getElementById('form-contenedor').addEventListener('submit', async function (evento) {
  evento.preventDefault();

  const id = document.getElementById('contenedor-id').value;
  const idZona = document.getElementById('id_zona').value;
  const lat = document.getElementById('latitud').value;
  const lng = document.getElementById('longitud').value;

  const datos = {
    ubicacion:    document.getElementById('ubicacion').value,
    tipo_residuo: document.getElementById('tipo_residuo').value,
    estado:       document.getElementById('estado').value,
    id_zona:      idZona ? parseInt(idZona, 10) : null,
    latitud:      lat ? parseFloat(lat) : null,
    longitud:     lng ? parseFloat(lng) : null,
  };

  const divMensaje = document.getElementById('mensaje-contenedor');
  const url = id ? `${API_URL}/contenedores.php?id=${id}` : `${API_URL}/contenedores.php`;
  const metodo = id ? 'PUT' : 'POST';

  try {
    const respuesta = await fetchAutenticado(url, {
      method: metodo,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(datos)
    });

    const resultado = await respuesta.json();

    if (respuesta.ok && resultado.exito) {
      divMensaje.innerHTML = `<div class="mensaje exito">${resultado.mensaje}</div>`;
      cancelarEdicionContenedor();
      cargarContenedores();
    } else {
      divMensaje.innerHTML = `<div class="mensaje error">${resultado.mensaje}</div>`;
    }
  } catch (error) {
    divMensaje.innerHTML = `<div class="mensaje error">Error de conexión con el servidor</div>`;
    console.error(error);
  }
});

cargarContenedores();
