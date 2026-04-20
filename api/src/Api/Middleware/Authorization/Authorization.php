<?php

declare(strict_types=1);

namespace IBRExplorer\Api\Middleware\Authorization;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use IBRExplorer\Api\Enum\StatusCode;
use IBRExplorer\Api\Trait\RouteRespondTrait;
use IBRExplorer\Database\PostgreSQL;
use IBRExplorer\Entity\Enum\System\EntityStatus;
use IBRExplorer\Entity\Enum\User\UserRoleType;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Throwable;

class Authorization implements MiddlewareInterface {

    use RouteRespondTrait;

    public static array $openRoutes = [
        '/auth',
        '/password/forgot'
    ];

    public function process(Request $request, RequestHandler $handler): Response {
        if ($request->getMethod() === 'OPTIONS') {
            return $handler->handle($request);
        } elseif ($request->hasHeader('Authorization')) {
            return $this->processToken($request, $handler);
        } elseif (!in_array($request->getUri()->getPath(), self::$openRoutes)) {
            return $this->respond('Token de autenticação não enviado.', StatusCode::Unauthorized);
        }

        return $handler->handle($request);
    }

    private function processToken(Request $request, RequestHandler $handler): Response {
        try {
            $token = $this->getToken($request);
            $decode = JWT::decode($token, new Key(TOKEN_KEY, 'HS256'));

            if (empty($decode->uid)) {
                return $this->respond('Token de autenticação inválido.', StatusCode::Unauthorized);
            }

            PostgreSQL::$instance->initUser($decode->uid);
            $user = PostgreSQL::$instance->getUser();

            if (empty($user)) {
                return $this->respond('Ocorreu um erro ao iniciar banco de dados.', StatusCode::InternalServerError);
            } elseif ($user->entityStatus !== EntityStatus::Active) {
                return $this->respond('Usuário não ativo, favor entrar em contato com o suporte.', StatusCode::Unauthorized);
            } elseif ($user->checkUserHasRole(UserRoleType::System)) {
                return $this->respond('Usuários do tipo `System` não são autorizados a fazer login.', StatusCode::Unauthorized);
            }
        } catch (ExpiredException) {
            return $this->respond('Token de autenticação expirado.', StatusCode::Unauthorized);
        } catch (Throwable) {
            return $this->respond(
                'Não foi possível processar o token de autenticação.',
                StatusCode::InternalServerError
            );
        }

        return $handler->handle($request);
    }

    private function getToken(Request $request): string {
        $headerLine = $request->getHeaderLine('Authorization');

        return trim(str_replace('Bearer', '', $headerLine));
    }

}