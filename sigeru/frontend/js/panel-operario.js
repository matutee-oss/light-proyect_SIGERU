const sesion = exigirSesion(['operario']);

function pintarMedidor(capacidad) {
  const p = capacidad.porcentaje;
  const relleno = document.getElementById('medidor-relleno');
  const etiqueta = document.getElementById('medidor-etiqueta');

  relleno.style.width = p + '%';
  etiqueta.textContent = p + '%';

  if (p >= 85) {
    relleno.style.background = 'linear-gradient(90deg, var(--clay), #d1543d)';
    document.getElementById('alerta-capacidad').innerHTML =
      `<div class="mensaje error" style="margin-bottom: 20px;"><strong>⚠️ Capacidad al ${p}%</strong> · Es urgente coordinar retiro de residuos.</div>`;
    document.getElementById('alerta-capacidad').style.display = 'block';
  } else if (p >= 70) {
    relleno.style.background = 'linear-gradient(90deg, var(--amber), var(--amber-bright))';
    document.getElementById('alerta-capacidad').innerHTML =
      `<div class="mensaje" style="margin-bottom: 20px; background: var(--amber-tint); color: var(--amber); border-left: 3px solid var(--amber);"><strong>Capacidad al ${p}%</strong> · Considerar planificar retiro pronto.</div>`;
    document.getElementById('alerta-capacidad').style.display = 'block';
  } else {
    relleno.style.background = 'linear-gradient(90deg, var(--petrol), #2a6259)';
    document.getElementById('alerta-capacidad').style.display = 'none';
  }

  document.getElementById('capacidad-texto').innerHTML =
    `<strong>${capacidad.toneladas_30d} toneladas</strong> ingresadas en los últimos 30 días`;
}

function pintarGraficoIngresos(datos) {
  const contenedor = document.getElementById('grafico-ingresos');
  if (!datos || datos.length === 0) {
    contenedor.innerHTML = '<p class="mono" style="color:var(--ink-faint); font-size:13px;">Sin ingresos recientes.</p>';
    return;
  }

  const max = Math.max(...datos.map(d => parseFloat(d.total)));

  contenedor.innerHTML = `
    <div class="grafico-barras">
      ${datos.map(function (d) {
        const altura = Math.round((parseFloat(d.total) / max) * 100);
        const fecha = new Date(d.dia).toLocaleDateString('es-UY', { day: '2-digit', month: 'short' });
        return `
          <div class="grafico-columna">
            <div class="grafico-valor">${parseFloat(d.total).toFixed(1)}t</div>
            <div class="grafico-barra" style="height: ${altura}%"></div>
            <div class="grafico-etiqueta">${fecha}</div>
          </div>
        `;
      }).join('')}
    </div>
  `;
}

function pintarIngresos(ingresos) {
  const tabla = document.getElementById('tabla-ingresos');
  if (!ingresos || ingresos.length === 0) {
    tabla.innerHTML = '<tr><td colspan="4" style="color:var(--ink-faint);">Sin ingresos registrados.</td></tr>';
    return;
  }
  tabla.innerHTML = ingresos.map(function (i) {
    const fecha = new Date(i.registrado_en).toLocaleString('es-UY', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
    return `
      <tr>
        <td class="mono" style="font-size:12px;">${fecha}</td>
        <td><strong>${parseFloat(i.toneladas).toFixed(2)} t</strong></td>
        <td>${i.matricula ? `<span class="mono">${escaparHtml(i.matricula)}</span>` : '—'}</td>
        <td style="color: var(--ink-soft);">${escaparHtml(i.observaciones || '—')}</td>
      </tr>
    `;
  }).join('');
}

function pintarRecursos(recursos) {
  const tabla = document.getElementById('tabla-recursos');
  tabla.innerHTML = recursos.map(function (r) {
    return `
      <tr>
        <td>${escaparHtml(r.tipo)}</td>
        <td><input type="number" min="0" value="${r.cantidad}" data-id="${r.id_recurso}" class="input-recurso"></td>
        <td><button data-id="${r.id_recurso}" class="btn-guardar-recurso boton-primario" style="padding: 6px 14px; font-size:12px;">Guardar</button></td>
      </tr>
    `;
  }).join('');

  tabla.querySelectorAll('.btn-guardar-recurso').forEach(function (b) {
    b.addEventListener('click', async function () {
      const id = b.dataset.id;
      const input = tabla.querySelector(`.input-recurso[data-id="${id}"]`);
      const respuesta = await fetchAutenticado(`${API_URL}/panel_operario.php?id_recurso=${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cantidad: parseInt(input.value, 10) })
      });
      if (respuesta.ok) {
        b.textContent = '✓ Guardado';
        setTimeout(() => { b.textContent = 'Guardar'; }, 1500);
      }
    });
  });
}

function pintarSelectCamiones(camiones) {
  document.getElementById('id_camion').innerHTML =
    '<option value="">— sin especificar —</option>' +
    camiones.map(c => `<option value="${c.id_camion}">${c.matricula}</option>`).join('');
}

async function cargarPanel() {
  const respuesta = await fetchAutenticado(`${API_URL}/panel_operario.php`);
  const datos = await respuesta.json();

  if (!respuesta.ok) {
    document.getElementById('contenido-operario').innerHTML =
      `<div class="mensaje error">${escaparHtml(datos.mensaje)}</div>`;
    return;
  }

  document.getElementById('nombre-centro').textContent = datos.centro.nombre;
  document.getElementById('ubicacion-centro').textContent = datos.centro.ubicacion;
  document.getElementById('capacidad-centro').textContent = `${datos.centro.capacidad} m³`;

  pintarMedidor(datos.capacidad);
  pintarGraficoIngresos(datos.ingresos_por_dia);
  pintarIngresos(datos.ingresos_recientes);
  pintarRecursos(datos.recursos);
  pintarSelectCamiones(datos.camiones);
}

document.getElementById('form-ingreso').addEventListener('submit', async function (evento) {
  evento.preventDefault();

  const datos = {
    toneladas: document.getElementById('toneladas').value,
    id_camion: document.getElementById('id_camion').value,
    observaciones: document.getElementById('observaciones').value
  };

  const respuesta = await fetchAutenticado(`${API_URL}/panel_operario.php?accion=registrar_ingreso`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(datos)
  });

  const resultado = await respuesta.json();
  const divMensaje = document.getElementById('mensaje-ingreso');

  if (respuesta.ok && resultado.exito) {
    divMensaje.innerHTML = `<div class="mensaje exito">${escaparHtml(resultado.mensaje)}</div>`;
    document.getElementById('form-ingreso').reset();
    cargarPanel();
  } else {
    divMensaje.innerHTML = `<div class="mensaje error">${escaparHtml(resultado.mensaje)}</div>`;
  }
});

cargarPanel();
