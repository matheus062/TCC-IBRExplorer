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

class ProfileImageUpdateAction extends Action {

    private const array ALLOWED_EXTENSIONS = ['jpeg', 'jpg', 'png', 'webp'];

    protected function run(): Response {
        $data = (string)($this->body['data'] ?? '');
        $ext = strtolower((string)($this->body['ext'] ?? ''));

        if ($ext === 'jpg') {
            $ext = 'jpeg';
        }

        if (empty($data) || empty($ext)) {
            return $this->respond([
                'profileImage' => 'Imagem e extensao sao obrigatorias.',
            ], StatusCode::InvalidEntity);
        } elseif (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return $this->respond([
                'profileImage' => 'Formato invalido. Use JPEG, PNG ou WEBP.',
            ], StatusCode::InvalidEntity);
        }

        if (str_contains($data, ',')) {
            $data = substr($data, strpos($data, ',') + 1);
        }

        if (base64_decode($data, true) === false) {
            return $this->respond([
                'profileImage' => 'Dados da imagem invalidos.',
            ], StatusCode::InvalidEntity);
        }

        try {
            $currentUser = PostgreSQL::$instance->getUser();

            if (empty($currentUser) || ($currentUser->id === 1)) {
                return $this->respond('Usuario nao autenticado.', StatusCode::Forbidden);
            }

            /** @var UserRepository $repository */
            $repository = IBRExplorerApi::getInstance()->getEntityRepository(User::class);
            $user = new User([
                'id' => $currentUser->id,
                'profileImage' => [
                    'name' => 'profile_' . $currentUser->id,
                    'altName' => (string)($this->body['name'] ?? 'Foto de perfil'),
                    'ext' => $ext,
                    'data' => $data,
                    's3Store' => false,
                ],
            ]);

            $repository->save($user);

            $profile = $repository->read($currentUser->id, [
                'name',
                'email',
                'profileImage',
                'roles' => ['type'],
            ], true);

            if ($profile === false) {
                return $this->respond('Perfil nao localizado.', StatusCode::NotFound);
            }
        } catch (Exception) {
            return $this->respond(
                'Ocorreu um erro desconhecido ao atualizar a foto de perfil.',
                StatusCode::InternalServerError
            );
        }

        return $this->respond([
            'message' => 'Foto de perfil atualizada com sucesso.',
            'user' => $profile,
        ]);
    }
}
