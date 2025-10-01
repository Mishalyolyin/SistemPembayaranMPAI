<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KalenderEvent;
use Illuminate\Http\Request;

class KalenderEventController extends Controller
{
    public function index()
    {
        $events = KalenderEvent::orderBy('tanggal')->get();
        return view('admin.kalender.index', compact('events'));
    }

    public function create()
    {
        return view('admin.kalender.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'judul_event' => 'required|string',
            'tanggal'     => 'required|date',
            'untuk'       => 'required|in:rpl,reguler,all',
        ]);

        KalenderEvent::create($data);
        return redirect()->route('admin.kalender.index')
                         ->with('success', 'Event berhasil ditambahkan.');
    }

    public function edit(KalenderEvent $kalender)
    {
        return view('admin.kalender.edit', compact('kalender'));
    }

    public function update(Request $request, KalenderEvent $kalender)
    {
        $data = $request->validate([
            'judul_event' => 'required|string',
            'tanggal'     => 'required|date',
            'untuk'       => 'required|in:rpl,reguler,all',
        ]);

        $kalender->update($data);
        return redirect()->route('admin.kalender.index')
                         ->with('success', 'Event berhasil diupdate.');
    }

    public function destroy(KalenderEvent $kalender)
    {
        $kalender->delete();
        return back()->with('success', 'Event berhasil dihapus.');
    }
}
