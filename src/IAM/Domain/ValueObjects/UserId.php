<?php

declare(strict_types=1);

namespace Src\IAM\Domain\ValueObjects;

use Src\IAM\Domain\Entity\User;
use Src\Shared\Framework\Domain\ValueObjects\Ulid;

/**
 * Identifier Value Object untuk Aggregate Root {@see User}.
 *
 * 🚨 Critical Rule #4 (IDs as Value Objects): identitas entitas **tidak boleh**
 * direpresentasikan sebagai `string` mentah. Kelas ini membungkus ULID sehingga
 * salah penempatan argumen (mis. menukar `UserId` dengan `TokenId`) langsung
 * tertangkap oleh type system PHP.
 *
 * Kolom `users.id` pada database bertipe ULID, sehingga VO ini memperluas
 * {@see Ulid} agar validasi format terjadi otomatis di konstruktor.
 */
final class UserId extends Ulid {}
