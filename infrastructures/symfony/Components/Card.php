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

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Twig component rendering a Bootstrap card with a title, a content and up to two action links. The content is
 * escaped, its line breaks converted to `<br />`; set `contentIsHtml` only for a trusted HTML content, rendered
 * as is (line breaks still converted).
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
#[AsTwigComponent]
class Card
{
    public string $title = '';
    public string $content = '';
    public bool $contentIsHtml = false;
    public string $leftActionPath = '';
    public string $leftActionText = '';
    public string $rightActionPath = '';
    public string $rightActionText = '';
}
