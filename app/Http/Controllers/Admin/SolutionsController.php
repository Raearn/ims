<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Inertia\Inertia;
use Inertia\Response;

class SolutionsController extends Controller
{
    /**
     * Display a listing of all tags with their associated solutions from tickets.
     */
    public function index(): Response
    {
        $tags = Tag::with(['tickets' => function ($query) {
            $query->select('tickets.id', 'title', 'solution')
                ->whereNotNull('solution')
                ->where('solution', '!=', '')
                ->with('tags');
        }])
            ->orderBy('name')
            ->get()
            ->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'solutions' => $tag->tickets->map(function ($ticket) {
                        return [
                            'ticket_id' => $ticket->id,
                            'ticket_title' => $ticket->title,
                            'solution' => $ticket->solution,
                            'tags' => $ticket->tags->pluck('name')->toArray(),
                        ];
                    }),
                ];
            })
            ->filter(function ($tag) {
                return count($tag['solutions']) > 0;
            })
            ->values();

        return Inertia::render('Solutions', [
            'tags' => $tags,
        ]);
    }
}
