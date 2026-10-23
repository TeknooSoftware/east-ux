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

namespace Teknoo\East\UXBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Teknoo\East\UX\Contracts\Enum\Theme\Mode;
use Teknoo\East\UX\Contracts\Enum\Theme\Style;
use Teknoo\East\UX\Service\ThemeService;

use function array_key_exists;
use function is_array;
use function is_string;
use function json_decode;

/**
 * Controller receiving the theme chosen by the visitor (route `_teknoo_east_ux_theme_update`, `POST /theme`), sent by
 * the `theme` Stimulus controller as a JSON object with a `style` (`legacy`, `modern`) and/or a `mode` (`light`,
 * `dark`, `auto`), and saving it with `ThemeService` (in the session, or in the storage of the application).
 *
 * Only JSON bodies are accepted (415 otherwise): a cross-site form can not send one, and a cross-site script can not
 * send one without the consent of the application (CORS preflight). A body without style nor mode, or with an
 * invalid value, is refused (400). The response is a 204 without content.
 *
 * It extends Symfony's `AbstractController`: East Foundation's router hands a route back to Symfony only for such a
 * controller.
 *
 * @copyright   Copyright (c) EIRL Richard Déloge (https://deloge.io - richard@deloge.io)
 * @copyright   Copyright (c) SASU Teknoo Software (https://teknoo.software - contact@teknoo.software)
 * @license     http://teknoo.software/license/bsd-3         3-Clause BSD License
 * @author      Richard Déloge <richard@teknoo.software>
 */
class ThemePreferenceUpdater extends AbstractController
{
    public function __construct(
        private readonly ThemeService $themeService,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if ('json' !== $request->getContentTypeFormat()) {
            return new Response('The theme must be sent as JSON', Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || (!array_key_exists('style', $data) && !array_key_exists('mode', $data))) {
            return new Response('Missing style or mode', Response::HTTP_BAD_REQUEST);
        }

        $style = null;
        if (array_key_exists('style', $data)) {
            $style = is_string($data['style']) ? Style::tryFrom($data['style']) : null;

            if (null === $style) {
                return new Response('Invalid style', Response::HTTP_BAD_REQUEST);
            }
        }

        $mode = null;
        if (array_key_exists('mode', $data)) {
            $mode = is_string($data['mode']) ? Mode::tryFrom($data['mode']) : null;

            if (null === $mode) {
                return new Response('Invalid mode', Response::HTTP_BAD_REQUEST);
            }
        }

        $this->themeService->update(
            $this->themeService->getPreference()->with(style: $style, mode: $mode),
        );

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
