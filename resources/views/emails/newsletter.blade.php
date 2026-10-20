<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $titre }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="background-color:#2C9CF2; padding:20px; text-align:center;">
                            <span style="color:#ffffff; font-size:20px; font-weight:bold;">Septentrional</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">
                            <p style="font-size:14px; color:#555555; margin-top:0;">Bonjour {{ $abonneNom ?: '' }},</p>
                            <h1 style="font-size:20px; color:#1b1b1b;">{{ $titre }}</h1>
                            <div style="font-size:15px; line-height:1.6; color:#333333;">
                                {!! $contenu !!}
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 24px; background-color:#fafafa; border-top:1px solid #eeeeee; text-align:center;">
                            <a href="{{ $desabonnementUrl }}" style="font-size:12px; color:#888888;">Se désabonner de la newsletter</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
