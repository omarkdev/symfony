<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Controller;

use Symfony\Component\ErrorCatcher\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Debug\FileLinkFormatter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Loader\ExistsLoaderInterface;

/**
 * ExceptionController.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExceptionController
{
    protected $twig;
    protected $debug;
    protected $profiler;
    private $errorRenderer;

    public function __construct(Profiler $profiler = null, Environment $twig, bool $debug, FileLinkFormatter $fileLinkFormat = null, HtmlErrorRenderer $errorRenderer = null)
    {
        $this->profiler = $profiler;
        $this->twig = $twig;
        $this->debug = $debug;
        $this->errorRenderer = $errorRenderer;

        if (null === $errorRenderer) {
            $this->errorRenderer = new HtmlErrorRenderer($debug, $this->twig->getCharset(), $fileLinkFormat);
        }
    }

    /**
     * Renders the exception panel for the given token.
     *
     * @param string $token The profiler token
     *
     * @return Response A Response instance
     *
     * @throws NotFoundHttpException
     */
    public function showAction($token)
    {
        if (null === $this->profiler) {
            throw new NotFoundHttpException('The profiler must be enabled.');
        }

        $this->profiler->disable();

        $exception = $this->profiler->loadProfile($token)->getCollector('exception')->getException();
        $template = $this->getTemplate();

        if (!$this->twig->getLoader()->exists($template)) {
            return new Response($this->errorRenderer->getBody($exception), 200, ['Content-Type' => 'text/html']);
        }

        $code = $exception->getStatusCode();

        return new Response($this->twig->render(
            $template,
            [
                'status_code' => $code,
                'status_text' => Response::$statusTexts[$code],
                'exception' => $exception,
                'logger' => null,
                'currentContent' => '',
            ]
        ), 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Renders the exception panel stylesheet for the given token.
     *
     * @param string $token The profiler token
     *
     * @return Response A Response instance
     *
     * @throws NotFoundHttpException
     */
    public function cssAction($token)
    {
        if (null === $this->profiler) {
            throw new NotFoundHttpException('The profiler must be enabled.');
        }

        $this->profiler->disable();

        $template = $this->getTemplate();

        if (!$this->templateExists($template)) {
            return new Response($this->errorRenderer->getStylesheet(), 200, ['Content-Type' => 'text/css']);
        }

        return new Response($this->twig->render('@WebProfiler/Collector/exception.css.twig'), 200, ['Content-Type' => 'text/css']);
    }

    protected function getTemplate()
    {
        return '@Twig/Exception/'.($this->debug ? 'exception' : 'error').'.html.twig';
    }

    // to be removed when the minimum required version of Twig is >= 2.0
    protected function templateExists($template)
    {
        $loader = $this->twig->getLoader();
        if ($loader instanceof ExistsLoaderInterface) {
            return $loader->exists($template);
        }

        try {
            $loader->getSource($template);

            return true;
        } catch (LoaderError $e) {
        }

        return false;
    }
}
