<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;

/**
 * This route is used to send a password recovery mail
 * The required parameters are: "email"
 * The process can be completed with the hash in the Route Shopware\Core\Checkout\Customer\SalesChannel\ResetPasswordRouteInterface
 */
interface SendPasswordRecoveryMailRouteInterface
{
    public function sendRecoveryMail(RequestDataBag $data, SalesChannelContext $context): SuccessResponse;
}
