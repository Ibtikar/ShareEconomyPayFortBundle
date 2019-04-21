<?php

namespace Ibtikar\ShareEconomyPayFortBundle\Controller\API;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Nelmio\ApiDocBundle\Annotation\Operation;
use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;
use Ibtikar\ShareEconomyToolsBundle\APIResponse as ToolsBundleAPIResponses;
use Ibtikar\ShareEconomyPayFortBundle\Entity\PfPaymentMethod;

class PaymentMethodsController extends Controller
{

    /**
     * add payment method
     *
     * @ApiDoc(
     *  section="PayFort",
     *  tags={
     *     "stable"="green"
     *  },
     *  authentication=true,
     *  parameters={
     *      {"name"="cardNumber", "dataType"="string", "required"=true},
     *      {"name"="expiryDate", "dataType"="string", "required"=true},
     *      {"name"="tokenName", "dataType"="string", "required"=true},
     *      {"name"="paymentOption", "dataType"="string", "required"=false},
     *      {"name"="merchantReference", "dataType"="string", "required"=true},
     *      {"name"="fortId", "dataType"="string", "required"=true}
     *  },
     *  statusCodes = {
     *      200="Returned on success",
     *      422="Returned if there is a validation error in the sent data",
     *      500="Returned if there is an internal server error"
     *  },
     *  responseMap = {
     *      200="Ibtikar\ShareEconomyPayFortBundle\APIResponse\PaymentMethodDetailsResponse",
     *      422="Ibtikar\ShareEconomyToolsBundle\APIResponse\ValidationErrors",
     *      500="Ibtikar\ShareEconomyToolsBundle\APIResponse\InternalServerError"
     *  }
     * )
     * @author Karim Shendy <kareem.elshendy@ibtikar.net.sa>
     * @return JsonResponse
     */
    public function addAction(Request $request)
    {
        $em   = $this->getDoctrine()->getManager();
        $user = $this->getUser();
        if (!$user) {
            $output = new ToolsBundleAPIResponses\Fail();
            $output->message = 'Please login first';
        } else {
            $paymentMethod = new PfPaymentMethod();
            $paymentMethod->setHolder($user);
            $paymentMethod->setCardNumber($request->request->get('cardNumber'));
            $paymentMethod->setExpiryDate($request->request->get('expiryDate'));
            $paymentMethod->setTokenName($request->request->get('tokenName'));
            $paymentMethod->setPaymentOption($request->request->get('paymentOption'));
            $paymentMethod->setMerchantReference($request->request->get('merchantReference'));
            $paymentMethod->setFortId($request->request->get('fortId'));
            $paymentMethod->setIsDefault(false);

            $validationMessages = $this->get('api_operations')->validateObject($paymentMethod);

            if (count($validationMessages)) {
                $output         = new ToolsBundleAPIResponses\ValidationErrors();
                $output->errors = $validationMessages;
            } else {
                $em->persist($paymentMethod);

                try {
                    $em->flush();

                    $output = $this->getPaymentMethodDetailsResponse($paymentMethod);
                } catch (\Exception $exc) {
                    $output = new ToolsBundleAPIResponses\InternalServerError();

                    $this->get('logger')->critical($exc->getMessage());
                }
            }
        }

        return new JsonResponse($output);
    }

    /**
     * edit payment method
     *
     * @Operation(
     *     tags={"PayFort"},
     *     summary="edit payment method",
     *     @SWG\Parameter(
     *         name="cardNumber",
     *         in="formData",
     *         description="todo",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="expiryDate",
     *         in="formData",
     *         description="todo",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="tokenName",
     *         in="formData",
     *         description="todo",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="paymentOption",
     *         in="formData",
     *         description="todo",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="merchantReference",
     *         in="formData",
     *         description="todo",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="fortId",
     *         in="formData",
     *         description="todo",
     *         required=false,
     *         type="string"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="Returned on success"
     *     ),
     *     @SWG\Response(
     *         response="422",
     *         description="Returned if there is a validation error in the sent data"
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Access denied"
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="Returned if there is an internal server error"
     *     )
     * )
     *
     * @author Karim Shendy <kareem.elshendy@ibtikar.net.sa>
     * @return JsonResponse
     */
    public function editAction(Request $request, $id)
    {
        $em            = $this->getDoctrine()->getManager();
        $user          = $this->getUser();
        $paymentMethod = $em->getRepository('IbtikarShareEconomyPayFortBundle:PfPaymentMethod')->find($id);

        if (!$paymentMethod) {
            $output = new ToolsBundleAPIResponses\NotFound();
        } elseif ($paymentMethod->getHolder()->getId() !== $user->getId()) {
            $output = new ToolsBundleAPIResponses\AccessDenied();
        } else {
            $paymentMethod->setTokenName(null);

            $newPaymentMethod = new PfPaymentMethod();
            $newPaymentMethod->setHolder($user);
            $newPaymentMethod->setCardNumber($request->request->get('cardNumber'));
            $newPaymentMethod->setExpiryDate($request->request->get('expiryDate'));
            $newPaymentMethod->setTokenName($request->request->get('tokenName'));
            $newPaymentMethod->setPaymentOption($request->request->get('paymentOption'));
            $newPaymentMethod->setMerchantReference($request->request->get('merchantReference'));
            $newPaymentMethod->setFortId($request->request->get('fortId'));
            $newPaymentMethod->setIsDefault($paymentMethod->getIsDefault());

            $validationMessages = $this->get('api_operations')->validateObject($newPaymentMethod);

            if (count($validationMessages)) {
                $output         = new ToolsBundleAPIResponses\ValidationErrors();
                $output->errors = $validationMessages;
            } else {
                $em->persist($newPaymentMethod);

                try {
                    $em->flush();

                    $em->remove($paymentMethod);
                    $em->flush();

                    $output = $this->getPaymentMethodDetailsResponse($newPaymentMethod);
                } catch (\Exception $exc) {
                    $output = new ToolsBundleAPIResponses\InternalServerError();

                    $this->get('logger')->critical($exc->getMessage());
                }
            }
        }

        return new JsonResponse($output);
    }

    /**
     * set payment method as default
     *
     * @ApiDoc(
     *  section="PayFort",
     *  tags={
     *     "stable"="green"
     *  },
     *  authentication=true,
     * parameters={
     *      {"name"="id", "dataType"="integer", "required"=true},
     *  },
     *  statusCodes = {
     *      200="Returned on success",
     *      403="Access denied",
     *      500="Returned if there is an internal server error"
     *  },
     *  responseMap = {
     *      200="Ibtikar\ShareEconomyToolsBundle\APIResponse\Success",
     *      403="Ibtikar\ShareEconomyToolsBundle\APIResponse\AccessDenied",
     *      500="Ibtikar\ShareEconomyToolsBundle\APIResponse\InternalServerError"
     *  }
     * )
     * @author Karim Shendy <kareem.elshendy@ibtikar.net.sa>
     * @return JsonResponse
     */
    public function setDefaultAction(Request $request)
    {
        $em   = $this->getDoctrine()->getManager();
        $user = $this->getUser();

        if ($request->request->get('id') == 0) {
            $defaultPaymentMethod = $em->getRepository('IbtikarShareEconomyPayFortBundle:PfPaymentMethod')->findOneBy(['holder' => $user, 'isDefault' => true]);

            if ($defaultPaymentMethod) {
                $defaultPaymentMethod->setIsDefault(false);
                $em->flush();
            }

            $output = new ToolsBundleAPIResponses\Success();
        } else {
            $paymentMethod = $em->getRepository('IbtikarShareEconomyPayFortBundle:PfPaymentMethod')->find($request->request->get('id'));

            if (!$paymentMethod) {
                $output = new ToolsBundleAPIResponses\NotFound();
            } elseif ($paymentMethod->getHolder()->getId() !== $user->getId()) {
                $output = new ToolsBundleAPIResponses\AccessDenied();
            } else {
                $defaultPaymentMethod = $em->getRepository('IbtikarShareEconomyPayFortBundle:PfPaymentMethod')->findOneBy(['holder' => $user, 'isDefault' => true]);

                if ($defaultPaymentMethod) {
                    $defaultPaymentMethod->setIsDefault(false);
                }

                $paymentMethod->setIsDefault(true);
                $em->flush();

                $output = new ToolsBundleAPIResponses\Success();
            }
        }

        return new JsonResponse($output);
    }

    /**
     * delete payment method
     *
     * @ApiDoc(
     *  section="PayFort",
     *  tags={
     *     "stable"="green"
     *  },
     *  authentication=true,
     *  parameters={
     *      {"name"="id", "dataType"="integer", "required"=true},
     *  },
     *  statusCodes = {
     *      200="Returned on success",
     *      403="Access denied",
     *      500="Returned if there is an internal server error"
     *  },
     *  responseMap = {
     *      200="Ibtikar\ShareEconomyToolsBundle\APIResponse\Success",
     *      403="Ibtikar\ShareEconomyToolsBundle\APIResponse\AccessDenied",
     *      500="Ibtikar\ShareEconomyToolsBundle\APIResponse\InternalServerError"
     *  }
     * )
     * @author Karim Shendy <kareem.elshendy@ibtikar.net.sa>
     * @return JsonResponse
     */
    public function deleteAction(Request $request)
    {
        $em                      = $this->getDoctrine()->getManager();
        $user                    = $this->getUser();
        $paymentMethod           = $em->getRepository('IbtikarShareEconomyPayFortBundle:PfPaymentMethod')->find($request->request->get('id'));
        $preventLastDeletion     = $this->getParameter('ibtikar_share_economy_pay_fort.prevent_last_payment_method_removal');
        $userPaymentMethodsCount = $em->getRepository('IbtikarShareEconomyPayFortBundle:PfPaymentMethod')->countHolderPaymentMethods($user);

        // payment method not found
        if (!$paymentMethod) {
            $output = new ToolsBundleAPIResponses\NotFound();
        }
        // not the owner of the payment method
        elseif ($paymentMethod->getHolder()->getId() !== $user->getId()) {
            $output = new ToolsBundleAPIResponses\AccessDenied();
        }
        // cannot delete the default payment method
        elseif ($paymentMethod->getIsDefault()) {
            $output          = new ToolsBundleAPIResponses\Fail();
            $output->message = $this->get('translator')->trans('cannot_delete_default_payment_method');
        }
        // if deletion of the last payment method prevented and this is the last one then do not remove
        elseif ($preventLastDeletion && $userPaymentMethodsCount == 1) {
            $output          = new ToolsBundleAPIResponses\Fail();
            $output->message = $this->get('translator')->trans('cannot_delete_last_payment_method');
        } else {
            $paymentMethod->setTokenName(null);
            $paymentMethod->setIsDefault(false);
            $em->flush();

            $em->remove($paymentMethod);
            $em->flush();

            $output = new ToolsBundleAPIResponses\Success();
        }

        return new JsonResponse($output);
    }

    /**
     * list user payment methods
     *
     * @ApiDoc(
     *  section="PayFort",
     *  tags={
     *     "stable"="green"
     *  },
     *  authentication=true,
     *  statusCodes = {
     *      200="Returned on success",
     *      500="Returned if there is an internal server error"
     *  },
     *  responseMap = {
     *      200="Ibtikar\ShareEconomyToolsBundle\APIResponse\ItemsList",
     *      500="Ibtikar\ShareEconomyToolsBundle\APIResponse\InternalServerError"
     *  }
     * )
     * @author Karim Shendy <kareem.elshendy@ibtikar.net.sa>
     * @return JsonResponse
     */
    public function listAction(Request $request)
    {
        $em             = $this->getDoctrine()->getManager();
        $user           = $this->getUser();
        $paymentMethods = $em->getRepository('IbtikarShareEconomyPayFortBundle:PfPaymentMethod')->findBy(['holder' => $user]);
        $output         = new ToolsBundleAPIResponses\ItemsList();

        if (count($paymentMethods)) {
            foreach ($paymentMethods as $paymentMethod) {
                $credit                    = new \Ibtikar\ShareEconomyPayFortBundle\APIResponse\PaymentMethod();
                $credit->id                = $paymentMethod->getId();
                $credit->fortId            = $paymentMethod->getFortId();
                $credit->cardNumber        = $paymentMethod->getCardNumber();
                $credit->expiryDate        = $paymentMethod->getExpiryDate();
                $credit->merchantReference = $paymentMethod->getMerchantReference();
                $credit->tokenName         = $paymentMethod->getTokenName();
                $credit->paymentOption     = $paymentMethod->getPaymentOption();
                $credit->isDefault         = $paymentMethod->getIsDefault();

                $output->items[] = $credit;
            }
        }

        return new JsonResponse($output);
    }

    /**
     * list user payment methods
     *
     * @Operation(
     *     tags={"PayFort"},
     *     summary="list user payment methods",
     *     @SWG\Response(
     *         response="200",
     *         description="Returned on success"
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Access denied"
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="Returned if there is an internal server error"
     *     )
     * )
     *
     * @author Karim Shendy <kareem.elshendy@ibtikar.net.sa>
     * @return JsonResponse
     */
    public function detailsAction(Request $request, $id)
    {
        $em            = $this->getDoctrine()->getManager();
        $user          = $this->getUser();
        $paymentMethod = $em->getRepository('IbtikarShareEconomyPayFortBundle:PfPaymentMethod')->find($id);

        if (!$paymentMethod) {
            $output = new ToolsBundleAPIResponses\NotFound();
        } elseif ($paymentMethod->getHolder()->getId() !== $user->getId()) {
            $output = new ToolsBundleAPIResponses\AccessDenied();
        } else {
            $output = $this->getPaymentMethodDetailsResponse($paymentMethod);
        }

        return new JsonResponse($output);
    }

    /**
     *
     * @param PfPaymentMethod $paymentMethod
     * @return \Ibtikar\ShareEconomyPayFortBundle\APIResponse\PaymentMethodDetailsResponse
     */
    private function getPaymentMethodDetailsResponse(PfPaymentMethod $paymentMethod)
    {
        $output                    = new \Ibtikar\ShareEconomyPayFortBundle\APIResponse\PaymentMethodDetailsResponse();
        $output->id                = $paymentMethod->getId();
        $output->fortId            = $paymentMethod->getFortId();
        $output->cardNumber        = $paymentMethod->getCardNumber();
        $output->expiryDate        = $paymentMethod->getExpiryDate();
        $output->merchantReference = $paymentMethod->getMerchantReference();
        $output->tokenName         = $paymentMethod->getTokenName();
        $output->paymentOption     = $paymentMethod->getPaymentOption();
        $output->isDefault         = $paymentMethod->getIsDefault();

        return $output;
    }
}