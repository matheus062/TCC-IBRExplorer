<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Middleware\Permission;

use IBRExplorer\Api\Enum\ActionMethod;
use IBRExplorer\Api\Enum\StatusCode;
use IBRExplorer\Api\Trait\RouteRespondTrait;
use IBRExplorer\Database\MySql;
use IBRExplorer\Entity\Enum\User\UserRoleType;
use IBRExplorer\Entity\User\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

abstract class Permission implements MiddlewareInterface {

    use RouteRespondTrait;

    protected Request $request;

    public function process(Request $request, RequestHandlerInterface $handler): ResponseInterface {
        $this->request = $request;

        if (($response = $this->checkUserPermissions()) !== true) {
            return $response;
        }

        return $handler->handle($this->request);
    }

    private function checkUserPermissions(): true|ResponseInterface {
        $currentUser = MySql::$instance->getUser();

        if (empty($currentUser)) {
            return $this->respond('Usuário não autenticado. Por favor, refaça o login.', StatusCode::Unauthorized);
        } elseif (!$this->isUserRoleGranted($currentUser)) {
            return $this->respond('Usuário não autorizado para acessar recurso.', StatusCode::Forbidden);
        }

        return true;
    }

    /**
     * @param User $user
     * @return bool
     */
    private function isUserRoleGranted(User $user): bool {
        $granted = false;
        $method = ActionMethod::tryFrom(strtolower($this->request->getMethod()));

        if (empty($method)) {
            throw new RuntimeException('Método de requisição não suportado.');
        }

        $allowedTypes = match ($method) {
            ActionMethod::Get => $this->getAllowedTypes(),
            ActionMethod::Post => $this->postAllowedTypes(),
            ActionMethod::Put => $this->putAllowedTypes(),
            ActionMethod::Delete => $this->deleteAllowedTypes(),
            default => throw new RuntimeException('Método de requisição não suportado.'),
        };

        foreach ($allowedTypes as $type) {
            if ($user->checkUserHasRole($type)) {
                $granted = true;
                break;
            }
        }

        return $granted;
    }

    /**
     * @return UserRoleType[]
     */
    abstract protected function getAllowedTypes(): array;

    abstract protected function postAllowedTypes(): array;

    abstract protected function putAllowedTypes(): array;

    abstract protected function deleteAllowedTypes(): array;

}