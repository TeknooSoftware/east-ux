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

namespace Teknoo\East\UXBundle\Mercure;

use DomainException;
use RuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Teknoo\East\UX\Contracts\Window\WindowMethodPublisherInterface;
use Teknoo\East\UX\Object\DTO\Window\WindowReference;

use function in_array;
use function sprintf;

/**
 * Publishes the name of a method of a window on the Mercure topic of its reference, as a private update: subscribers
 * need a token with the grant `TOPIC_PATTERN`. The topic is a constant IRI and not a URL of the application: the hub
 * never fetches it, and a worker can build it without knowing the host of the application.
 *
 * All the windows share this grant, because a Mercure cookie is replaced by the next response setting it: with a grant
 * for each topic, a window would lose its grant as soon as another one is rendered.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
final class WindowMethodPublisher implements WindowMethodPublisherInterface
{
    public const string TOPIC_PREFIX = 'https://teknoo.software/east-collection/ux/window/';

    public const string TOPIC_PATTERN = self::TOPIC_PREFIX . ':id';

    public function __construct(
        private readonly ?HubInterface $hub = null,
    ) {
    }

    public function publish(WindowReference $windowReference, string $method): WindowMethodPublisherInterface
    {
        if (!in_array($method, $windowReference->methods, true)) {
            throw new DomainException(
                message: sprintf('The method "%s" is not in the reference of the window', $method),
                code: 400,
            );
        }

        if (null === $this->hub) {
            throw new RuntimeException(
                message: 'A Mercure hub is required to call the method of a window',
                code: 500,
            );
        }

        $this->hub->publish(
            new Update(
                topics: self::TOPIC_PREFIX . $windowReference->id,
                data: $method,
                private: true,
            ),
        );

        return $this;
    }
}
