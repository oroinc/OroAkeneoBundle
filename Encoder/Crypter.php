<?php

namespace Oro\Bundle\AkeneoBundle\Encoder;

use Oro\Bundle\SecurityBundle\Encoder\SymmetricCrypterInterface;

class Crypter
{
    /**
     * @var SymmetricCrypterInterface
     */
    private $crypter;

    /**
     * Crypter constructor.
     */
    public function __construct(SymmetricCrypterInterface $crypter)
    {
        $this->crypter = $crypter;
    }

    public function getDecryptData($data = null)
    {
        return $this->crypter->decryptData($data);
    }

    public function getEncryptData($data = null)
    {
        return $this->crypter->encryptData($data);
    }
}
