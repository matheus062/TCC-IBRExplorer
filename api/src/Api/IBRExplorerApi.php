<?php

declare(strict_types=1);

namespace IBRExplorer\Api;

use DI\Container;
use Exception;
use IBRExplorer\Api\Action\Authorization\CreateAuthTokenAction;
use IBRExplorer\Api\Action\Entity\EntityCreateAction;
use IBRExplorer\Api\Action\Entity\EntityListAction;
use IBRExplorer\Api\Action\Entity\EntityReadAction;
use IBRExplorer\Api\Action\Entity\EntityUpdateAction;
use IBRExplorer\Api\Action\Password\PasswordChangeAction;
use IBRExplorer\Api\Action\Password\PasswordForgotAction;
use IBRExplorer\Api\Enum\ActionMethod;
use IBRExplorer\Api\Middleware\Authorization\Authorization;
use IBRExplorer\Api\Middleware\ErrorHandler\ErrorHandler;
use IBRExplorer\Api\Middleware\Permission\UsersPermission;
use IBRExplorer\Database\MySql;
use IBRExplorer\Database\RepositoryConfig;
use IBRExplorer\Entity\Address\Address;
use IBRExplorer\Entity\Address\City;
use IBRExplorer\Entity\Address\Country;
use IBRExplorer\Entity\Address\State;
use IBRExplorer\Entity\Entity;
use IBRExplorer\Entity\User\User;
use IBRExplorer\Repository\Address\AddressRepository;
use IBRExplorer\Repository\EntityRepository;
use IBRExplorer\Repository\User\UserRepository;
use IBRExplorer\Service\Address\CityService;
use IBRExplorer\Service\Address\CountryService;
use IBRExplorer\Service\EntityService;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use Tuupola\Middleware\CorsMiddleware;

class IBRExplorerApi {

    private static ?IBRExplorerApi $instance;

    private App $app;
    private Container $container;

    public static function getInstance(): IBRExplorerApi {
        if (!isset(self::$instance)) {
            self::$instance = new IBRExplorerApi();
            self::$instance->prepareApp();
            self::$instance->setRoutes();
            self::$instance->startDbConnection();
        }

        return self::$instance;
    }

    private function prepareApp(): void {
        $this->container = new Container();
        $this->app = AppFactory::create(container: $this->container);

        $this->app->add(Authorization::class);
        $this->app->add(new CorsMiddleware([
            'origin' => ['*'],
            'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            'headers.allow' => ['Accept', 'Authorization', 'Content-Type'],
        ]));
        $this->app->add(ErrorHandler::class);
    }

    private function setRoutes(): void {
        $this->app->options('/{routes:.+}', function ($request, $response) {
            return $response
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'Accept, Authorization, Content-Type');
        });

        $this->app->post('/auth', CreateAuthTokenAction::class);

        $this->app->group('/password', function (RouteCollectorProxy $password) {
            $password->post('/forgot', PasswordForgotAction::class);
            $password->put('/change', PasswordChangeAction::class);
        });

        $this->setAddressRoutes();
        $this->setUsersRoutes();
    }

    private function setAddressRoutes(): void {
        $this->entityCrudRoute('/country', Country::class, null, null);
        $this->entityCrudRoute('/state', State::class, null, null);
        $this->entityCrudRoute('/city', City::class, null, null);
    }

    private function entityCrudRoute(
        string  $endpoint,
        string  $entityClass,
        ?string $entityCreateAction = EntityCreateAction::class,
        ?string $entityUpdateAction = EntityUpdateAction::class,
        ?string $entityReadAction = EntityReadAction::class,
        ?string $entityListAction = EntityListAction::class,
        ?string $permissionMiddleware = null
    ): void {
        $arguments = ['entityClass' => $entityClass];

        if (!empty($entityListAction)) {
            $this->setEndpoint(
                ActionMethod::Get,
                $endpoint,
                $entityListAction,
                $arguments,
                $permissionMiddleware
            );
        }


        if (!empty($entityCreateAction)) {
            $this->setEndpoint(
                ActionMethod::Post,
                $endpoint,
                $entityCreateAction,
                $arguments,
                $permissionMiddleware
            );
        }

        if (!empty($entityUpdateAction)) {
            $this->setEndpoint(
                ActionMethod::Put,
                $endpoint . '/{id}',
                $entityUpdateAction,
                $arguments,
                $permissionMiddleware
            );
        }

        if (!empty($entityReadAction)) {
            $this->setEndpoint(
                ActionMethod::Get,
                $endpoint . '/{id}',
                $entityReadAction,
                $arguments,
                $permissionMiddleware
            );
        }
    }

    private function setEndpoint(
        ActionMethod $method,
        string       $pattern,
        string       $action,
        ?array       $arguments = null,
        ?string      $permissionMiddleware = null
    ): void {
        $endpoint = $this->app->{$method->value}($pattern, $action);

        if (!empty($arguments)) {
            $endpoint->setArguments($arguments);
        }

        if (!empty($permissionMiddleware)) {
            $endpoint->add($permissionMiddleware);
        }
    }

    private function setUsersRoutes(): void {
        $this->entityCrudRoute(
            '/user',
            User::class,
            permissionMiddleware: UsersPermission::class
        );
    }

    private function startDbConnection(): void {
        $repositoryConfig = new RepositoryConfig(
            MYSQL_HOST,
            MYSQL_PORT,
            MYSQL_USER,
            MYSQL_PASSWORD,
            MYSQL_DATABASE,
            __DIR__ . '/../src/Database/Structure/Database/',
            __DIR__ . '/../files/'
        );
        $mysql = new MySql($repositoryConfig);
        $mysql->initDatabase();
        /** @noinspection PhpUnhandledExceptionInspection */
        $mysql->initUser(1);
    }

    /**
     * @throws Exception
     */
    public function run(): void {
        $this->app->run();
    }

    public function getEntityService(Entity|string $entity): EntityService {
        $entityClassName = is_string($entity) ? $entity : $entity::class;
        $key = $entityClassName . '::Service';

        if ($this->container->has($key)) {
            /** @noinspection PhpUnhandledExceptionInspection */
            return $this->container->get($key);
        }

        $service = match ($entityClassName) {
            Country::class => new CountryService(),
            City::class => new CityService(),
            default => new EntityService($entityClassName)
        };
        $this->container->set($key, $service);

        return $service;
    }

    public function getEntityRepository(Entity|string $entity): EntityRepository {
        $entityClassName = is_string($entity) ? $entity : get_class($entity);
        $key = $entityClassName . '::Repository';

        if ($this->container->has($key)) {
            /** @noinspection PhpUnhandledExceptionInspection */
            return $this->container->get($key);
        }

        $repository = match ($entityClassName) {
            Address::class => new AddressRepository(),
            User::class => new UserRepository(),
            default => new EntityRepository($entityClassName)
        };
        $this->container->set($key, $repository);

        return $repository;
    }

}