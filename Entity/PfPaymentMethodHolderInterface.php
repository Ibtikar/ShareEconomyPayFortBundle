<?php

namespace Ibtikar\ShareEconomyPayFortBundle\Entity;

/**
 * An interface that the invoice Subject object should implement.
 * In most circumstances, only a single object should implement
 * this interface as the ResolveTargetEntityListener can only
 * change the target to a single object.
 */
interface PfPaymentMethodHolderInterface
{

    public function addPfPaymentMethod(PfPaymentMethod $pfPaymentMethod);

    public function removePfPaymentMethod(PfPaymentMethod $pfPaymentMethod);

    public function getPfPaymentMethods();

}