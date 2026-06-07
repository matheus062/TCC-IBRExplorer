<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action\Password;

use Exception;
use Firebase\JWT\JWT;
use IBRExplorer\Api\Action\Action;
use IBRExplorer\Api\Enum\StatusCode;
use IBRExplorer\Api\IBRExplorerApi;
use IBRExplorer\Entity\Enum\System\EntityStatus;
use IBRExplorer\Entity\User\User;
use IBRExplorer\Repository\User\UserRepository;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Http\Message\ResponseInterface as Response;

class PasswordForgotAction extends Action {

    private UserRepository $userRepository;

    public function __construct() {
        /** @var UserRepository $repository */
        $repository = IBRExplorerApi::getInstance()->getEntityRepository(User::class);
        $this->userRepository = $repository;
    }

    protected function run(): Response {
        $email = (string)($this->body['email'] ?? '');

        if (empty($email)) {
            return $this->respond(['email' => 'Campo obrigatório.'], StatusCode::InvalidEntity);
        }

        try {
            $user = $this->userRepository->readByEmail($email, [
                'name',
                'entityStatus',
                'email',
            ]);
        } catch (Exception) {
            return $this->respond(
                'Ocorreu um erro desconhecido ao buscar o usuário. Por favor, entre em contato com o suporte.',
                StatusCode::InternalServerError
            );
        }

        if ($user === false) {
            return $this->respond(
                'Email de redefinição de senha enviado com sucesso.',
                StatusCode::Created
            );
        } elseif (($user->entityStatus) !== EntityStatus::Active) {
            return $this->respond(
                'Usuário não ativo, favor entrar em contato com o suporte.',
                StatusCode::Forbidden
            );
        }

        $payload = [
            'iss' => TOKEN_ISSUER,
            'iat' => time(),
            'exp' => strtotime('+15 minutes'),
            'uid' => $user->id,
        ];
        $token = JWT::encode($payload, TOKEN_KEY, 'HS256');

//        if (DEBUG) {
//            return $this->respond(
//                ['token' => $token],
//                StatusCode::Created
//            );
//        } else

        if (!$this->sendResetPasswordEmail($user, $token)) {
            return $this->respond(
                'Ocorreu um erro ao enviar o email de redefinição de senha, favor tentar novamente mais tarde.',
                StatusCode::InternalServerError
            );
        }

        return $this->respond(
            'Email de redefinição de senha enviado com sucesso.',
            StatusCode::Created
        );
    }

    private function sendResetPasswordEmail(User $user, string $token): bool {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom(SMTP_USER, 'TCC - IBR Explorer');
            $mail->addAddress($user->email->getValue(), $user->name);

            $mail->isHTML();
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->Subject = 'IBRExplorer — Redefinição de senha';

            $resetLink = APP_EMAIL_URL . 'password-reset?token=' . $token;
            $userName = htmlspecialchars($user->name, ENT_QUOTES, 'UTF-8');

            $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>IBRExplorer — Redefinição de senha</title>
</head>
<body bgcolor="#07141c" style="margin:0;padding:0;background-color:#07141c;font-family:Arial,Helvetica,sans-serif;">

  <table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#07141c" style="background-color:#07141c;">
    <tr>
      <td align="center" style="padding:48px 20px;">

        <table width="560" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;width:100%;">

          <!-- Amber accent bar -->
          <tr>
            <td height="3" bgcolor="#f4bc5f" style="background-color:#f4bc5f;font-size:0;line-height:0;">&nbsp;</td>
          </tr>

          <!-- Card -->
          <tr>
            <td bgcolor="#0a1520" style="background-color:#0a1520;border:1px solid #1a3040;border-top:0;border-radius:0 0 16px 16px;padding:36px;">

              <!-- Eyebrow -->
              <p style="margin:0 0 10px;font-size:11px;letter-spacing:0.18em;text-transform:uppercase;color:#4a6a7e;">IBRExplorer</p>

              <!-- Title -->
              <h1 style="margin:0 0 6px;font-size:24px;font-weight:700;color:#e8f0f5;line-height:1.1;">Redefinição de senha</h1>
              <p style="margin:0 0 24px;font-size:13px;color:#5a7a8e;">Upload, indexação e exploração de capturas PCAP/PCAPNG.</p>

              <!-- Divider -->
              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
                <tr>
                  <td height="1" bgcolor="#1a3040" style="background-color:#1a3040;font-size:0;line-height:0;">&nbsp;</td>
                </tr>
              </table>

              <!-- Greeting -->
              <p style="margin:0 0 6px;font-size:15px;font-weight:700;color:#e8f0f5;">Olá, {$userName}!</p>
              <p style="margin:0 0 24px;font-size:14px;color:#8ca8ba;line-height:1.65;">
                Você solicitou a redefinição de sua senha no IBRExplorer. Clique no botão abaixo para criar uma nova senha.
                Este link é válido por <strong style="color:#e8f0f5;">15 minutos</strong>.
              </p>

              <!-- CTA button -->
              <table cellpadding="0" cellspacing="0" border="0" style="margin-bottom:28px;">
                <tr>
                  <td bgcolor="#f4bc5f" style="background-color:#f4bc5f;border-radius:10px;">
                    <a href="{$resetLink}"
                       style="display:inline-block;padding:14px 30px;font-size:14px;font-weight:700;color:#1d1912;text-decoration:none;border-radius:10px;letter-spacing:0.02em;">
                      Redefinir senha
                    </a>
                  </td>
                </tr>
              </table>

              <!-- Fallback link -->
              <p style="margin:0 0 6px;font-size:12px;color:#4a6a7e;">Se o botão não funcionar, copie e cole o endereço abaixo no navegador:</p>
              <p style="margin:0;font-size:11px;word-break:break-all;color:#5a7a8e;">{$resetLink}</p>

              <!-- Footer divider -->
              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 20px;">
                <tr>
                  <td height="1" bgcolor="#1a3040" style="background-color:#1a3040;font-size:0;line-height:0;">&nbsp;</td>
                </tr>
              </table>

              <!-- Disclaimer -->
              <p style="margin:0;font-size:12px;color:#4a6a7e;line-height:1.55;">
                Se você não solicitou esta alteração, ignore este e-mail. Nenhuma ação é necessária e sua senha permanece a mesma.
              </p>

            </td>
          </tr>

          <!-- Bottom brand -->
          <tr>
            <td align="center" style="padding:20px 0 0;">
              <p style="margin:0;font-size:11px;letter-spacing:0.2em;text-transform:uppercase;color:#2a4a5e;">IBRExplorer</p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>

</body>
</html>
HTML;

            $mail->AltBody = "Olá, {$user->name}!\n\n"
                . "Você solicitou a redefinição de sua senha no IBRExplorer.\n"
                . "Acesse o link abaixo para criar uma nova senha (válido por 15 minutos):\n\n"
                . $resetLink . "\n\n"
                . "Se você não solicitou esta alteração, ignore este e-mail.";

            return $mail->send();
        } catch (Exception) {
            return false;
        }
    }

}

