<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Réinitialisation de mot de passe</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="background-color:#2C9CF2; padding:20px; text-align:center;">
                            <span style="color:#ffffff; font-size:20px; font-weight:bold;">Septentrional — Administration</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">
                            <p style="font-size:14px; color:#555555; margin-top:0;">Bonjour {{ $userName }},</p>
                            <p style="font-size:15px; line-height:1.6; color:#333333;">
                                Une demande de réinitialisation de mot de passe a été effectuée pour votre compte.
                                Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe. Ce lien expire dans 60 minutes.
                            </p>
                            <p style="text-align:center; margin:32px 0;">
                                <a href="{{ $resetUrl }}" style="background-color:#2C9CF2; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:bold;">
                                    Réinitialiser mon mot de passe
                                </a>
                            </p>
                            <p style="font-size:13px; color:#888888;">
                                Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email : votre mot de passe restera inchangé.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
