<?php

/*
 * East UX.
 *
 * LICENSE
 *
 * This source file is subject to the 3-Clause BSD license
 * it is available in LICENSE file at the root of this package
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to richard@teknoo.software so we can send you a copy immediately.
 *
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 *
 * @link        https://teknoo.software/east-collection/ux Project website
 *
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */

declare(strict_types=1);

namespace Teknoo\Tests\East\UX\Service;

use ArrayIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Service\ResetInterface;
use Teknoo\East\Common\Contracts\User\UserInterface;
use Teknoo\East\UX\Contracts\Menu\MenuGeneratorInterface;
use Teknoo\East\UX\Contracts\Enum\Menu\Location;
use Teknoo\East\UX\Object\DTO\Menu\MenuItem;
use Teknoo\East\UX\Object\DTO\Menu\MenuSection;
use Teknoo\East\UX\Service\MenuService;
use Teknoo\East\UX\Contracts\Enum\Menu\Sorting;

/**
 * Unit test for the menu service class.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[CoversClass(MenuService::class)]
class MenuServiceTest extends TestCase
{
    /**
     * @param iterable<string> $roles
     */
    private function buildUser(string $identifier, iterable $roles = []): UserInterface
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn($identifier);
        $user->method('getRoles')->willReturn($roles);

        return $user;
    }

    public function testImplementsResetInterface(): void
    {
        $this->assertInstanceOf(ResetInterface::class, new MenuService([]));
    }

    public function testGetMenuForWithoutGenerators(): void
    {
        $service = new MenuService([]);

        $this->assertSame([], $service->getMenuFor($this->buildUser('user1'), Location::TopRight));
    }

    public function testGetSectionCreatesNewSection(): void
    {
        $generator = new class implements MenuGeneratorInterface {
            public ?MenuSection $captured = null;

            public function generate(
                MenuService $menuService,
                Location $location,
                UserInterface $user,
                array $roles,
            ): void {
                $this->captured = $menuService->getSection('my.section', Location::TopRight, 'icon');
            }
        };

        $service = new MenuService([$generator]);
        $service->getMenuFor($this->buildUser('user1'), Location::TopRight);

        $this->assertInstanceOf(MenuSection::class, $generator->captured);
        $this->assertSame('my.section', $generator->captured->name);
        $this->assertSame(1, $generator->captured->position);
        $this->assertSame('icon', $generator->captured->icon);
        $this->assertFalse($generator->captured->rawLabel);
    }

    public function testGetSectionWithRawLabel(): void
    {
        $generator = new class implements MenuGeneratorInterface {
            public ?MenuSection $first = null;
            public ?MenuSection $second = null;

            public function generate(
                MenuService $menuService,
                Location $location,
                UserInterface $user,
                array $roles,
            ): void {
                $this->first = $menuService->getSection(
                    name: (string) $user,
                    location: Location::TopRight,
                    icon: 'icon-user',
                    rawLabel: true,
                );
                // The section already exists: its flags are those of the first call
                $this->second = $menuService->getSection((string) $user, Location::TopRight);
            }
        };

        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('user1');
        $user->method('getRoles')->willReturn([]);
        $user->method('__toString')->willReturn('John Doe');

        $service = new MenuService([$generator]);
        $sections = $service->getMenuFor($user, Location::TopRight);

        $this->assertCount(1, $sections);
        $this->assertSame($generator->first, $sections[0]);
        $this->assertSame($generator->first, $generator->second);
        $this->assertSame('John Doe', $sections[0]->name);
        $this->assertSame('icon-user', $sections[0]->icon);
        $this->assertTrue($sections[0]->rawLabel);
    }

    public function testGetSectionReturnsSameInstanceOnSubsequentCalls(): void
    {
        $generator = new class implements MenuGeneratorInterface {
            public ?MenuSection $first = null;
            public ?MenuSection $second = null;

            public function generate(
                MenuService $menuService,
                Location $location,
                UserInterface $user,
                array $roles,
            ): void {
                $this->first = $menuService->getSection('same.section', Location::TopRight);
                $this->second = $menuService->getSection('same.section', Location::TopRight);
            }
        };

        $service = new MenuService([$generator]);
        $service->getMenuFor($this->buildUser('user1'), Location::TopRight);

        $this->assertSame($generator->first, $generator->second);
    }

    public function testGetSectionAutoIncrementsPosition(): void
    {
        $generator = new class implements MenuGeneratorInterface {
            public ?MenuSection $sectionA = null;
            public ?MenuSection $sectionB = null;

            public function generate(
                MenuService $menuService,
                Location $location,
                UserInterface $user,
                array $roles,
            ): void {
                $this->sectionA = $menuService->getSection('section.a', Location::TopRight);
                $this->sectionB = $menuService->getSection('section.b', Location::TopRight);
            }
        };

        $service = new MenuService([$generator]);
        $service->getMenuFor($this->buildUser('user1'), Location::TopRight);

        $this->assertSame(1, $generator->sectionA?->position);
        $this->assertSame(2, $generator->sectionB?->position);
    }

    public function testGetSectionPositionsAreComputedPerLocation(): void
    {
        $generator = new class implements MenuGeneratorInterface {
            /**
             * @var array<string, MenuSection>
             */
            public array $sections = [];

            public function generate(
                MenuService $menuService,
                Location $location,
                UserInterface $user,
                array $roles,
            ): void {
                $this->sections['left.a'] = $menuService->getSection('left.a', Location::TopLeft);
                $this->sections['right.a'] = $menuService->getSection('right.a', Location::TopRight);
                $this->sections['left.b'] = $menuService->getSection('left.b', Location::TopLeft);
                $this->sections['right.b'] = $menuService->getSection('right.b', Location::TopRight);
                $this->sections['right.c'] = $menuService->getSection('right.c', Location::TopRight);
            }
        };

        $service = new MenuService([$generator]);
        $sections = $service->getMenuFor($this->buildUser('user1'), Location::TopRight);

        // Sections of another location do not shift the positions, and appended sections never share a position
        $this->assertSame(1, $generator->sections['left.a']->position);
        $this->assertSame(2, $generator->sections['left.b']->position);
        $this->assertSame(1, $generator->sections['right.a']->position);
        $this->assertSame(2, $generator->sections['right.b']->position);
        $this->assertSame(3, $generator->sections['right.c']->position);

        $this->assertCount(3, $sections);
        $this->assertSame('right.a', $sections[0]->name);
        $this->assertSame('right.b', $sections[1]->name);
        $this->assertSame('right.c', $sections[2]->name);
    }

    public function testGetSectionPositionsDoNotDependOnGeneratorsOrder(): void
    {
        $generatorA = new class implements MenuGeneratorInterface {
            public function generate(
                MenuService $menuService,
                Location $location,
                UserInterface $user,
                array $roles,
            ): void {
                $menuService->getSection('left.a', Location::TopLeft);
                $menuService->getSection('right.a', Location::TopRight);
            }
        };

        $generatorB = new class implements MenuGeneratorInterface {
            public function generate(
                MenuService $menuService,
                Location $location,
                UserInterface $user,
                array $roles,
            ): void {
                $menuService->getSection('right.b', Location::TopRight);
            }
        };

        $sections = (new MenuService([$generatorA, $generatorB]))
            ->getMenuFor($this->buildUser('user1'), Location::TopRight);

        $this->assertCount(2, $sections);
        $this->assertSame('right.a', $sections[0]->name);
        $this->assertSame(1, $sections[0]->position);
        $this->assertSame('right.b', $sections[1]->name);
        $this->assertSame(2, $sections[1]->position);
    }

    public function testGetSectionPrependSortsBeforeAppend(): void
    {
        $generator = new class implements MenuGeneratorInterface {
            public ?MenuSection $first = null;
            public ?MenuSection $second = null;

            public function generate(
                MenuService $menuService,
                Location $location,
                UserInterface $user,
                array $roles,
            ): void {
                // A section in another location does not change the positions of TopRight: the Append section
                // gets position 1 (> Prepend's 0).
                $menuService->getSection('section.other', Location::TopLeft);
                $this->first = $menuService->getSection('section.first', Location::TopRight);
                $this->second = $menuService->getSection('section.prepended', Location::TopRight, '', Sorting::Prepend);
            }
        };

        $service = new MenuService([$generator]);
        $sections = $service->getMenuFor($this->buildUser('user1'), Location::TopRight);

        $this->assertSame(1, $generator->first?->position);
        $this->assertSame(0, $generator->second?->position);
        $this->assertCount(2, $sections);
        $this->assertSame('section.prepended', $sections[0]->name);
        $this->assertSame('section.first', $sections[1]->name);
    }

    public function testGetSectionAppendAfterPrepend(): void
    {
        $generator = new class implements MenuGeneratorInterface {
            public function generate(
                MenuService $menuService,
                Location $location,
                UserInterface $user,
                array $roles,
            ): void {
                $menuService->getSection('section.prepended', Location::TopRight, '', Sorting::Prepend);
                $menuService->getSection('section.appended', Location::TopRight);
            }
        };

        $service = new MenuService([$generator]);
        $sections = $service->getMenuFor($this->buildUser('user1'), Location::TopRight);

        $this->assertCount(2, $sections);
        $this->assertSame('section.prepended', $sections[0]->name);
        $this->assertSame(0, $sections[0]->position);
        $this->assertSame('section.appended', $sections[1]->name);
        $this->assertSame(2, $sections[1]->position);
    }

    public function testGetMenuForCallsGeneratorsWithClone(): void
    {
        $capturedService = null;
        $capturedUser = null;
        $capturedRoles = null;

        $callback = static function (
            MenuService $s,
            Location $l,
            UserInterface $u,
            array $r,
        ) use (
            &$capturedService,
            &$capturedUser,
            &$capturedRoles,
        ): void {
            $capturedService = $s;
            $capturedUser = $u;
            $capturedRoles = $r;
        };

        $generator = new class ($callback) implements MenuGeneratorInterface {
            public function __construct(private readonly \Closure $callback)
            {
            }

            public function generate(
                MenuService $menuService,
                Location $location,
                UserInterface $user,
                array $roles,
            ): void {
                ($this->callback)($menuService, $location, $user, $roles);
            }
        };

        $user = $this->buildUser('user1', ['ROLE_USER', 'ROLE_ADMIN']);
        $service = new MenuService([$generator]);
        $service->getMenuFor($user, Location::TopRight);

        $this->assertInstanceOf(MenuService::class, $capturedService);
        $this->assertNotSame($service, $capturedService);
        $this->assertSame($user, $capturedUser);
        $this->assertSame(['ROLE_USER', 'ROLE_ADMIN'], $capturedRoles);
    }

    public function testGetMenuForPassesRolesAsAList(): void
    {
        $generator = new class implements MenuGeneratorInterface {
            /**
             * @var array<string>|null
             */
            public ?array $roles = null;

            public function generate(
                MenuService $menuService,
                Location $location,
                UserInterface $user,
                array $roles,
            ): void {
                $this->roles = $roles;
            }
        };

        $user = $this->buildUser('user1', new ArrayIterator(['ROLE_USER', 'ROLE_ADMIN']));
        $service = new MenuService([$generator]);
        $service->getMenuFor($user, Location::TopRight);

        $this->assertSame(['ROLE_USER', 'ROLE_ADMIN'], $generator->roles);
    }

    public function testGetMenuForCachesByUserId(): void
    {
        $callCount = 0;
        $generator = new class ($callCount) implements MenuGeneratorInterface {
            public function __construct(public int &$count)
            {
            }

            public function generate(
                MenuService $menuService,
                Location $location,
                UserInterface $user,
                array $roles,
            ): void {
                $this->count++;
            }
        };

        $user = $this->buildUser('user1');
        $service = new MenuService([$generator]);
        $service->getMenuFor($user, Location::TopRight);
        $service->getMenuFor($user, Location::TopRight);

        $this->assertSame(1, $callCount);
    }

    public function testGetMenuForCachesByLocation(): void
    {
        $callCount = 0;
        $generator = new class ($callCount) implements MenuGeneratorInterface {
            public function __construct(public int &$count)
            {
            }

            public function generate(
                MenuService $menuService,
                Location $location,
                UserInterface $user,
                array $roles,
            ): void {
                $this->count++;
            }
        };

        $user = $this->buildUser('user1');
        $service = new MenuService([$generator]);
        $service->getMenuFor($user, Location::TopRight);
        $service->getMenuFor($user, Location::TopLeft);
        $service->getMenuFor($user, Location::TopRight);
        $service->getMenuFor($user, Location::TopLeft);

        $this->assertSame(2, $callCount);
    }

    public function testGetMenuForCacheIsNotSharedAcrossInstances(): void
    {
        $callCount = 0;
        $generator = new class ($callCount) implements MenuGeneratorInterface {
            public function __construct(public int &$count)
            {
            }

            public function generate(
                MenuService $menuService,
                Location $location,
                UserInterface $user,
                array $roles,
            ): void {
                $this->count++;
            }
        };

        $user = $this->buildUser('user1');
        $service1 = new MenuService([$generator]);
        $service2 = new MenuService([$generator]);
        $service1->getMenuFor($user, Location::TopRight);
        $service2->getMenuFor($user, Location::TopRight);

        $this->assertSame(2, $callCount);
    }

    public function testResetEmptiesTheCache(): void
    {
        $callCount = 0;
        $generator = new class ($callCount) implements MenuGeneratorInterface {
            public function __construct(public int &$count)
            {
            }

            public function generate(
                MenuService $menuService,
                Location $location,
                UserInterface $user,
                array $roles,
            ): void {
                $this->count++;
                $menuService->getSection('section.' . $this->count, Location::TopRight);
            }
        };

        $user = $this->buildUser('user1');
        $service = new MenuService([$generator]);
        $result1 = $service->getMenuFor($user, Location::TopRight);
        $service->reset();
        $result2 = $service->getMenuFor($user, Location::TopRight);
        $result3 = $service->getMenuFor($user, Location::TopRight);

        $this->assertSame(2, $callCount);
        $this->assertCount(1, $result1);
        $this->assertCount(1, $result2);
        $this->assertSame('section.1', $result1[0]->name);
        $this->assertSame('section.2', $result2[0]->name);
        $this->assertSame($result2, $result3);
    }

    public function testResetEmptiesTheCurrentSections(): void
    {
        $service = new MenuService([]);
        $first = $service->getSection('section.a', Location::TopRight);
        $service->getSection('section.b', Location::TopRight);

        $service->reset();

        $second = $service->getSection('section.a', Location::TopRight);
        $this->assertNotSame($first, $second);
        $this->assertSame(1, $second->position);
    }

    public function testGetMenuForIsolatesLocations(): void
    {
        $generator = new class implements MenuGeneratorInterface {
            public function generate(
                MenuService $menuService,
                Location $location,
                UserInterface $user,
                array $roles,
            ): void {
                $menuService->getSection('section.shared', Location::TopRight);
                $menuService->getSection('section.only-b', Location::TopLeft);
            }
        };

        $user = $this->buildUser('user1');
        $service = new MenuService([$generator]);
        $resultA = $service->getMenuFor($user, Location::TopRight);
        $resultB = $service->getMenuFor($user, Location::TopLeft);

        $this->assertCount(1, $resultA);
        $this->assertCount(1, $resultB);
        $this->assertSame('section.shared', $resultA[0]->name);
        $this->assertSame('section.only-b', $resultB[0]->name);
    }

    public function testGetMenuForReturnsSectionsSortedByPosition(): void
    {
        $generator = new class implements MenuGeneratorInterface {
            public function generate(
                MenuService $menuService,
                Location $location,
                UserInterface $user,
                array $roles,
            ): void {
                $sectionA = $menuService->getSection('section.a', Location::TopRight);
                $sectionB = $menuService->getSection('section.b', Location::TopRight);
                $sectionC = $menuService->getSection('section.c', Location::TopRight);
                $sectionA->addItem(new MenuItem(name: 'a', route: 'route_a'));
                $sectionB->addItem(new MenuItem(name: 'b', route: 'route_b'));
                $sectionC->addItem(new MenuItem(name: 'c', route: 'route_c'));
            }
        };

        $service = new MenuService([$generator]);
        $sections = $service->getMenuFor($this->buildUser('user1'), Location::TopRight);

        $this->assertCount(3, $sections);
        $this->assertSame(1, $sections[0]->position);
        $this->assertSame(2, $sections[1]->position);
        $this->assertSame(3, $sections[2]->position);
        $this->assertSame('section.a', $sections[0]->name);
        $this->assertSame('section.b', $sections[1]->name);
        $this->assertSame('section.c', $sections[2]->name);
    }

    public function testGetMenuForResetsSectionsBetweenUsers(): void
    {
        $callCount = 0;
        $generator = new class ($callCount) implements MenuGeneratorInterface {
            public function __construct(public int &$count)
            {
            }

            public function generate(
                MenuService $menuService,
                Location $location,
                UserInterface $user,
                array $roles,
            ): void {
                $this->count++;
                $menuService->getSection('section.' . $this->count, Location::TopRight);
            }
        };

        $service = new MenuService([$generator]);
        $result1 = $service->getMenuFor($this->buildUser('user1'), Location::TopRight);
        $result2 = $service->getMenuFor($this->buildUser('user2'), Location::TopRight);

        $this->assertCount(1, $result1);
        $this->assertCount(1, $result2);
        $this->assertSame('section.1', $result1[0]->name);
        $this->assertSame('section.2', $result2[0]->name);
    }
}
