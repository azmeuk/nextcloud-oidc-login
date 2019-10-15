<?php

namespace OCA\OIDCLogin\AppInfo;

use OCP\AppFramework\App;
use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\IRequest;
use OCP\ISession;
use OCP\IL10N;

class Application extends App
{
    private $appName = 'oidclogin';

    private $providerUrl;

    private $redirectUrl;
    /** @var IConfig */
    private $config;
    /** @var IURLGenerator */
    private $urlGenerator;

    public function __construct()
    {
        parent::__construct($this->appName);
    }

    public function register()
    {
        $l = $this->query(IL10N::class);

        $this->config = $this->query(IConfig::class);

        $userSession = $this->query(IUserSession::class);
        if ($userSession->isLoggedIn()) {
            $uid = $userSession->getUser()->getUID();
            $session = $this->query(ISession::class);
            if ($this->config->getUserValue($uid, $this->appName, 'disable_password_confirmation')) {
                $session->set('last-password-confirm', time());
            }
            if ($logoutUrl = $session->get('oidclogin_logout_url')) {
                $userSession->listen('\OC\User', 'postLogout', function () use ($logoutUrl) {
                    header('Location: ' . $logoutUrl);
                    exit();
                });
            }
            return;
        }

        $this->addAltLogin();
    }

    private function addAltLogin()
    {
        $this->urlGenerator = $this->query(IURLGenerator::class);
        $request = $this->query(IRequest::class);
        $this->redirectUrl = $request->getParam('redirect_url');

        $l = $this->query(IL10N::class);
        $this->providerUrl = $this->urlGenerator->linkToRoute($this->appName.'.login.oidc', [
            'login_redirect_url' => $this->redirectUrl
        ]);
        \OC_App::registerLogIn([
            'name' => $l->t('OpenID Connect'),
            'href' => $this->providerUrl
        ]);
    }

    private function query($className)
    {
        return $this->getContainer()->query($className);
    }
}
