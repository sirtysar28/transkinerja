<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class LogLampiran extends Model
{
    protected $fillable = ['log_kegiatan_id', 'nama_asli', 'path', 'mime', 'ukuran'];

    public function logKegiatan()
    {
        return $this->belongsTo(LogKegiatan::class);
    }

    public function url(): string
    {
        return route('logbook.lampiran.download', $this->id);
    }

    protected static function booted(): void
    {
        static::deleting(function (self $lampiran) {
            Storage::disk('local')->delete($lampiran->path);
        });
    }
}
