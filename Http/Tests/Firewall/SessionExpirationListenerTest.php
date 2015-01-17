<?php

/*
 * This file is part of the AJGL packages
 *
 * Copyright (C) Antonio J. García Lagar <aj@garcialagar.es>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ajgl\Security\Http\Tests\Firewall;

use Ajgl\Security\Http\Firewall\SessionExpirationListener;

/**
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
class SessionExpirationListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testHandleWhenNoSession()
    {
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request
            ->expects($this->once())
            ->method('getSession')
            ->will($this->returnValue(null));

        $event = $this->getResponseEvent();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request));

        $listener = new SessionExpirationListener(
            $this->getMock('Symfony\Component\Security\Core\SecurityContextInterface'),
            $this->getHttpUtils(),
            1440
        );

        $this->assertNull($listener->handle($event));
    }

    public function testHandleWhenNoToken()
    {
        $session = $this->getMock('Symfony\Component\HttpFoundation\Session\SessionInterface');

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request
            ->expects($this->any())
            ->method('getSession')
            ->will($this->returnValue($session));

        $securityContext = $this->getMock('Symfony\Component\Security\Core\SecurityContextInterface');
        $securityContext
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue(null));

        $event = $this->getResponseEvent();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request));

        $listener = new SessionExpirationListener(
            $securityContext,
            $this->getHttpUtils(),
            1440
        );

        $this->assertNull($listener->handle($event));
    }

    public function testHandleWhenSessionHasNotExpired()
    {
        $metadataBag = $this->getMock('\Symfony\Component\HttpFoundation\Session\Storage\MetadataBag');
        $metadataBag
            ->expects($this->once())
            ->method('getLastUsed')
            ->will($this->returnValue(time()));

        $session = $this->getMock('Symfony\Component\HttpFoundation\Session\SessionInterface');
        $session
            ->expects($this->any())
            ->method('getMetadataBag')
            ->will($this->returnValue($metadataBag));

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request
            ->expects($this->any())
            ->method('getSession')
            ->will($this->returnValue($session));

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        $securityContext = $this->getMock('Symfony\Component\Security\Core\SecurityContextInterface');
        $securityContext
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue($token));

        $event = $this->getResponseEvent();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request));

        $listener = new SessionExpirationListener(
            $securityContext,
            $this->getHttpUtils(),
            1440
        );

        $this->assertNull($listener->handle($event));
    }

    /**
     * @expectedException \Ajgl\Security\Core\Exception\SessionExpiredException
     */
    public function testHandleWhenSessionHasExpiredAndNoTargetUrl()
    {
        $metadataBag = $this->getMock('\Symfony\Component\HttpFoundation\Session\Storage\MetadataBag');
        $metadataBag
            ->expects($this->once())
            ->method('getLastUsed')
            ->will($this->returnValue(time()-2));

        $session = $this->getMock('Symfony\Component\HttpFoundation\Session\SessionInterface');
        $session
            ->expects($this->any())
            ->method('getMetadataBag')
            ->will($this->returnValue($metadataBag));

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request
            ->expects($this->any())
            ->method('getSession')
            ->will($this->returnValue($session));

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        $securityContext = $this->getMock('Symfony\Component\Security\Core\SecurityContextInterface');
        $securityContext
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue($token));

        $event = $this->getResponseEvent();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request));

        $listener = new SessionExpirationListener(
            $securityContext,
            $this->getHttpUtils(),
            1
        );

        $listener->handle($event);
    }

    public function testHandleWhenSessionHasExpiredAndTargetUrl()
    {
        $metadataBag = $this->getMock('\Symfony\Component\HttpFoundation\Session\Storage\MetadataBag');
        $metadataBag
            ->expects($this->once())
            ->method('getLastUsed')
            ->will($this->returnValue(time()-2));

        $session = $this->getMock('Symfony\Component\HttpFoundation\Session\SessionInterface');
        $session
            ->expects($this->any())
            ->method('getMetadataBag')
            ->will($this->returnValue($metadataBag));

        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request
            ->expects($this->any())
            ->method('getSession')
            ->will($this->returnValue($session));

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        $securityContext = $this->getMock('Symfony\Component\Security\Core\SecurityContextInterface');
        $securityContext
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue($token));

        $response = $this->getMock('Symfony\Component\HttpFoundation\Response');

        $event = $this->getResponseEvent();
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request));
        $event
            ->expects($this->once())
            ->method('setResponse')
            ->with($this->identicalTo($response));

        $httpUtils = $this->getHttpUtils();
        $httpUtils
            ->expects($this->once())
            ->method('createRedirectResponse')
            ->with($this->identicalTo($request), $this->equalTo('/expired'))
            ->will($this->returnValue($response));

        $listener = new SessionExpirationListener(
            $securityContext,
            $httpUtils,
            1,
            '/expired'
        );

        $listener->handle($event);
    }

    private function getHttpUtils()
    {
        return $this->getMockBuilder('Symfony\Component\Security\Http\HttpUtils')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getResponseEvent()
    {
        return $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
