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
            $mail->Subject = 'IBR Explorer - Redefinição de senha';
            // TODO: Adicionar imagem de logo
            $mail->Body = '
            <body style="margin: 0; padding: 0; overflow: hidden;">
            <div style="font-family: Arial, sans-serif; color: #333; background-color: #f1f1f1; padding: 20px; height: 100vh;">
                <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 8px; text-align: center;">
                    <img style="max-width: 5em; height: auto; margin-top: 1em; margin-bottom: 1em;" src="" alt="Logo" style="max-width: 150px; margin-bottom: 20px;">
                    <h2 style="color: #007bff; font-size: 24px; margin-bottom: 20px;">Olá!</h2>
                    <p style="font-size: 16px; color: #555;">Você solicitou a redefinição de sua senha.</p>
                    <p style="font-size: 16px; color: #555;">Para redefinir sua senha, clique no botão abaixo:</p>
                    <a href="' . APP_EMAIL_URL . 'password-reset?token=' . $token . '" 
                       style="display: inline-block; padding: 12px 24px; margin: 20px 0; color: #ffffff; background-color: #007bff; text-decoration: none; font-weight: bold; border-radius: 4px;">
                       Redefinir Senha
                    </a>
                    <p style="font-size: 14px; color: #777;">Se você não solicitou essa alteração, por favor, ignore este e-mail.</p>
                    <br>
                    <p style="font-size: 16px; color: #333; font-weight: bold;">IBR Explorer</p>
                </div>
            </div>
            </body>            
            ';
            $mail->AltBody = 'Link: ' . APP_EMAIL_URL . 'password-reset?token=' . $token;

            return $mail->send();
        } catch (Exception) {
            return false;
        }
    }

}

