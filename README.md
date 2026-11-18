# Laravel DDD Implementation

Project ini merupakan media belajar untuk mengimplementasikan **Domain-Driven Design (DDD)** pada aplikasi Laravel.

## Arsitektur

Project ini menerapkan beberapa pendekatan arsitektur berikut:

- **Domain-Driven Design (DDD)** untuk memusatkan desain aplikasi pada domain dan aturan bisnis.
- **Command Query Responsibility Segregation (CQRS)** untuk memisahkan operasi perubahan data (*command*) dari operasi pembacaan data (*query*).
- **Hexagonal Architecture** untuk menjaga domain tetap independen dari detail framework, database, dan antarmuka eksternal.

## Database

Project menggunakan **SQLite** lokal sebagai database pengembangan. Berkas database berada di `database/database.sqlite`.

## Tujuan Pembelajaran

Melalui project ini, pembelajaran difokuskan pada:

- Pemodelan domain, entity, value object, dan aggregate.
- Pemisahan tanggung jawab antara command dan query.
- Penerapan port dan adapter dalam Hexagonal Architecture.
- Integrasi pola-pola tersebut ke dalam ekosistem Laravel.
