{{-- Email notifikasi TransKinerja — HTML inline-style (aman untuk semua klien email) --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta http-equiv="X-Mailer" content="TransKinerja"/>
    <title>[TransKinerja] {{ $judul }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 4px 18px rgba(7,29,65,0.10);">

                {{-- Kop --}}
                <tr>
                    <td style="background-color:#081C26;padding:22px 28px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td width="52" style="vertical-align:middle;padding-right:14px;">
                                    <img src="{{ asset('images/logo.png') }}" alt="Logo Kementerian Transmigrasi" width="48" height="48" style="display:block;border-radius:8px;"/>
                                </td>
                                <td style="vertical-align:middle;">
                                    <p style="margin:0;font-size:15px;font-weight:bold;color:#ffffff;letter-spacing:0.4px;">TRANSKINERJA</p>
                                    <p style="margin:2px 0 0;font-size:11px;color:#c7d4ee;">Kementerian Transmigrasi Republik Indonesia</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Isi --}}
                <tr>
                    <td style="padding:30px 28px 10px;">
                        <h2 style="margin:0 0 14px;font-size:18px;color:#0E2A37;">{{ $judul }}</h2>
                        <p style="margin:0;font-size:14px;line-height:1.7;color:#334155;">
                            {{ $pesan }}
                        </p>
                        @if ($tautan)
                            <p style="margin:26px 0 8px;">
                                <a href="{{ $tautan }}" style="display:inline-block;background-color:#0E2A37;color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;padding:12px 26px;border-radius:9px;">
                                    Buka Aplikasi TransKinerja
                                </a>
                            </p>
                            <p style="margin:0 0 6px;font-size:11px;color:#94a3b8;word-break:break-all;">Atau salin tautan: {{ $tautan }}</p>
                        @endif
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="padding:22px 28px 26px;">
                        <div style="border-top:1px solid #e2e8f0;padding-top:16px;">
                            <p style="margin:0;font-size:12px;color:#64748b;line-height:1.6;">
                                @if ($penerima) Halo <b>{{ $penerima->name }}</b>, Anda menerima email ini karena terdaftar pada aplikasi TransKinerja. @endif
                                Email ini dikirim otomatis — tidak perlu dibalas.
                            </p>
                            <p style="margin:8px 0 0;font-size:11px;color:#94a3b8;">
                                {{ now()->translatedFormat('d F Y, H:i') }} WIB &bull; TransKinerja &bull; Kementerian Transmigrasi RI
                            </p>
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
