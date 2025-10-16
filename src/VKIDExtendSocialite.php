<?php

namespace MoveMoveApp\VKID;

use SocialiteProviders\Manager\SocialiteWasCalled;

final class VKIDExtendSocialite
{
    public function handle(SocialiteWasCalled $event): void
    {
        $event->extendSocialite('vkid', Provider::class);
    }
}
