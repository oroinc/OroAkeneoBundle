<?php

namespace Oro\Bundle\AkeneoBundle\Controller;

use Akeneo\Pim\ApiClient\Exception\ExceptionInterface;
use Oro\Bundle\AkeneoBundle\Entity\AkeneoSettings;
use Oro\Bundle\AkeneoBundle\Integration\AkeneoTransportInterface;
use Oro\Bundle\CurrencyBundle\Provider\CurrencyProviderInterface;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Form\Type\ChannelType;
use Oro\Bundle\SecurityBundle\Annotation\Acl;
use Psr\Http\Client\ClientExceptionInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class ValidateConnectionController extends AbstractController
{
    const CONNECTION_SUCCESSFUL_MESSAGE = 'oro.akeneo.connection.successfull';
    const CONNECTION_ERROR_MESSAGE = 'oro.akeneo.connection.error';

    /** @var CurrencyProviderInterface */
    private $currencyProvider;

    /** @var TranslatorInterface */
    private $translator;

    /** @var AkeneoTransportInterface */
    private $akeneoTransport;

    public function __construct(
        CurrencyProviderInterface $currencyProvider,
        TranslatorInterface $translator,
        AkeneoTransportInterface $akeneoTransport
    ) {
        $this->currencyProvider = $currencyProvider;
        $this->translator = $translator;
        $this->akeneoTransport = $akeneoTransport;
    }

    /**
     * @Route(path="/validate-akeneo-connection/{channelId}/", name="oro_akeneo_validate_connection", methods={"POST"})
     * @ParamConverter("channel", class="OroIntegrationBundle:Channel", options={"id"="channelId"})
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

        $akeneoChannelNames = [];
        $akeneoCurrencies = [];
        $akeneoLocales = [];

        try {
            $this->akeneoTransport->init($akeneoSettings, false);
            $success = true;
            $message = $this->translator->trans(self::CONNECTION_SUCCESSFUL_MESSAGE);
            switch ($request->get('synctype', 'all')) {
                case 'channels':
                    $akeneoChannelNames = $this->akeneoTransport->getChannels();
                    break;
                case 'currencies':
                    $akeneoCurrencies = $this->akeneoTransport->getMergedCurrencies();
                    break;
                case 'locales':
                    $akeneoLocales = $this->akeneoTransport->getLocales();
                    break;
                default:
                    $akeneoChannelNames = $this->akeneoTransport->getChannels();
                    $akeneoCurrencies = $this->akeneoTransport->getMergedCurrencies();
                    $akeneoLocales = $this->akeneoTransport->getLocales();
            }
        } catch (ClientExceptionInterface | ExceptionInterface $e) {
            $success = false;
            $message = $e->getMessage();
        } catch (\Exception $e) {
            $success = false;
            $message = $this->translator->trans(self::CONNECTION_ERROR_MESSAGE);
        }

        return new JsonResponse(
            [
                'channels' => $akeneoChannelNames,
                'akeneoCurrencies' => $akeneoCurrencies,
                'akeneoLocales' => $akeneoLocales,
                'success' => $success,
                'message' => $message,
                'currencyList' => $this->currencyProvider->getCurrencies(),
            ]
        );
    }
}
