<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Action\Profile;

use Exception;
use IBRExplorer\Api\Action\Action;
use IBRExplorer\Api\Enum\StatusCode;
use IBRExplorer\Api\IBRExplorerApi;
use IBRExplorer\Database\PostgreSQL;
use IBRExplorer\Entity\User\User;
use IBRExplorer\Repository\User\UserRepository;
use Psr\Http\Message\ResponseInterface as Response;

class ProfileReadAction extends Action {

    protected function run(): Response {
        try {
            $currentUser = PostgreSQL::$instance->getUser();

            if (empty($currentUser) || ($currentUser->id === 1)) {
                return $this->respond('Usuario nao autenticado.', StatusCode::Forbidden);
            }

            /** @var UserRepository $repository */
            $repository = IBRExplorerApi::getInstance()->getEntityRepository(User::class);
            $user = $repository->read($currentUser->id, [
                'name',
                'email',
                'profileImage',
                'roles' => ['type'],
            ], true);

            if ($user === false) {
                return $this->respond('Perfil nao localizado.', StatusCode::NotFound);
            }
        } catch (Exception) {
            return $this->respond(
                'Ocorreu um erro desconhecido ao buscar o perfil.',
                StatusCode::InternalServerError
            );
        }

        return $this->respond($user);
    }
}
