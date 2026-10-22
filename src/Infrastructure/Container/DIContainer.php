<?php
declare(strict_types=1);

namespace LessonNotes\Infrastructure\Container;

use InvalidArgumentException;
use LessonNotes\Application\UseCases\GetClassLessonsUseCase;
use LessonNotes\Application\UseCases\GetLessonDetailUseCase;
use LessonNotes\Application\UseCases\ParseAndSaveLessonUseCase;
use LessonNotes\Domain\Repositories\LessonClassRepositoryInterface;
use LessonNotes\Domain\Repositories\LessonRepositoryInterface;
use LessonNotes\Domain\Services\ParserInterface;
use LessonNotes\Domain\Services\RegexBilingualParser;
use LessonNotes\Infrastructure\Persistence\DatabaseConnection;
use LessonNotes\Infrastructure\Persistence\PdoLessonClassRepository;
use LessonNotes\Infrastructure\Persistence\PdoLessonRepository;
use LessonNotes\Infrastructure\Security\AuthGuard;
use PDO;

/**
 * Dependency Injection Container for Hexagonal Architecture.
 * Resolves application ports, use cases, and infrastructure adapters without static state.
 */
class DIContainer
{
    /**
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * @var array<string, callable>
     */
    private array $factories = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->registerDefaults($config);
    }

    /**
     * Register default service definitions.
     *
     * @param array<string, mixed> $config
     */
    private function registerDefaults(array $config): void
    {
        // 1. PDO Database Connection
        $this->set(PDO::class, function () use ($config): PDO {
            return DatabaseConnection::create($config);
        });

        // 2. Repositories
        $this->set(LessonClassRepositoryInterface::class, function (): LessonClassRepositoryInterface {
            return new PdoLessonClassRepository($this->getDb());
        });

        $this->set(LessonRepositoryInterface::class, function (): LessonRepositoryInterface {
            return new PdoLessonRepository($this->getDb());
        });

        // 3. Domain Services
        $this->set(ParserInterface::class, function (): ParserInterface {
            return new RegexBilingualParser();
        });

        // 4. Security Guards
        $this->set(AuthGuard::class, function (): AuthGuard {
            return new AuthGuard($this->getDb());
        });

        // 5. Use Cases
        $this->set(ParseAndSaveLessonUseCase::class, function (): ParseAndSaveLessonUseCase {
            return new ParseAndSaveLessonUseCase(
                $this->getLessonClassRepository(),
                $this->getLessonRepository(),
                $this->getParser()
            );
        });

        $this->set(GetClassLessonsUseCase::class, function (): GetClassLessonsUseCase {
            return new GetClassLessonsUseCase(
                $this->getLessonClassRepository(),
                $this->getLessonRepository()
            );
        });

        $this->set(GetLessonDetailUseCase::class, function (): GetLessonDetailUseCase {
            return new GetLessonDetailUseCase(
                $this->getLessonRepository(),
                $this->getLessonClassRepository()
            );
        });
    }

    /**
     * Register a factory definition.
     */
    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    /**
     * Register an existing object instance.
     */
    public function setInstance(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    /**
     * Retrieve a resolved service instance.
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->factories[$id])) {
            $instance = ($this->factories[$id])($this);
            $this->instances[$id] = $instance;
            return $instance;
        }

        throw new InvalidArgumentException("Service '{$id}' is not defined in the DIContainer.");
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]);
    }

    // --- Type-safe convenience accessors ---

    public function getDb(): PDO
    {
        /** @var PDO */
        return $this->get(PDO::class);
    }

    public function getLessonClassRepository(): LessonClassRepositoryInterface
    {
        /** @var LessonClassRepositoryInterface */
        return $this->get(LessonClassRepositoryInterface::class);
    }

    public function getLessonRepository(): LessonRepositoryInterface
    {
        /** @var LessonRepositoryInterface */
        return $this->get(LessonRepositoryInterface::class);
    }

    public function getParser(): ParserInterface
    {
        /** @var ParserInterface */
        return $this->get(ParserInterface::class);
    }

    public function getAuthGuard(): AuthGuard
    {
        /** @var AuthGuard */
        return $this->get(AuthGuard::class);
    }

    public function getParseAndSaveLessonUseCase(): ParseAndSaveLessonUseCase
    {
        /** @var ParseAndSaveLessonUseCase */
        return $this->get(ParseAndSaveLessonUseCase::class);
    }

    public function getGetClassLessonsUseCase(): GetClassLessonsUseCase
    {
        /** @var GetClassLessonsUseCase */
        return $this->get(GetClassLessonsUseCase::class);
    }

    public function getGetLessonDetailUseCase(): GetLessonDetailUseCase
    {
        /** @var GetLessonDetailUseCase */
        return $this->get(GetLessonDetailUseCase::class);
    }
}
