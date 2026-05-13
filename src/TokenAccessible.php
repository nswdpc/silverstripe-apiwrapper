<?php

namespace Symbiote\ApiWrapper;

use SilverStripe\Control\Controller;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Member;
use SilverStripe\Security\RandomGenerator;

/**
 * @property ?string $Token
 * @property bool $RegenerateTokens
 * @extends \SilverStripe\ORM\DataExtension<static>
 */
class TokenAccessible extends DataExtension
{
    private $authToken;

    private static array $db = [
        'Token'                 => 'Varchar(128)',
        'RegenerateTokens'      => 'Boolean',
    ];

    public function onBeforeWrite()
    {

        /** @var \SilverStripe\ORM\DataObject $owner */
        $owner = $this->getOwner();
        if (!$owner->Token) {
            $owner->RegenerateTokens = true;
        }

        if ($owner->RegenerateTokens) {
            $owner->RegenerateTokens = false;
            $this->generateTokens();
        }
    }

    public function updateCMSFields(FieldList $fields)
    {
        parent::updateCMSFields($fields);

        /** @var \SilverStripe\ORM\DataObject $owner */
        $owner = $this->getOwner();
        $token = $this->userToken();

        if (!$token) {
            $token = "This user token can no longer be displayed - if you do not know this value, regenerate tokens by selecting Regenerate below";
        } else {
            $token = $owner->ID . ':' . $token;
        }

        $readOnly = ReadonlyField::create('DisplayToken', 'Token', $token);
        $fields->removeByName('Token');
        $fields->addFieldToTab('Root.Main', $readOnly, 'AuthPrivateKey');

        $fields->insertAfter('AuthPrivateKey', $fields->dataFieldByName('RegenerateTokens'));

        $fields->removeByName('Token');
    }

    public function onAfterWrite()
    {
        if ($this->authToken) {
            /** @var \SilverStripe\ORM\DataObject $owner */
            $owner = $this->getOwner();

            // store the new token so it can be displayed later
            $controller = Controller::curr();
            if ($controller instanceof Controller) {
                $controller->getRequest()->getSession()->set('member_auth_token_' . $owner->ID, $this->authToken);
            }
        }
    }

    /**
     * Generate and store the authentication tokens required
     *
     * @TODO Rework this, it's not really any better than storing text passwords
     */
    public function generateTokens()
    {
        $generator = new RandomGenerator();
        $token = $generator->randomToken('sha1');
        $owner = $this->getOwner();
        if ($owner instanceof Member) {
            $owner->Token = $owner->encryptWithUserSettings($token);
            $this->authToken = $token;
        }
    }

    public function userToken()
    {
        /** @var \SilverStripe\ORM\DataObject $owner */
        $owner = $this->getOwner();
        $controller = Controller::curr();
        return $controller ? $controller->getRequest()->getSession()->get('member_auth_token_' . $owner->ID) : null;
    }
}
