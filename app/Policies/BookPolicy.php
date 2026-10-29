<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

/**
 * Autorización por Policies (§14): cada usuario accede solo a sus libros.
 */
class BookPolicy
{
    public function view(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }

    public function update(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }

    public function delete(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }

    public function export(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }
}
