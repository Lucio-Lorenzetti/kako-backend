# 🏓 Kako Padel - Backend

Backend del sistema de gestión de turnos para la cancha de pádel **Kako Padel**.  
Este proyecto provee una **API RESTful** y un **panel administrativo** que permiten a los clientes reservar turnos y a la administración gestionar la cancha de manera rápida, segura y eficiente.

---

## 📌 Funcionalidades principales

### 👥 Usuarios
- Registro y gestión de usuarios.
- Roles: `admin` (propietario) y `user` (cliente).
- Autenticación pendiente de integración con **Auth0**.

### 🗓️ Turnos
- Creación de turnos con fecha, hora, estado y precio.
- Estados posibles: `disponible`, `reservado`, `inactivo`.
- Listado de turnos disponibles para clientes.

### 📑 Reservas
- Asociación de reservas a usuarios y turnos.
- Consulta de reservas de un cliente.
- Creación de reservas desde el frontend.

### 🔧 Administración
- Panel exclusivo para el propietario.
- Alta, baja y edición de turnos.
- Liberación de reservas o bloqueo de turnos.
- Modificación de precios y contenidos visibles.
- Informes básicos de actividad diaria.

---

## 🛠️ Tecnologías utilizadas
- **Laravel 11** – framework principal.
- **MySQL** – base de datos relacional.
- **Eloquent ORM** – manejo de entidades y relaciones.
- **Seeder & Factory** – carga inicial de datos de prueba.
- **API RESTful** – comunicación con frontend React.
- **Auth0** – (pendiente) autenticación y autorización.


## 📬 Contacto
Desarrollado por Lorenzetti Lucio  
E-mail: lucioadriell@gmail.com
