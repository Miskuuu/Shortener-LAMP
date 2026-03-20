# Shortener-LAMP

Sistema de acortamiento y monitoreo de URLs desarrollado desde cero con stack LAMP, sin frameworks en backend ni frontend.

## Requisitos

- Apache + PHP 8+
- MySQL/MariaDB
- Navegador web

## Estructura

- `backend/api/` API en PHP con respuestas JSON
- `backend/redirect/index.php` redirección por URL corta y registro de visita
- `backend/sql/schema.sql` esquema de base de datos
- `frontend/index.html` SPA en HTML + CSS + JavaScript + AJAX

## Instalacion rapida

1. Copia el proyecto dentro del `DocumentRoot` de Apache (por ejemplo, `htdocs` o `www`).
2. Importa el esquema:

```sql
SOURCE /ruta/al/proyecto/backend/sql/schema.sql;
```

3. Verifica credenciales de MySQL en `backend/api/config/database.php`.
4. Abre en navegador: `http://localhost/Shortener-LAMP/frontend/index.html`.

## Configuracion de ruta base

La ruta base se define desde la interfaz web (campo `Ruta base del sistema`) y se guarda en:

- `backend/api/config/app_config.json`

Endpoint asociado:

- `GET backend/api/config/base.php`
- `POST backend/api/config/base.php`

Ejemplo `POST` JSON:

```json
{
	"baseUrl": "http://localhost/Shortener-LAMP",
	"redirectPath": "/backend/redirect/index.php"
}
```

## Endpoints principales (JSON)

- `POST /backend/api/urls/create.php`
	- body: `{ "originalUrl": "https://ejemplo.com" }`
- `GET /backend/api/urls/list.php`
- `GET /backend/api/urls/get.php?id=1`
- `GET /backend/api/urls/stats.php?id=1`
- `GET /backend/api/urls/countries.php?id=1`
- `GET /backend/api/urls/chart.php?id=1`
- `POST /backend/api/visits/register.php`

## Flujo funcional implementado

- Definicion de ruta base del sistema.
- Creacion de URL corta usando la ruta base configurada.
- Al navegar una URL corta:
	- Guarda IP y hora de peticion.
	- Resuelve y guarda pais por IP (IPInfo con fallback a ipapi).
- Vista por URL con:
	- Fecha de creacion.
	- Total de accesos.
	- Lista de paises y frecuencia.
	- Grafica de frecuencia de accesos por dia.

## Interfaz SPA

- Sin recargar la pagina.
- Uso de `fetch`/AJAX para todas las operaciones.
- Vista en una sola pagina con estilos CSS responsivos y renderizado dinamico.

## Enlaces solicitados por la entrega

- Enlace del sistema funcionando: `PENDIENTE_PUBLICAR`
- Enlace del repositorio Git: `PENDIENTE_REPOSITORIO`

