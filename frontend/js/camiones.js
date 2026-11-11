const sesion = exigirSesion(['administrador']);

let todosCamiones = [];

async function cargarCamiones() {
  const tabla = document.getElementById('tabla-camiones');
  tabla.innerHTML = '<tr><td colspan="5">Cargando...</td></tr>';

  try {
    const respuesta = await fetchAutenticado(`${API_URL}/camiones.php`);
    todosCamiones = await respuesta.json();
    renderizarCamiones(todosCamiones);
  } catch (error) {
    tabla.innerHTML = '<tr><td colspan="5">Error al cargar camiones</td></tr>';
    console.error(error);
  }
}

function renderizarCamiones(camiones) {
  const tabla = document.getElementById('tabla-camiones');
  if (!camiones.length) {
    tabla.innerHTML = '<tr><td colspan="5" style="color:var(--ink-faint);">Sin camiones registrados.</td></tr>';
    return;
  }

  tabla.innerHTML = '';
  camiones.forEach(function (camion) {
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td class="mono" style="font-size:12px;">${camion.id_camion}</td>
      <td><strong>${camion.matricula}</strong></td>
      <td><span class="etiqueta-estado estado-${camion.estado}">${camion.estado}</span></td>
      <td class="mono" style="font-size:12px;">${camion.capacidad_kg ? Number(camion.capacidad_kg).toLocaleString() : '6,000'} kg</td>
      <td>
        <div style="display:flex; gap:6px; flex-wrap:wrap;">
          <button data-id="${camion.id_camion}" class="btn-editar-camion" style="padding:4px 12px; font-size:12px; background:var(--petrol); color:#fff; border:none; border-radius:var(--radio); cursor:pointer;">Editar</button>
          <button data-id="${camion.id_camion}" class="btn-eliminar-camion" style="padding:4px 12px; font-size:12px; background:none; border:1px solid var(--clay); color:var(--clay); border-radius:var(--radio); cursor:pointer;">Eliminar</button>
        </div>
      </td>
    `;
    tabla.appendChild(fila);
  });

  document.querySelectorAll('.btn-editar-camion').forEach(boton => {
    boton.addEventListener('click', () => activarEdicion(parseInt(boton.dataset.id)));
  });
  document.querySelectorAll('.btn-eliminar-camion').forEach(boton => {
    boton.addEventListener('click', () => eliminarCamion(boton.dataset.id));
  });
}

function activarEdicion(id) {
  const camion = todosCamiones.find(c => c.id_camion === id);
  if (!camion) return;

  document.getElementById('titulo-form-camion').textContent = 'Editar camión';
  document.getElementById('btn-guardar-camion').textContent = 'Actualizar camión';
  document.getElementById('btn-cancelar-camion').style.display = 'inline-block';

  document.getElementById('camion-id').value = camion.id_camion;
  document.getElementById('matricula').value = camion.matricula;
  document.getElementById('estado').value = camion.estado;
  document.getElementById('capacidad_kg').value = camion.capacidad_kg || 6000;
  document.getElementById('latitud').value = camion.latitud || '';
  document.getElementById('longitud').value = camion.longitud || '';

  document.querySelector('section.tarjeta').scrollIntoView({ behavior: 'smooth' });
}

function cancelarEdicion() {
  document.getElementById('titulo-form-camion').textContent = 'Nuevo camión';
  document.getElementById('btn-guardar-camion').textContent = 'Guardar camión';
  document.getElementById('btn-cancelar-camion').style.display = 'none';
  document.getElementById('camion-id').value = '';
  document.getElementById('form-camion').reset();
  document.getElementById('capacidad_kg').value = 6000;
  document.getElementById('mensaje-camion').innerHTML = '';
}

document.getElementById('btn-cancelar-camion').addEventListener('click', cancelarEdicion);

async function eliminarCamion(id) {
  if (!confirm('¿Eliminar este camión?')) return;

  try {
    const respuesta = await fetchAutenticado(`${API_URL}/camiones.php?id=${id}`, { method: 'DELETE' });
    const resultado = await respuesta.json();
    if (respuesta.ok && resultado.exito) {
      cargarCamiones();
    } else {
      alert(resultado.mensaje);
    }
  } catch (error) {
    alert('Error de conexión');
    console.error(error);
  }
}

document.getElementById('form-camion').addEventListener('submit', async function (evento) {
  evento.preventDefault();

  const id = document.getElementById('camion-id').value;
  const lat = document.getElementById('latitud').value;
  const lng = document.getElementById('longitud').value;

  const datos = {
    matricula:    document.getElementById('matricula').value,
    estado:       document.getElementById('estado').value,
    capacidad_kg: parseFloat(document.getElementById('capacidad_kg').value) || 6000,
    latitud:      lat ? parseFloat(lat) : null,
    longitud:     lng ? parseFloat(lng) : null,
  };

  const divMensaje = document.getElementById('mensaje-camion');
  const url = id ? `${API_URL}/camiones.php?id=${id}` : `${API_URL}/camiones.php`;
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
      cancelarEdicion();
      cargarCamiones();
    } else {
      divMensaje.innerHTML = `<div class="mensaje error">${resultado.mensaje}</div>`;
    }
  } catch (error) {
    divMensaje.innerHTML = `<div class="mensaje error">Error de conexión con el servidor</div>`;
    console.error(error);
  }
});

cargarCamiones();
