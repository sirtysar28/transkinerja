{{-- Email kode OTP TransKinerja — HTML inline-style (aman untuk semua klien email) --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta http-equiv="X-Mailer" content="TransKinerja"/>
    <title>[TransKinerja] Kode OTP {{ $kode }}</title>
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
                        <h2 style="margin:0 0 8px;font-size:18px;color:#0E2A37;">Kode Verifikasi OTP</h2>
                        <p style="margin:0 0 18px;font-size:14px;line-height:1.7;color:#334155;">
                            Halo <b>{{ $penerima->name }}</b>, seseorang (kemungkinan Anda) baru saja berhasil memasukkan
                            username/NIP dan password yang benar pada aplikasi TransKinerja. Masukkan kode berikut untuk
                            menyelesaikan proses masuk:
                        </p>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td align="center" style="padding:18px 12px;background-color:#0E2A37;border-radius:12px;">
                                    <span style="font-family:'Courier New',monospace;font-size:38px;font-weight:bold;letter-spacing:12px;color:#ffffff;">{{ $kode }}</span>
                                </td>
                            </tr>
                        </table>
                        <p style="margin:16px 0 0;font-size:13px;line-height:1.7;color:#334155;">
                            Kode berlaku <b>{{ $menit }} menit</b> (hingga pukul {{ $kedaluwarsa->timezone(config('app.timezone'))->translatedFormat('H:i') }})
                            dan hanya dapat digunakan sekali.
                        </p>
                        <p style="margin:12px 0 0;padding:12px 16px;background-color:#fef3c7;border:1px solid #fde68a;border-radius:10px;font-size:12px;line-height:1.6;color:#92400e;">
                            <b>Keamanan:</b> Jangan bagikan kode ini kepada siapa pun, termasuk petugas SDM/IT.
                            Aplikasi tidak pernah meminta kode OTP melalui telepon, WhatsApp, atau pesan lain.
                            Bila bukan Anda yang mencoba masuk, segera ubah password Anda dan hubungi administrator.
                        </p>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="padding:22px 28px 26px;">
                        <div style="border-top:1px solid #e2e8f0;padding-top:16px;">
                            <p style="margin:0;font-size:12px;color:#64748b;line-height:1.6;">
                                Email ini dikirim otomatis karena verifikasi OTP diaktifkan administrator — tidak perlu dibalas.
                            </p>
                            <p style="margin:8px 0 0;font-size:11px;color:#94a3b8;">
                                {{ now()->translatedFormat('d F Y, H:i') }} &bull; TransKinerja &bull; Kementerian Transmigrasi RI
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
