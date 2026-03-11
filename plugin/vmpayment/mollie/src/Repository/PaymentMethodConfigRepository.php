<?php

namespace Mollie\Payment\Repository;

use Mollie\BusinessLogic\PaymentMethod\Model\PaymentMethodConfig;
use Mollie\Infrastructure\Logger\Logger;
use Mollie\Infrastructure\ORM\QueryFilter\QueryFilter;

class PaymentMethodConfigRepository extends BaseRepository
{
    const THIS_CLASS_NAME = __CLASS__;

    /**
     * Find PaymentMethodConfig by profile ID and Mollie method ID
     *
     * @param string $profileId
     * @param string $mollieMethodId
     *
     * @return PaymentMethodConfig|null
     */
    public function findByProfileAndMollieId(string $profileId, string $mollieMethodId): ?PaymentMethodConfig
    {
        try {
            $filter = new QueryFilter();
            $filter->where('profileId', '=', $profileId);
            $filter->where('mollieId', '=', $mollieMethodId);

            /** @var PaymentMethodConfig|null $config */
            $config = $this->selectOne($filter);

            return $config;
        } catch (\Exception $e) {
            Logger::logError($e->getMessage(), 'Payment Method Config Repository');

            return null;
        }
    }
}
