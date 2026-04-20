<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action\Authorization;

use Exception;
use Firebase\JWT\JWT;
use IBRExplorer\Api\Action\Action;
use IBRExplorer\Api\Enum\StatusCode;
use IBRExplorer\Api\IBRExplorerApi;
use IBRExplorer\Database\PostgreSQL;
use IBRExplorer\Entity\Enum\System\EntityStatus;
use IBRExplorer\Entity\User\User;
use IBRExplorer\Repository\User\UserRepository;
use Psr\Http\Message\ResponseInterface as Response;

class CreateAuthTokenAction extends Action {

    private UserRepository $userRepository;

    public function __construct() {
        /** @var UserRepository $repository */
        $repository = IBRExplorerApi::getInstance()->getEntityRepository(User::class);
        $this->userRepository = $repository;
    }

    protected function run(): Response {
        $email = (string)($this->body['email'] ?? '');
        $password = (string)($this->body['password'] ?? '');

        if (empty($email) || empty($password)) {
            return $this->respond(
                [
                    'email' => 'Campo obrigatório.',
                    'password' => 'Campo obrigatório.'
                ],
                StatusCode::InvalidEntity
            );
        }

        try {
            $user = $this->userRepository->readByEmail($email, [
                'name',
                'entityStatus',
                'email',
                'roles' => ['type']
            ]);
        } catch (Exception) {
            return $this->respond(
                'Ocorreu um erro desconhecido ao buscar o usuário. Por favor, entre em contato com o suporte.',
                StatusCode::InternalServerError
            );
        }

        if (($user === false) || !$this->validatePassword($email, $password)) {
            return $this->respond(
                'Credenciais inválidas. Por favor, tente novamente.',
                StatusCode::Unauthorized
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
            'exp' => strtotime('+10 hours'),
            'uid' => $user->id,
        ];

        return $this->respond([
            'token' => JWT::encode($payload, TOKEN_KEY, 'HS256'),
            'user' => $user
        ], StatusCode::Created);
    }

    private function validatePassword(string $email, string $passwordSent): bool {
        try {
            $passwordHash = PostgreSQL::$instance->column('user', 'password', ['email' => $email]);

            if (empty($passwordHash)) {
                return false;
            }

            $passwordPeppered = hash_hmac('sha1', $passwordSent, PASSWORD_PEPPER);
        } catch (Exception) {
            return false;
        }

        return password_verify($passwordPeppered, $passwordHash);
    }

}