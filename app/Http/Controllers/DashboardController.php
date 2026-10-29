<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $books = Book::where('user_id', $request->user()->id)
            ->withCount('chapters')
            ->latest('updated_at')
            ->get();

        return view('books.index', ['books' => $books]);
    }
}
