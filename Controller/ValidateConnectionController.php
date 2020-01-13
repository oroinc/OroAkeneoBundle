<?php

namespace Oro\Bundle\AkeneoBundle\Controller;

use Akeneo\Pim\ApiClient\Exception\ExceptionInterface;
use Oro\Bundle\AkeneoBundle\Entity\AkeneoSettings;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Form\Type\ChannelType;
use Oro\Bundle\SecurityBundle\Annotation\Acl;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ValidateConnectionController extends Controller
{
    const CONNECTION_SUCCESSFUL_MESSAGE = 'oro.akeneo.connection.successfull';
    const CONNECTION_ERROR_MESSAGE = 'oro.akeneo.connection.error';

    /**
     * @Route("/validate-akeneo-connection/{channelId}/", name="oro_akeneo_validate_connection")
     * @ParamConverter("channel", class="OroIntegrationBundle:Channel", options={"id"="channelId"})
     * @Method("POST")
     *
     * @Acl(
     *      id="oro_integration_channel",
     *      type="entity",
     *      class="OroIntegrationBundle:Channel",
     *      permission="VIEW"
     * )
     *
     * @throws \InvalidArgumentException
     */
    public function validateConnectionAction(Request $request, Channel $channel = null): JsonResponse
    {
        if (!$channel) {
            $channel = new Channel();
        }

        $form = $this->createForm(ChannelType::class, $channel);
        $form->handleRequest($request);

        /** @var AkeneoSettings $akeneoSettings */
        $akeneoSettings = $channel->getTransport();

        $channelId = $channel->getTransport()->getId();

        if ($channelId && null == $akeneoSettings->getPassword()) {
            $entityManager = $this->container->get('doctrine')->getManagerForClass(AkeneoSettings::class);
            $repository = $entityManager->getRepository(AkeneoSettings::class);
            $akeneoSettingsEntity = $repository->findOneBy(['id' => $channelId]);
            $akeneoSettings->setPassword($akeneoSettingsEntity->getPassword());
        }

        $currencyConfig = $this->container->get('oro_currency.config.currency');

        $akeneoChannelNames = [];
        $akeneoCurrencies = [];
        $akeneoLocales = [];

        try {
            $transport = $this->get('oro_akeneo.integration.transport');
            $transport->init($akeneoSettings, false);
            $success = true;
            $message = self::CONNECTION_SUCCESSFUL_MESSAGE;
            switch ($request->get('synctype', 'all')) {
                case 'channels':
                    $akeneoChannelNames = $transport->getChannels();
                    break;
                case 'currencies':
                    $akeneoCurrencies = $transport->getMergedCurrencies();
                    break;
                case 'locales':
                    $akeneoLocales = $transport->getLocales();
                    break;
                default:
                    $akeneoChannelNames = $transport->getChannels();
                    $akeneoCurrencies = $transport->getMergedCurrencies();
                    $akeneoLocales = $transport->getLocales();
            }
        } catch (ExceptionInterface $e) {
            $success = false;
            $message = $e->getMessage();
        } catch (\Exception $e) {
            $success = false;
            $message = self::CONNECTION_ERROR_MESSAGE;
        }

        return new JsonResponse(
            [
                'channels' => $akeneoChannelNames,
                'akeneoCurrencies' => $akeneoCurrencies,
                'akeneoLocales' => $akeneoLocales,
                'success' => $success,
                'message' => $this->get('translator')->trans($message),
                'currencyList' => $currencyConfig->getCurrencies(),
            ]
        );
    }
}
