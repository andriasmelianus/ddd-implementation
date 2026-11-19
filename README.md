# Laravel DDD Implementation — IAM Bounded Context

Repositori ini merupakan implementasi referensi dan media pembelajaran arsitektur **Domain-Driven Design (DDD)**, **Hexagonal Architecture (Ports and Adapters)**, serta **Command Query Responsibility Segregation (CQRS)** pada ekosistem Laravel.

Studi kasus utama yang diimplementasikan adalah **Mekanisme Autentikasi Login Multi-Kondisi** pada Bounded Context **IAM (Identity & Access Management)** (`src/IAM/`).

---

## 🚀 Panduan Instalasi & Menjalankan Proyek

### 1. Kebutuhan Sistem
- **PHP** $\ge$ 8.4
- **Composer** $\ge$ 2.x
- **SQLite3**

### 2. Langkah Instalasi

1. **Clone repository & masuk ke direktori proyek**:
   ```bash
   git clone <repository-url>
   cd ddd-implementation
   ```

2. **Install dependensi Composer**:
   ```bash
   composer install
   ```

3. **Konfigurasi Environment**:
   Salin `.env.example` menjadi `.env` dan generate application key:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Persiapan Database SQLite**:
   Pastikan file database SQLite telah tersedia dan jalankan migrasi:
   ```bash
   touch database/database.sqlite
   php artisan migrate
   ```

5. **Seed Data Pengguna Default**:
   Jalankan seeder untuk mengisi akun pengujian:
   ```bash
   php artisan db:seed --class=UserSeeder
   ```
   *Data akun bawaan:*
   - **Email**: `user@example.com`
   - **Password**: `secret123`
   - **Status**: `ACTIVE`

6. **Menjalankan Server Pengembangan**:
   ```bash
   php artisan serve
   ```
   Aplikasi akan aktif di `http://127.0.0.1:8000`.

7. **Menjalankan Pengujian Otomatis (Automated Tests)**:
   ```bash
   # Menjalankan seluruh Unit Test dan Feature Test
   php artisan test

   # Menjalankan spesifik modul IAM
   php artisan test --filter=IAM
   ```

---

## 🏛️ Konteks DDD & Struktur File Bounded Context `IAM`

Seluruh logika bisnis autentikasi diisolasi di dalam modul `src/IAM/` dan terbebas dari ketergantungan langsung terhadap framework Laravel (diatur oleh aturan *Inward Dependency*).

```
src/IAM/
├── Domain/
│   ├── ValueObject/
│   │   ├── EmailAddress.php                # Value Object validasi format email murni
│   │   ├── AccountStatus.php               # Enum status akun (ACTIVE, SUSPENDED, UNVERIFIED)
│   │   └── UserId.php                      # Strongly-typed identifier berbasis ULID
│   ├── Entity/
│   │   └── User.php                        # Aggregate Root pengelola invarian autentikasi
│   ├── Event/
│   │   └── AccountSuspendedDueToFailedLogins.php # Domain Event saat akun ter-suspend otomatis
│   ├── Exception/
│   │   ├── InvalidCredentialsException.php # Exception saat email/password salah (HTTP 401)
│   │   ├── AccountSuspendedException.php   # Exception saat akun ditangguhkan (HTTP 403)
│   │   ├── AccountUnverifiedException.php  # Exception saat akun belum terverifikasi (HTTP 403)
│   │   └── InvalidEmailException.php       # Exception saat format sintaksis email salah
│   └── Port/
│       ├── UserRepositoryInterface.php     # Kontrak outbound persistence domain
│       ├── PasswordHasherInterface.php     # Kontrak outbound verifikasi password
│       └── TokenGeneratorInterface.php     # Kontrak outbound pembuatan auth token
├── Application/
│   ├── Command/
│   │   ├── AuthenticateUserCommand.php     # DTO input command autentikasi
│   │   └── AuthenticateUserCommandHandler.php # Orchestrator alur autentikasi pengguna
│   └── DTO/
│       └── AuthTokenDto.php                # DTO output token autentikasi (JsonSerializable)
├── Infrastructure/
│   ├── Adapter/
│   │   ├── EloquentUserRepository.php      # Implementasi UserRepositoryInterface via Eloquent
│   │   ├── BcryptPasswordHasher.php        # Implementasi PasswordHasherInterface via Hash facade
│   │   └── SanctumTokenGenerator.php       # Implementasi TokenGeneratorInterface via Bearer token
│   ├── Persistence/
│   │   └── UserModel.php                   # Model Eloquent murni untuk tabel 'users'
│   └── Providers/
│       └── IAMServiceProvider.php          # Service provider binding interface ke adapter
└── UI/
    ├── Controller/
    │   └── LoginAction.php                 # Single Action Controller ramping (≤ 20 baris)
    └── Request/
        └── LoginRequest.php                # FormRequest validasi format payload HTTP
```

### Penjelasan Rantai Tanggung Jawab (Separation of Concerns)

1. **Domain Layer (`src/IAM/Domain/`)**:
   - **`User` (Aggregate Root)**: Menjaga konsistensi data dan aturan bisnis:
     - Pengecekan status akun wajib dilakukan sebelum memverifikasi password (`SUSPENDED` memicu `AccountSuspendedException`, `UNVERIFIED` memicu `AccountUnverifiedException`).
     - Jika password tidak cocok: menambah counter `failed_login_attempts`. Jika counter mencapai $\ge 5$, status akun otomatis diubah menjadi `SUSPENDED` dan menerbitkan domain event `AccountSuspendedDueToFailedLogins`.
     - Jika password cocok: mereset `failed_login_attempts` ke `0` dan memperbarui `last_login_at` ke waktu saat ini.
   - **`ValueObject`**: Menjamin keabsahan tipe primitif sejak dini (`EmailAddress`, `AccountStatus`, `UserId`).
   - **`Port`**: Interface murni yang menyatakan kebutuhan domain tanpa peduli teknologi database atau hashing apa yang digunakan.

2. **Application Layer (`src/IAM/Application/`)**:
   - **`AuthenticateUserCommandHandler`**: Mengorkestrasi pemanggilan repositori, eksekusi method `User::authenticate()`, penyimpanan state perubahan counter gagal/sukses, dan pembuatan token via `TokenGeneratorInterface`.
   - Mengimplementasikan mitigasi kehilangan state: jika password salah, state `failed_login_attempts` tetap dipersistensi ke database sebelum `InvalidCredentialsException` dilempar ke presentation layer.

3. **Infrastructure Layer (`src/IAM/Infrastructure/`)**:
   - Mengimplementasikan *Ports* domain menggunakan teknologi konkret: Eloquent ORM (`EloquentUserRepository`), bcrypt hashing (`BcryptPasswordHasher`), dan generator token (`SanctumTokenGenerator`).
   - Merekonsiliasi data mentah tabel `users` menjadi entitas domain menggunakan static factory `User::reconstitute()` (tanpa reflection).

4. **UI Presentation Layer (`src/IAM/UI/`)**:
   - **`LoginRequest`**: Validasi awal HTTP format JSON dan pemetaan ke DTO command via `getDto()`.
   - **`LoginAction`**: Controller bertipe *Single Action* (ramping) yang murni mendelegasikan eksekusi ke handler dan memetakan exception domain ke kode status HTTP yang tepat (200, 401, 403, 422).

---

## 📡 Dokumentasi Endpoint & Pengujian API

### Endpoint Autentikasi
- **URL**: `POST /api/v1/auth/login`
- **Headers**:
  ```http
  Content-Type: application/json
  Accept: application/json
  ```

---

### Skenario 1: Login Berhasil (Success)

#### Request Payload
```json
{
    "email": "user@example.com",
    "password": "secret123"
}
```

#### Response Output (`200 OK`)
```json
{
    "access_token": "37398e0de93fe9bb3082642f43805c044abdf4492ce7c369e49af7d94bb1a630",
    "token_type": "Bearer",
    "user_id": "01M7S0JD3V5A7WJ6QZRBK2KKH0"
}
```
*Efek Samping*: `failed_login_attempts` direset menjadi `0`, dan `last_login_at` diperbarui ke timestamp saat ini.

---

### Skenario 2: Password Salah (Invalid Credentials)

#### Request Payload
```json
{
    "email": "user@example.com",
    "password": "wrong-password"
}
```

#### Response Output (`401 Unauthorized`)
```json
{
    "message": "Kredensial yang diberikan tidak valid."
}
```
*Efek Samping*: Nilai `failed_login_attempts` pada database bertambah $+1$.

---

### Skenario 3: Akun Ditangguhkan (Suspended / $\ge 5$ Percobaan Gagal)

Jika percobaan salah mencapai 5 kali berturut-turut, status akun berubah menjadi `SUSPENDED` dan request berikutnya ditolak.

#### Request Payload
```json
{
    "email": "user@example.com",
    "password": "secret123"
}
```

#### Response Output (`403 Forbidden`)
```json
{
    "message": "Akun Anda sedang ditangguhkan."
}
```

---

### Skenario 4: Akun Belum Terverifikasi (Unverified)

#### Request Payload
```json
{
    "email": "unverified@example.com",
    "password": "secret123"
}
```

#### Response Output (`403 Forbidden`)
```json
{
    "message": "Silakan verifikasi email Anda terlebih dahulu."
}
```

---

### Skenario 5: Format Email Tidak Valid (Validation Error)

#### Request Payload
```json
{
    "email": "bukan-format-email",
    "password": "secret123"
}
```

#### Response Output (`422 Unprocessable Content`)
```json
{
    "message": "The email field must be a valid email address.",
    "errors": {
        "email": [
            "The email field must be a valid email address."
        ]
    }
}
```

---

## 🧪 Eksekusi Pengujian (Testing)

Proyek dilengkapi dengan pengujian unit terisolasi (100% domain isolation dengan mock) serta pengujian fitur terintegrasi:

```bash
# Menjalankan Unit Test domain
php artisan test tests/Unit/IAM/Domain/UserAuthenticationTest.php

# Menjalankan Feature API Test
php artisan test tests/Feature/IAM/LoginApiTest.php
```

Hasil verifikasi:
```text
PASS  Tests\Unit\IAM\Domain\UserAuthenticationTest
✓ successful authentication resets attempts and updates last login
✓ wrong password increments failed attempts and throws invalid credentials exception
✓ reaches fifth failed attempt auto suspends account and records domain event
✓ suspended account throws exception without checking password
✓ unverified account throws exception without checking password

PASS  Tests\Feature\IAM\LoginApiTest
✓ login success returns token and resets failed attempts
✓ login with wrong password returns 401 and increments attempts in database
✓ fifth failed attempt suspends account in database
✓ login with suspended account returns 403
✓ login with unverified account returns 403
✓ login with non existent user returns 401
✓ login validation fails with invalid email format
```
