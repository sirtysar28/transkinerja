<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;

class NotifikasiController extends Controller
{
    public function index()
    {
        $notifikasi = AppNotification::where('user_id', auth()->id())
            ->orderByDesc('created_at')->paginate(10)->withQueryString();

        return view('notifikasi.index', ['notifikasi' => $notifikasi]);
    }

    public function baca(AppNotification $notifikasi)
    {
        abort_unless($notifikasi->user_id === auth()->id(), 404);
        $notifikasi->update(['dibaca_at' => now()]);

        return redirect($notifikasi->tautan ?: route('notifikasi.index'));
    }

    public function bacaSemua()
    {
        AppNotification::where('user_id', auth()->id())
            ->whereNull('dibaca_at')
            ->update(['dibaca_at' => now()]);

        return back()->with('sukses', 'Semua notifikasi ditandai terbaca.');
    }
}
