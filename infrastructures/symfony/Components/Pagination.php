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

namespace Teknoo\East\UXBundle\Components;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

use function http_build_query;

/**
 * Twig component rendering the pagination interface of a list: previous, page numbers and next buttons, bound to
 * the `page` model of the enclosing Live Component (`ListWindow`). `pageCount` is the view parameter of East
 * Common's list recipe.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[AsTwigComponent]
final class Pagination
{
    public int $pageCount = 0;
    public int $page = 1;

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function buildUrl(int $page): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $params = $request ? $request->query->all() : [];
        $params['page'] = $page;

        return '?' . http_build_query($params);
    }
}
