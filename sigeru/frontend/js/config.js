// URL de la API. Se detecta sola según cómo se abrió el frontend:
//  - Docker (frontend en el puerto 3000) → backend en el puerto 8080
//  - XAMPP (http://localhost/sigeru/frontend/...) → misma carpeta del proyecto
const API_URL = (function () {
  const { protocol, hostname, port, pathname } = window.location;
  if (port === '3000') {
    return `${protocol}//${hostname}:8080`;
  }
  const raiz = pathname.includes('/frontend/') ? pathname.split('/frontend/')[0] : '/sigeru';
  return `${protocol}//${hostname}${port ? ':' + port : ''}${raiz}/backend`;
})();
