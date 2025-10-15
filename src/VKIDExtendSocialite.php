<?php

namespace MoveMoveApp\VKID;

use SocialiteProviders\Manager\SocialiteWasCalled;

class VKIDExtendSocialite
{
    public function handle(SocialiteWasCalled $socialiteWasCalled): void
    {
        $event->extendSocialite('vkid', Provider::class);
    }
}