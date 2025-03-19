<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action\Password;

use Exception;
use IBRExplorer\Api\Action\Action;
use IBRExplorer\Api\Enum\StatusCode;
use IBRExplorer\Database\MySql;
use Psr\Http\Message\ResponseInterface as Response;

class PasswordChangeAction extends Action {

    protected function run(): Response {
        $password = (string)($this->body['password'] ?? '');

        if (empty($password)) {
            return $this->respond(['password' => 'Campo obrigatório.'], StatusCode::InvalidEntity);
        }

        try {
            $user = MySql::$instance->getUser();

            if (empty($user) || ($user->id === 1)) {
                return $this->respond('Usuário não autenticado.', StatusCode::Forbidden);
            }

            $passwordPeppered = hash_hmac('sha1', $password, PASSWORD_PEPPER);
            $passwordHash = password_hash($passwordPeppered, PASSWORD_DEFAULT);

            if (!MySql::$instance->updateRow('user', ['password' => $passwordHash], $user->id)) {
                return $this->respond(
                    'Não foi possível redefinir a senha, por favor tente novamente.',
                    StatusCode::InternalServerError
                );
            }
        } catch (Exception) {
            return $this->respond(
                'Ocorreu um erro desconhecido. Favor entrar em contato com o suporte.',
                StatusCode::InternalServerError
            );
        }

        return $this->respond('Senha alterada com sucesso.');
    }
}