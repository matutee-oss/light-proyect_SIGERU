const sesion = exigirSesion(['administrador']);

let todosLosContenedores = [];
let modoEdicionContenedor = false;

async function cargarZonas() {
  try {
    const respuesta = await fetch(`${API_URL}/zonas.php`);
    const zonas = await respuesta.json();
    const opciones = zonas.map(z => `<option value="${z.id_zona}">${escaparHtml(z.nombre_zona)}</option>`).join('');
    document.getElementById('id_zona').innerHTML = '<option value="">Sin zona</option>' + opciones;
    document.getElementById('filtro-zona').innerHTML = '<option value="">Todas</option>' + opciones;
  } catch (error) {
    console.error(error);
  }
}

async function cargarContenedores() {
  const tabla = document.getElementById('tabla-contenedores');
  tabla.innerHTML = '<tr><td colspan="7">Cargando...</td></tr>';

  const filtros = new URLSearchParams();
  const zona = document.getElementById('filtro-zona').value;
  const estado = document.getElementById('filtro-estado').value;
  if (zona) filtros.set('id_zona', zona);
  if (estado) filtros.set('estado', estado);

  try {
    const respuesta = await fetchAutenticado(`${API_URL}/contenedores.php?${filtros}`);
    todosLosContenedores = await respuesta.json();
    renderizarContenedores(todosLosContenedores);
  } catch (error) {
    tabla.innerHTML = '<tr><td colspan="7">Error al cargar contenedores</td></tr>';
    console.error(error);
  }
}

function renderizarContenedores(contenedores) {
  const tabla = document.getElementById('tabla-contenedores');
  if (!contenedores.length) {
    tabla.innerHTML = '<tr><td colspan="7" style="color:var(--ink-faint);">Sin contenedores registrados.</td></tr>';
    return;
  }

  tabla.innerHTML = '';
  contenedores.forEach(function (contenedor) {
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td class="mono" style="font-size:12px;">${contenedor.id_contenedor}</td>
      <td>${escaparHtml(contenedor.ubicacion)}</td>
      <td>${escaparHtml(contenedor.tipo_residuo)}</td>
      <td>${insigniaEstado(contenedor.estado, 'contenedor')}</td>
      <td style="font-size:13px;">${escaparHtml(contenedor.nombre_zona || '—')}</td>
      <td class="mono" style="font-size:12px;">${Number(contenedor.nivel_llenado)} %</td>
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
  document.getElementById('capacidad_litros').value = contenedor.capacidad_litros || 2400;
  document.getElementById('nivel_llenado').value = contenedor.nivel_llenado || 0;

  document.querySelector('section.tarjeta').scrollIntoView({ behavior: 'smooth' });
}

function cancelarEdicionContenedor() {
  modoEdicionContenedor = false;
  document.getElementById('titulo-form-contenedor').textContent = 'Nuevo contenedor';
  document.getElementById('btn-guardar-contenedor').textContent = 'Guardar contenedor';
  document.getElementById('btn-cancelar-contenedor').style.display = 'none';
  document.getElementById('contenedor-id').value = '';
  document.getElementById('form-contenedor').reset();
}

document.getElementById('btn-cancelar-contenedor').addEventListener('click', cancelarEdicionContenedor);

async function eliminarContenedor(id) {
  if (!confirm('¿Eliminar este contenedor?')) return;

  try {
    const respuesta = await fetchAutenticado(`${API_URL}/contenedores.php?id=${id}`, { method: 'DELETE' });
    const resultado = await respuesta.json();
    if (respuesta.ok && resultado.exito) {
      document.getElementById('filtro-zona').addEventListener('change', cargarContenedores);
document.getElementById('filtro-estado').addEventListener('change', cargarContenedores);

cargarZonas();
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
    capacidad_litros: document.getElementById('capacidad_litros').value,
    nivel_llenado:    document.getElementById('nivel_llenado').value,
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
      divMensaje.innerHTML = `<div class="mensaje exito">${escaparHtml(resultado.mensaje)}</div>`;
      cancelarEdicionContenedor();
      document.getElementById('filtro-zona').addEventListener('change', cargarContenedores);
document.getElementById('filtro-estado').addEventListener('change', cargarContenedores);

cargarZonas();
cargarContenedores();
    } else {
      divMensaje.innerHTML = `<div class="mensaje error">${escaparHtml(resultado.mensaje)}</div>`;
    }
  } catch (error) {
    divMensaje.innerHTML = `<div class="mensaje error">Error de conexión con el servidor</div>`;
    console.error(error);
  }
});

document.getElementById('filtro-zona').addEventListener('change', cargarContenedores);
document.getElementById('filtro-estado').addEventListener('change', cargarContenedores);

cargarZonas();
cargarContenedores();
