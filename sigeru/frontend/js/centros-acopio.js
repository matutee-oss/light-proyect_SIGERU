const sesion = exigirSesion(['administrador']);

let todosCentros = [];

async function cargarCentros() {
  const contenedor = document.getElementById('lista-centros');
  contenedor.innerHTML = '<p class="mono" style="color:var(--ink-faint);">Cargando...</p>';

  try {
    const respuesta = await fetchAutenticado(`${API_URL}/centros_acopio.php`);
    todosCentros = await respuesta.json();
    renderizarCentros(todosCentros);
  } catch (error) {
    contenedor.innerHTML = '<p class="mono" style="color:var(--clay);">Error al cargar centros.</p>';
    console.error(error);
  }
}

function renderizarCentros(centros) {
  const contenedor = document.getElementById('lista-centros');

  if (!centros.length) {
    contenedor.innerHTML = '<p class="mono" style="color:var(--ink-faint); font-size:13px;">No hay centros de acopio registrados.</p>';
    return;
  }

  contenedor.innerHTML = centros.map(function (c) {
    const recursos = c.recursos || [];
    const tablaRecursos = recursos.length
      ? `<table class="listado" style="margin-top:12px;">
          <thead><tr><th>Recurso / Maquinaria</th><th>Cantidad</th></tr></thead>
          <tbody>
            ${recursos.map(r => `<tr><td>${r.tipo}</td><td class="mono">${r.cantidad}</td></tr>`).join('')}
          </tbody>
        </table>`
      : '<p class="mono" style="color:var(--ink-faint); font-size:12px; margin-top:8px;">Sin recursos registrados.</p>';

    return `
      <section class="tarjeta" style="margin-bottom:16px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
          <div>
            <div class="eyebrow">Centro #${c.id_centro}</div>
            <h3 style="font-size:20px; margin:4px 0;">${c.nombre}</h3>
            <p style="color:var(--ink-soft); margin:0; font-size:14px;">${c.ubicacion}</p>
            <p style="color:var(--ink-soft); margin:4px 0 0; font-size:13px;" class="mono">Capacidad: ${Number(c.capacidad).toLocaleString()} t</p>
          </div>
          <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <button data-id="${c.id_centro}" class="btn-editar-centro" style="padding:6px 16px; font-size:13px; background:var(--petrol); color:#fff; border:none; border-radius:var(--radio); cursor:pointer;">Editar</button>
            <button data-id="${c.id_centro}" class="btn-agregar-recurso boton-primario" style="padding:6px 16px; font-size:13px;">+ Recurso</button>
            <button data-id="${c.id_centro}" class="btn-eliminar-centro" style="padding:6px 16px; font-size:13px; background:none; border:1px solid var(--clay); color:var(--clay); border-radius:var(--radio); cursor:pointer;">Eliminar</button>
          </div>
        </div>
        <div>
          <h4 style="font-size:14px; color:var(--ink-soft); margin:16px 0 4px;">Recursos y maquinaria</h4>
          ${tablaRecursos}
        </div>
      </section>
    `;
  }).join('');

  document.querySelectorAll('.btn-editar-centro').forEach(b => {
    b.addEventListener('click', () => activarEdicion(parseInt(b.dataset.id)));
  });
  document.querySelectorAll('.btn-agregar-recurso').forEach(b => {
    b.addEventListener('click', () => abrirModalRecurso(parseInt(b.dataset.id)));
  });
  document.querySelectorAll('.btn-eliminar-centro').forEach(b => {
    b.addEventListener('click', () => eliminarCentro(parseInt(b.dataset.id)));
  });
}

function activarEdicion(id) {
  const centro = todosCentros.find(c => c.id_centro === id);
  if (!centro) return;

  document.getElementById('titulo-form-centro').textContent = 'Editar centro de acopio';
  document.getElementById('btn-guardar-centro').textContent = 'Actualizar centro';
  document.getElementById('btn-cancelar-centro').style.display = 'inline-block';

  document.getElementById('centro-id').value = centro.id_centro;
  document.getElementById('c-nombre').value = centro.nombre;
  document.getElementById('c-ubicacion').value = centro.ubicacion;
  document.getElementById('c-capacidad').value = centro.capacidad;

  document.querySelector('section.tarjeta').scrollIntoView({ behavior: 'smooth' });
}

function cancelarEdicion() {
  document.getElementById('titulo-form-centro').textContent = 'Nuevo centro de acopio';
  document.getElementById('btn-guardar-centro').textContent = 'Guardar centro';
  document.getElementById('btn-cancelar-centro').style.display = 'none';
  document.getElementById('centro-id').value = '';
  document.getElementById('form-centro').reset();
  document.getElementById('mensaje-centro').innerHTML = '';
}

document.getElementById('btn-cancelar-centro').addEventListener('click', cancelarEdicion);

document.getElementById('form-centro').addEventListener('submit', async function (evento) {
  evento.preventDefault();

  const id = document.getElementById('centro-id').value;
  const datos = {
    nombre:    document.getElementById('c-nombre').value,
    ubicacion: document.getElementById('c-ubicacion').value,
    capacidad: parseFloat(document.getElementById('c-capacidad').value),
  };

  const url = id ? `${API_URL}/centros_acopio.php?id=${id}` : `${API_URL}/centros_acopio.php`;
  const metodo = id ? 'PUT' : 'POST';
  const divMensaje = document.getElementById('mensaje-centro');

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
      cargarCentros();
    } else {
      divMensaje.innerHTML = `<div class="mensaje error">${resultado.mensaje}</div>`;
    }
  } catch (error) {
    divMensaje.innerHTML = `<div class="mensaje error">Error de conexión con el servidor</div>`;
    console.error(error);
  }
});

async function eliminarCentro(id) {
  const centro = todosCentros.find(c => c.id_centro === id);
  if (!centro) return;
  if (!confirm(`¿Eliminar el centro "${centro.nombre}"? Esta acción no se puede deshacer.`)) return;

  try {
    const respuesta = await fetchAutenticado(`${API_URL}/centros_acopio.php?id=${id}`, { method: 'DELETE' });
    const resultado = await respuesta.json();
    if (respuesta.ok && resultado.exito) {
      cargarCentros();
    } else {
      alert(resultado.mensaje);
    }
  } catch (error) {
    alert('Error de conexión');
    console.error(error);
  }
}

function abrirModalRecurso(idCentro) {
  document.getElementById('recurso-centro-id').value = idCentro;
  document.getElementById('form-recurso').reset();
  document.getElementById('r-cantidad').value = 1;
  document.getElementById('mensaje-recurso').innerHTML = '';
  const modal = document.getElementById('modal-recurso');
  modal.style.display = 'flex';
}

document.getElementById('btn-cerrar-modal').addEventListener('click', function () {
  document.getElementById('modal-recurso').style.display = 'none';
});

document.getElementById('modal-recurso').addEventListener('click', function (e) {
  if (e.target === this) this.style.display = 'none';
});

document.getElementById('form-recurso').addEventListener('submit', async function (evento) {
  evento.preventDefault();

  const idCentro = document.getElementById('recurso-centro-id').value;
  const datos = {
    tipo:     document.getElementById('r-tipo').value,
    cantidad: parseInt(document.getElementById('r-cantidad').value),
  };

  const divMensaje = document.getElementById('mensaje-recurso');

  try {
    const respuesta = await fetchAutenticado(
      `${API_URL}/centros_acopio.php?id=${idCentro}&accion=agregar_recurso`,
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos)
      }
    );

    const resultado = await respuesta.json();

    if (respuesta.ok && resultado.exito) {
      divMensaje.innerHTML = `<div class="mensaje exito">${resultado.mensaje}</div>`;
      setTimeout(() => {
        document.getElementById('modal-recurso').style.display = 'none';
        cargarCentros();
      }, 800);
    } else {
      divMensaje.innerHTML = `<div class="mensaje error">${resultado.mensaje}</div>`;
    }
  } catch (error) {
    divMensaje.innerHTML = `<div class="mensaje error">Error de conexión</div>`;
    console.error(error);
  }
});

cargarCentros();
