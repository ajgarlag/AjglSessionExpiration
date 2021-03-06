<?php

/*
 * This file is part of the AJGL packages
 *
 * Copyright (C) Antonio J. García Lagar <aj@garcialagar.es>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ajgl\Security\Http\Firewall;

use Ajgl\Security\Core\Exception\SessionExpiredException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * SessionExpirationListener controls idle sessions.
 *
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
class SessionExpirationListener implements ListenerInterface
{
    private $securityContext;
    private $httpUtils;
    private $maxIdleTime;
    private $targetUrl;
    private $logger;

    public function __construct(SecurityContextInterface $securityContext, HttpUtils $httpUtils, $maxIdleTime, $targetUrl = null, LoggerInterface $logger = null)
    {
        $this->securityContext = $securityContext;
        $this->httpUtils = $httpUtils;
        $this->setMaxIdleTime($maxIdleTime);
        $this->targetUrl = $targetUrl;
        $this->logger = $logger;
    }

    /**
     * Handles expired sessions.
     *
     * @param GetResponseEvent $event A GetResponseEvent instance
     *
     * @throws SessionExpiredException If the session has expired
     */
    public function handle(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        $session = $request->getSession();

        if (null === $session || null === $token = $this->securityContext->getToken()) {
            return;
        }

        if (!$this->hasSessionExpired($session)) {
            return;
        }

        if (null !== $this->logger) {
            $this->logger->info(sprintf("Expired session detected for user named '%s'", $token->getUsername()));
        }

        $this->removeSessionData($session);

        if (null === $this->targetUrl) {
            throw new SessionExpiredException();
        }

        $response = $this->httpUtils->createRedirectResponse($request, $this->targetUrl);
        $event->setResponse($response);
    }

    /**
     * @param int $maxIdleTime
     */
    private function setMaxIdleTime($maxIdleTime)
    {
        if ($maxIdleTime > ini_get('session.gc_maxlifetime') && null !== $this->logger) {
            $this->logger->warning(sprintf("Max idle time should not be greater than 'session.gc_maxlifetime"));
        }
        $this->maxIdleTime = (int) $maxIdleTime;
    }

    /**
     * Removes session data.
     *
     * @param SessionInterface $session
     */
    protected function removeSessionData(SessionInterface $session)
    {
        $this->securityContext->setToken(null);
        $session->invalidate();
    }

    /**
     * Checks if the given session has expired.
     *
     * @param SessionInterface $session
     *
     * @return bool
     */
    protected function hasSessionExpired(SessionInterface $session)
    {
        return time() - $session->getMetadataBag()->getLastUsed() >= $this->maxIdleTime;
    }
}
