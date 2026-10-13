# test-manager-library-3145556-api

> **Prueba técnica · Ficha ADSO 3145556**
> Entrega: **martes 13 de octubre de 2026, a las 5:00 p. m.** (17:00, hora Colombia).

Este repositorio es el **backend** de *Manager Library*, en **PHP con Laravel**. **Empieza vacío a propósito**: se construye en el fork de cada aprendiz.

## Instrucciones

Cada aprendiz debe **crear el fork** de los seis repositorios del proyecto y **resolver el proyecto
con el spec planteado**.

1. Hacer fork, a su cuenta de GitHub, de cada repositorio de la tabla del final.
2. Leer el spec en [`test-manager-library-3145556-docs`](https://github.com/code-sena/test-manager-library-3145556-docs).
   Se entrega en dos versiones: `spec-python/` y `spec-.net/`.
3. Desarrollar en los forks.

## El reto se desarrolla con React y PHP (Laravel)

El spec está escrito para Python y para .NET, pero el reto **no** se hace en esos lenguajes:

| Capa | Tecnología del reto |
|---|---|
| Frontend | React |
| Backend | PHP con Laravel |

Lo que el spec define sobre el negocio —historias, criterios de aceptación, reglas, contrato de la
API, modelo de datos— se respeta. Lo que define sobre la tecnología se traduce a React y Laravel.

## La prueba no consiste en escribir el código

El propósito principal es ver la **capacidad de desempeño con SDD** (*Spec-Driven Development*,
desarrollo guiado por especificación): cómo se lee, se interpreta y se aplica una especificación
para llevarla a un stack distinto. El código es el medio, no el fin.

## Entrega

Cuenta lo que quede **subido a los forks antes de las 5:00 p. m.** (17:00). Lo que llegue después no se revisa.
No hay prórroga: suban avances parciales con commits pequeños durante la prueba.

## Los seis repositorios

| Repositorio | Qué va ahí |
|---|---|
| [`test-manager-library-3145556-docs`](https://github.com/code-sena/test-manager-library-3145556-docs) | El spec: `spec-python/` y `spec-.net/` |
| [`test-manager-library-3145556-api`](https://github.com/code-sena/test-manager-library-3145556-api) | Backend en PHP (Laravel) |
| [`test-manager-library-3145556-app`](https://github.com/code-sena/test-manager-library-3145556-app) | Frontend en React |
| [`test-manager-library-3145556-page`](https://github.com/code-sena/test-manager-library-3145556-page) | Sitio público estático de presentación |
| [`test-manager-library-3145556-infra`](https://github.com/code-sena/test-manager-library-3145556-infra) | Contenedores, red, volúmenes y motor de base de datos vacío |
| [`test-manager-library-3145556-tool`](https://github.com/code-sena/test-manager-library-3145556-tool) | Utilidades: sembrador de datos de demostración |

---

## Cómo correr la API

Requiere Docker; no hace falta PHP local. La forma normal es con `infra` (`docker compose up`, ver su README). Solo la API, contra una base ya levantada:

```bash
cp .env.example .env     # completar DATABASE_URL, JWT_SECRET, ADMIN_USERNAME, ADMIN_PASSWORD
docker build -t library-api .
docker run --rm --env-file .env -p 8000:8000 library-api
curl http://localhost:8000/api/v1/health      # {"status":"ok"}
```

Al arrancar, el contenedor corre `php artisan migrate --force` (el esquema `library` lo crean las migraciones, ADR-001) y `php artisan library:bootstrap-admin` (crea el admin desde `ADMIN_USERNAME`/`ADMIN_PASSWORD`; es idempotente).

## Cómo probar

Las pruebas usan PostgreSQL real (una base distinta, por ejemplo `library_test`) y reconstruyen el esquema desde las migraciones.

```bash
docker run --rm --network <red-de-la-base> --env-file .env \
  -e DATABASE_URL=postgresql://USER:PASS@db:5432/library_test -e APP_ENV=testing \
  --entrypoint "" library-api vendor/bin/pest
```

| Suite | Carpeta | Tarea | Contra qué corre |
|---|---|---|---|
| Dominio | `tests/Unit` | T-03…T-06 | Objetos puros |
| Aplicación | `tests/Application` | T-07, T-09, T-10 | Fakes en memoria |
| Integración | `tests/Integration` | T-02, T-18 | PostgreSQL (consultas de `data-model.md` §10) |
| Contrato | `tests/Contract` | T-16 | La API completa: rutas y los 14 códigos de error |
| Concurrencia | `tests/Concurrency` | T-13, RNF-2 | Procesos paralelos sobre el último ejemplar |
| Arquitectura | `tests/Architecture` | T-19 | Pest Arch (artículos I, II y III) |

## Estructura (hexagonal)

```text
src/
├── Domain/        # PHP puro: agregados, objetos de valor, política y errores (Art. I)
├── Application/   # puertos (interfaces) y un servicio por caso de uso (Art. II)
├── Adapters/
│   ├── Inbound/   # Http (controladores, middleware, mapeo de errores) y Console
│   └── Outbound/  # Persistence (PostgreSQL), Security (Argon2id, JWT), Storage, Time
└── Bootstrap/Composition/PortBindings.php   # único punto de composición (Art. III)
```

Las decisiones sobre huecos del spec están en `docs/spec-laravel/decisiones-y-huecos.md` y los ADR-005…007.
