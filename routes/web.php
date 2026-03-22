<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        $user = auth()->user();
        if ($user->isAdmin()) {
            return redirect()->route('dashboard');
        } elseif ($user->isSupervisor()) {
            return redirect()->route('supervisor.dashboard');
        }
        return redirect()->route('dashboard'); // technical users or others can have a default too
    }
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified', 'role:admin'])->name('dashboard');

Route::get('tickets', function () {
    $search = request('search');
    $status = request('status');

    $tickets = \App\Models\Ticket::with(['assignedUser'])
        ->when($search, fn($query) => $query->where('title', 'like', "%{$search}%"))
        ->when($status && $status !== 'All', fn($query) => $query->where('status', $status))
        ->latest()
        ->get();

    return Inertia::render('Tickets', [
        'tickets' => $tickets->map(fn($ticket) => [
            'id' => 'TKT-' . (1000 + $ticket->id),
            'title' => $ticket->title,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'category' => $ticket->category,
            'assignedTo' => $ticket->assignedUser?->name ?? 'Unassigned',
            'createdAt' => $ticket->created_at->diffForHumans(),
        ]),
        'filters' => [
            'search' => $search,
            'status' => $status ?? 'All',
        ],
    ]);
})->middleware(['auth', 'verified', 'role:admin'])->name('tickets');

Route::post('tickets', function () {
    $validated = request()->validate([
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'category' => 'required|string',
        'priority' => 'required|string',
    ]);

    \App\Models\Ticket::create([
        ...$validated,
        'user_id' => auth()->id(),
        'status' => 'Open',
    ]);

    return redirect()->back()->with('success', 'Ticket created successfully.');
})->middleware(['auth', 'verified', 'role:admin'])->name('tickets.store');

Route::get('supervisor/dashboard', function () {
    return Inertia::render('SupervisorDashboard');
})->middleware(['auth', 'verified', 'role:supervisor'])->name('supervisor.dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
